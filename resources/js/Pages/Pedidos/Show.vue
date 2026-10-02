<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({ pedido: Object, mesas: Array, produtos: Array, paraLevar: Boolean });
const page = usePage();

const pedidoForm = useForm({
    tipo_atendimento: props.paraLevar ? 'para_levar' : 'mesa',
    mesa_id: props.mesas?.[0]?.id ?? '',
    lugares_ocupados: '',
    submesa_letra: '',
    observacoes: '',
});
const itemForm = useForm({ pedido_id: props.pedido?.id, produto_id: '', quantidade: 1, prioridade: false, observacoes: '' });
const estadoForm = useForm({ estado: props.pedido?.estado ?? 'pendente' });
const cancelamentoForm = useForm({ estado: 'cancelado' });
const fecharContaForm = useForm({ metodo_pagamento: 'dinheiro', valor_recebido: '', troco: 0 });
const quantidade = ref(1);
const termo = ref('');
const caixaRef = ref(null);
const submesaLetras = ['A', 'B', 'C', 'D'];
const aviso = ref('');
let avisoTimer;

const secoes = [
    ['todos', 'Todos'],
    ['bebidas', 'Bebidas'],
    ['frango', 'Frango'],
    ['acompanhamentos', 'Acompanhamentos'],
    ['comida', 'Comida'],
    ['sobremesas', 'Sobremesas'],
];
const secaoAtiva = ref('todos');

const produtosFiltrados = computed(() => {
    const pesquisa = termo.value.trim().toLowerCase();

    return (props.produtos ?? []).filter((produto) => {
        const secao = produto.categoria?.secao;
        const passaSecao = secaoAtiva.value === 'todos' || secao === secaoAtiva.value;
        const passaPesquisa = !pesquisa || produto.nome.toLowerCase().includes(pesquisa);

        return passaSecao && passaPesquisa;
    });
});

const mostrarAviso = (mensagem) => {
    aviso.value = mensagem;
    window.clearTimeout(avisoTimer);
    avisoTimer = window.setTimeout(() => {
        aviso.value = '';
    }, 4000);
};

const totalPedido = computed(() => Number(props.pedido?.total ?? props.pedido?.total_calculado ?? 0));
const pedidoFechado = computed(() => ['entregue', 'cancelado'].includes(props.pedido?.estado));
const valorRecebido = computed(() => Number(fecharContaForm.valor_recebido || totalPedido.value));
const valorTroco = computed(() => Number(fecharContaForm.troco || 0));
const trocoADevolver = computed(() => Math.max(0, valorRecebido.value - totalPedido.value));
const doacaoEstimada = computed(() => Math.max(0, valorRecebido.value - totalPedido.value - valorTroco.value));
const criadoPor = computed(() => props.pedido?.operador_nome ?? props.pedido?.user?.name ?? props.pedido?.pos?.nome ?? 'Sem utilizador');
const mostrarEstadoItems = computed(() => Boolean(page.props.restaurante?.mostrar_estado_items));
const erroItem = computed(() => page.props.errors?.item);

const adicionarProduto = (produto) => {
    if (pedidoFechado.value) {
        return;
    }

    itemForm.pedido_id = props.pedido?.id;
    itemForm.produto_id = produto.id;
    itemForm.quantidade = quantidade.value || 1;
    mostrarAviso('A enviar produto para a secção...');
    itemForm.post(route('pedido-items.store'), {
        preserveScroll: true,
        onSuccess: () => {
            itemForm.produto_id = '';
            quantidade.value = 1;
            itemForm.prioridade = false;
            itemForm.observacoes = '';
            mostrarAviso('Produto enviado para a secção.');
        },
        onError: () => mostrarAviso('Nao foi possivel enviar o produto. Confirma o pedido e tenta novamente.'),
    });
};

const alternarUrgente = (item) => {
    router.patch(route('pedido-items.update', item.id), { prioridade: !item.prioridade }, {
        preserveScroll: true,
        onError: (erros) => mostrarAviso(Object.values(erros).join(' ') || 'Nao foi possivel alterar o item.'),
    });
};

const anularItem = (item) => {
    if (!confirm(`Anular 1x ${item.produto?.nome ?? 'produto'} deste pedido?`)) {
        return;
    }

    router.delete(route('pedido-items.destroy', item.id), { preserveScroll: true });
};

const cancelarPedido = () => {
    if (!confirm('Cancelar este pedido e libertar a mesa?')) {
        return;
    }

    mostrarAviso('A cancelar pedido...');
    cancelamentoForm.patch(route('pedidos.estado', props.pedido.id), {
        preserveScroll: true,
        onSuccess: () => mostrarAviso('Pedido cancelado e mesa libertada.'),
        onError: () => mostrarAviso('Nao foi possivel cancelar o pedido. Tenta novamente.'),
    });
};

const fecharConta = () => {
    fecharContaForm
        .transform((dados) => ({
            metodo_pagamento: dados.metodo_pagamento,
            valor_recebido: dados.valor_recebido || totalPedido.value,
            troco: dados.troco || 0,
        }))
        .patch(route('pedidos.fecharConta', props.pedido.id), {
            onFinish: () => fecharContaForm.transform((dados) => dados),
        });
};

const pagamentoCerto = () => {
    fecharContaForm.valor_recebido = totalPedido.value.toFixed(2);
    fecharContaForm.troco = 0;
};

const entregarTroco = () => {
    fecharContaForm.troco = trocoADevolver.value.toFixed(2);
};

const doarTroco = () => {
    fecharContaForm.troco = 0;
};

const formatarPreco = (valor) => `${Number(valor ?? 0).toFixed(2).replace('.', ',')}€`;

// Apresentação (cores por secção e nomes de estado)
const corSecao = (secao) => ({
    bebidas: { texto: 'text-secao-bar', borda: 'border-l-secao-bar', pilula: 'text-secao-bar' },
    frango: { texto: 'text-secao-grelhados', borda: 'border-l-secao-grelhados', pilula: 'text-secao-grelhados' },
    acompanhamentos: { texto: 'text-secao-acompanhamentos', borda: 'border-l-secao-acompanhamentos', pilula: 'text-secao-acompanhamentos' },
    comida: { texto: 'text-secao-cozinha', borda: 'border-l-secao-cozinha', pilula: 'text-secao-cozinha' },
    sobremesas: { texto: 'text-secao-sobremesas', borda: 'border-l-secao-sobremesas', pilula: 'text-secao-sobremesas' },
}[secao] ?? { texto: 'text-suave', borda: 'border-l-secao-servico', pilula: 'text-tinta' });
const estadoNome = (estado) => ({ pendente: 'Pendente', preparacao: 'Em preparação', pronto: 'Pronto', entregue: 'Entregue', cancelado: 'Cancelado' }[estado] ?? estado);
const estadoClass = (estado) => ({
    pendente: 'bg-laranja-claro text-laranja-texto',
    preparacao: 'bg-[#E8EEFA] text-[#1E4592]',
    pronto: 'bg-verde-claro2 text-verde-escuro',
    entregue: 'bg-linha-fraca text-suave',
    cancelado: 'bg-perigo-claro text-perigo-texto',
}[estado] ?? 'bg-linha-fraca text-suave');
const metodosPagamento = [['dinheiro', 'Dinheiro'], ['mbway', 'MBWay'], ['multibanco', 'Multibanco'], ['transferencia', 'Transferência']];
const ajustarQuantidade = (delta) => { quantidade.value = Math.max(1, Number(quantidade.value || 1) + delta); };

const escolherTipoAtendimento = (tipo) => {
    pedidoForm.tipo_atendimento = tipo;

    if (tipo === 'para_levar') {
        pedidoForm.mesa_id = '';
        pedidoForm.lugares_ocupados = '';
        pedidoForm.submesa_letra = '';
        return;
    }

    pedidoForm.mesa_id = pedidoForm.mesa_id || props.mesas?.[0]?.id || '';
};

const criarPedido = () => {
    pedidoForm
        .transform((dados) => ({
            ...dados,
            mesa_id: dados.tipo_atendimento === 'para_levar' ? null : dados.mesa_id,
            lugares_ocupados: dados.tipo_atendimento === 'para_levar' ? null : dados.lugares_ocupados,
            submesa_letra: dados.tipo_atendimento === 'para_levar' ? null : (dados.submesa_letra ? dados.submesa_letra.toUpperCase() : null),
        }))
        .post(route('pedidos.store'), {
            onFinish: () => pedidoForm.transform((dados) => dados),
        });
};

watch(() => props.pedido?.id, (pedidoId) => {
    itemForm.pedido_id = pedidoId;
    estadoForm.estado = props.pedido?.estado ?? 'pendente';
});

onMounted(() => {
    if (new URLSearchParams(window.location.search).get('caixa') === '1') {
        setTimeout(() => caixaRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);
    }
});
</script>

<template>
    <AppLayout>
        <div class="flex flex-col gap-5 font-sans text-tinta tabular-nums">
            <Link :href="route('pedidos.index')" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[15px] font-bold text-verde hover:text-verde-escuro">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                Voltar aos pedidos
            </Link>

            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-[30px] font-extrabold leading-tight">{{ pedido ? `Pedido #${pedido.id}` : 'Novo pedido' }}</h1>
                        <span v-if="pedido" class="inline-flex h-7 items-center rounded-full px-3 text-sm font-bold" :class="estadoClass(pedido.estado)">{{ estadoNome(pedido.estado) }}</span>
                    </div>
                    <p v-if="pedido" class="text-[15px] text-suave">{{ pedido.mesa?.designacao ?? 'Para levar' }} · {{ criadoPor }}</p>
                </div>
                <Link v-if="pedido" :href="route('pedidos.talao', pedido.id)" class="inline-flex h-12 items-center gap-2 rounded-[10px] border border-linha-forte bg-white px-[18px] text-base font-bold text-tinta hover:bg-fundo">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M3 9h18v8H3zM6 14h12v7H6z" /></svg>
                    Talão
                </Link>
            </div>

            <div v-if="aviso" role="status" class="flex items-center gap-2.5 rounded-[10px] bg-verde-claro px-4 py-3 text-[15px] font-bold text-verde-escuro">
                <svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7" /></svg>
                {{ aviso }}
            </div>

            <!-- Novo pedido -->
            <form v-if="!pedido" class="flex w-full max-w-xl flex-col gap-4 rounded-[14px] border border-linha bg-white p-5 sm:p-6" @submit.prevent="criarPedido">
                <AvisoErros :errors="pedidoForm.errors" :excluir="['lugares_ocupados', 'mesa_id', 'observacoes', 'submesa_letra']" />
                <div role="radiogroup" aria-label="Tipo de atendimento" class="grid grid-cols-2 gap-2">
                    <button type="button" role="radio" :aria-checked="pedidoForm.tipo_atendimento === 'mesa'" class="h-12 rounded-[10px] text-base font-bold transition" :class="pedidoForm.tipo_atendimento === 'mesa' ? 'bg-escuro text-white' : 'border border-linha-forte bg-white text-tinta hover:bg-fundo'" @click="escolherTipoAtendimento('mesa')">Mesa</button>
                    <button type="button" role="radio" :aria-checked="pedidoForm.tipo_atendimento === 'para_levar'" class="h-12 rounded-[10px] text-base font-bold transition" :class="pedidoForm.tipo_atendimento === 'para_levar' ? 'bg-escuro text-white' : 'border border-linha-forte bg-white text-tinta hover:bg-fundo'" @click="escolherTipoAtendimento('para_levar')">Para levar</button>
                </div>

                <label v-if="pedidoForm.tipo_atendimento === 'mesa'" class="flex flex-col gap-1.5">
                    <span class="text-sm font-bold text-suave">Mesa ou submesa</span>
                    <select v-model="pedidoForm.mesa_id" class="h-12 w-full rounded-[10px] border-linha-forte px-3.5 text-base text-tinta focus:border-verde focus:ring-verde">
                        <option v-for="mesa in mesas" :key="mesa.id" :value="mesa.id">
                            {{ mesa.designacao }}{{ mesa.lugares ? ` · lugares ${mesa.lugares}` : '' }} · {{ mesa.capacidade }} pessoas
                        </option>
                    </select>
                    <span v-if="pedidoForm.errors.mesa_id" class="text-sm font-bold text-perigo">{{ pedidoForm.errors.mesa_id }}</span>
                </label>

                <label v-if="pedidoForm.tipo_atendimento === 'mesa'" class="flex flex-col gap-1.5">
                    <span class="text-sm font-bold text-suave">Lugares ocupados</span>
                    <input :class="{ '!border-perigo': pedidoForm.errors.lugares_ocupados }" v-model="pedidoForm.lugares_ocupados" type="number" min="1" class="h-12 w-full rounded-[10px] border-linha-forte px-3.5 text-base text-tinta focus:border-verde focus:ring-verde" placeholder="Vazio = mesa completa"><span v-if="pedidoForm.errors.lugares_ocupados" class="block text-[13px] font-semibold text-perigo-texto">{{ pedidoForm.errors.lugares_ocupados }}</span>
                </label>

                <label v-if="pedidoForm.tipo_atendimento === 'mesa' && pedidoForm.lugares_ocupados" class="flex flex-col gap-1.5">
                    <span class="text-sm font-bold text-suave">Letra da submesa</span>
                    <select v-model="pedidoForm.submesa_letra" class="h-12 w-full rounded-[10px] border-linha-forte px-3.5 text-base text-tinta focus:border-verde focus:ring-verde uppercase">
                        <option value="">Escolher letra</option>
                        <option v-for="letra in submesaLetras" :key="letra" :value="letra">{{ letra }}</option>
                    </select>
                    <span v-if="pedidoForm.errors.submesa_letra" class="text-sm font-bold text-perigo">{{ pedidoForm.errors.submesa_letra }}</span>
                </label>

                <label class="flex flex-col gap-1.5">
                    <span class="text-sm font-bold text-suave">Observações</span>
                    <textarea :class="{ '!border-perigo': pedidoForm.errors.observacoes }" v-model="pedidoForm.observacoes" rows="3" class="w-full rounded-[10px] border-linha-forte px-3.5 py-3 text-base focus:border-verde focus:ring-verde" placeholder="Observações"></textarea><span v-if="pedidoForm.errors.observacoes" class="block text-[13px] font-semibold text-perigo-texto">{{ pedidoForm.errors.observacoes }}</span>
                </label>
                <button type="submit" :disabled="pedidoForm.processing" class="h-[52px] rounded-[10px] bg-verde text-base font-bold text-white hover:bg-verde-escuro disabled:opacity-60">Criar pedido</button>
            </form>

            <div v-else class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="flex min-w-0 flex-col gap-5">
                    <section aria-labelledby="itens-titulo" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                        <div v-if="!pedidoFechado" class="grid gap-3 border-b border-linha-fraca px-5 py-4 sm:grid-cols-3">
                            <div class="flex flex-col"><span class="text-[13px] font-semibold text-suave">Mesa</span><span class="text-lg font-extrabold">{{ pedido.mesa?.designacao ?? 'Para levar' }}</span></div>
                            <div class="flex flex-col"><span class="text-[13px] font-semibold text-suave">Pedido feito por</span><span class="text-lg font-extrabold">{{ criadoPor }}</span></div>
                            <div class="flex flex-col"><span class="text-[13px] font-semibold text-suave">Total</span><span class="text-lg font-extrabold">{{ formatarPreco(totalPedido) }}</span></div>
                        </div>
                        <h2 id="itens-titulo" class="border-b border-linha-fraca px-5 py-3.5 text-lg font-extrabold">Itens do pedido</h2>
                        <div v-if="erroItem" class="mx-5 mt-4 rounded-[10px] bg-perigo-claro px-4 py-3 text-sm font-bold text-perigo-texto">{{ erroItem }}</div>
                        <template v-if="pedido.items?.length">
                            <div v-for="item in pedido.items" :key="item.id" class="flex flex-wrap items-center gap-x-4 gap-y-2.5 border-b border-linha-fraca px-5 py-3.5 last:border-b-0" :class="item.prioridade ? 'bg-laranja-claro/60' : ''">
                                <div class="flex min-w-0 flex-grow basis-56 flex-col gap-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[17px] font-extrabold">{{ item.quantidade }}x {{ item.produto?.nome }}</span>
                                        <span v-if="item.prioridade" class="inline-flex h-6 items-center rounded-full bg-laranja px-2.5 text-xs font-extrabold tracking-wide text-white">A TERMINAR</span>
                                    </div>
                                    <span class="text-sm text-suave-2"><span class="font-bold" :class="corSecao(item.produto?.categoria?.secao).texto">{{ item.produto?.categoria?.nome }}</span> · {{ formatarPreco(item.preco_unitario) }} cada</span>
                                    <span v-if="item.observacoes" class="self-start rounded-md bg-laranja-claro px-2 py-0.5 text-sm font-semibold text-laranja-texto">Info: {{ item.observacoes }}</span>
                                </div>
                                <div class="ml-auto flex flex-wrap items-center gap-2">
                                    <span v-if="mostrarEstadoItems" class="inline-flex h-7 items-center rounded-full px-2.5 text-sm font-bold" :class="estadoClass(item.estado)">{{ estadoNome(item.estado) }}</span>
                                    <button type="button" :aria-pressed="!!item.prioridade" class="h-11 rounded-[10px] px-3.5 text-sm font-bold transition" :class="item.prioridade ? 'bg-laranja text-white' : 'border border-[#F5D9BF] bg-laranja-claro text-laranja-texto hover:brightness-95'" @click="alternarUrgente(item)">A terminar</button>
                                    <button v-if="!pedidoFechado" type="button" class="h-11 min-w-[64px] rounded-[10px] border border-[#F2C7C1] bg-white px-3.5 text-sm font-bold text-perigo hover:bg-perigo-claro" @click="anularItem(item)">
                                        {{ item.quantidade > 1 ? '-1' : 'Anular' }}
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div v-else class="px-5 py-8 text-center text-[15px] text-suave">Ainda não há comida ou bebidas neste pedido.</div>
                    </section>

                    <section aria-labelledby="adicionar-titulo" class="flex flex-col gap-4 rounded-[14px] border border-linha bg-white p-5">
                        <h2 id="adicionar-titulo" class="text-lg font-extrabold">Adicionar produtos</h2>
                            <AvisoErros :errors="itemForm.errors" />
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="flex flex-col gap-1.5">
                                <span id="qtd-rotulo" class="text-sm font-bold text-suave">Quantidade</span>
                                <div role="group" aria-labelledby="qtd-rotulo" class="flex h-12 items-stretch overflow-hidden rounded-[10px] border border-linha-forte">
                                    <button type="button" aria-label="Menos um" class="w-12 bg-fundo text-xl font-bold hover:bg-linha-fraca" @click="ajustarQuantidade(-1)">−</button>
                                    <input v-model.number="quantidade" type="number" min="1" aria-label="Quantidade" class="w-16 border-0 text-center text-lg font-extrabold focus:ring-0">
                                    <button type="button" aria-label="Mais um" class="w-12 bg-fundo text-xl font-bold hover:bg-linha-fraca" @click="ajustarQuantidade(1)">+</button>
                                </div>
                            </div>
                            <label class="flex h-12 cursor-pointer items-center gap-3 rounded-[10px] border border-linha-forte px-4 text-[15px] font-semibold">
                                <input v-model="itemForm.prioridade" type="checkbox" class="h-5 w-5 rounded border-linha-forte text-laranja focus:ring-laranja">
                                Marcar novo item como a terminar
                            </label>
                        </div>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-sm font-bold text-suave">Informação para a secção</span>
                            <textarea v-model="itemForm.observacoes" rows="2" class="w-full rounded-[10px] border-linha-forte px-3.5 py-3 text-base focus:border-verde focus:ring-verde" placeholder="Ex.: sem picante, alergia, sem molho..."></textarea>
                        </label>
                        <div role="group" aria-label="Filtrar por secção" class="flex flex-wrap gap-2">
                            <button
                                v-for="[valor, label] in secoes"
                                :key="valor"
                                type="button"
                                :aria-pressed="secaoAtiva === valor"
                                class="inline-flex h-11 items-center rounded-full border px-4 text-[15px] font-bold transition"
                                :class="secaoAtiva === valor ? 'border-escuro bg-escuro text-white' : ['border-linha-forte bg-white hover:bg-fundo', corSecao(valor).pilula]"
                                @click="secaoAtiva = valor"
                            >
                                {{ label }}
                            </button>
                        </div>
                        <label class="flex h-12 items-center gap-2.5 rounded-[10px] border border-linha-forte px-3.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                            <svg class="shrink-0 text-suave-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
                            <span class="sr-only">Procurar produto</span>
                            <input v-model="termo" type="search" class="w-full border-0 p-0 text-base focus:ring-0" placeholder="Procurar produto">
                        </label>
                        <div class="grid max-h-[58vh] grid-cols-2 gap-2.5 overflow-y-auto pr-1 sm:grid-cols-[repeat(auto-fill,minmax(150px,1fr))]">
                            <button
                                v-for="produto in produtosFiltrados"
                                :key="produto.id"
                                type="button"
                                :disabled="pedidoFechado"
                                class="flex min-h-[80px] flex-col justify-between gap-2 rounded-[10px] border border-l-[5px] border-linha-forte bg-white p-3 text-left text-tinta transition hover:bg-fundo active:scale-[.98] disabled:opacity-50"
                                :class="corSecao(produto.categoria?.secao).borda"
                                @click="adicionarProduto(produto)"
                            >
                                <span class="text-[15px] font-bold">{{ produto.nome }}</span>
                                <span class="flex flex-wrap items-end justify-between gap-1">
                                    <span class="text-[13px] font-bold" :class="corSecao(produto.categoria?.secao).texto">{{ produto.categoria?.nome }}</span>
                                    <span class="text-[15px] font-extrabold">{{ formatarPreco(produto.preco) }}</span>
                                </span>
                            </button>
                            <p v-if="!produtosFiltrados.length" class="col-span-full py-4 text-center text-[15px] text-suave">Nenhum produto encontrado.</p>
                        </div>
                    </section>
                </div>

                <aside class="flex flex-col gap-4">
                    <AvisoErros :errors="cancelamentoForm.errors" />
                    <form class="flex flex-col gap-3 rounded-[14px] border border-linha bg-white p-[18px]" @submit.prevent="estadoForm.patch(route('pedidos.estado', pedido.id))">
                        <label class="flex flex-col gap-1.5">
                            <span class="text-sm font-bold text-suave">Estado do pedido</span>
                            <select :class="{ '!border-perigo': estadoForm.errors.estado }" v-model="estadoForm.estado" class="h-12 w-full rounded-[10px] border-linha-forte px-3.5 text-base text-tinta focus:border-verde focus:ring-verde font-semibold">
                                <option value="pendente">Pendente</option>
                                <option value="preparacao">Em preparação</option>
                                <option value="entregue">Entregue</option>
                                <option value="cancelado">Cancelado</option>
                            </select><span v-if="estadoForm.errors.estado" class="block text-[13px] font-semibold text-perigo-texto">{{ estadoForm.errors.estado }}</span>
                        </label>
                        <button type="submit" :disabled="estadoForm.processing" class="h-11 rounded-[10px] border border-linha-forte bg-white text-[15px] font-bold text-tinta hover:bg-fundo disabled:opacity-60">Mudar estado</button>
                    </form>

                    <div v-if="!pedidoFechado" class="flex flex-col gap-3 xl:sticky xl:top-5 xl:z-10">
                        <form ref="caixaRef" aria-labelledby="fechar-titulo" class="flex flex-col overflow-hidden rounded-[14px] border-2 border-laranja bg-white" @submit.prevent="fecharConta">
                            <div class="flex flex-col gap-0.5 bg-laranja-claro px-[18px] py-4">
                                <span class="text-sm font-bold text-laranja-texto">Total a receber</span>
                                <span class="text-[40px] font-extrabold leading-tight text-laranja-texto">{{ formatarPreco(totalPedido) }}</span>
                            </div>
                            <div class="flex flex-col gap-3.5 p-[18px]">
                                <h2 id="fechar-titulo" class="text-lg font-extrabold">Fechar conta</h2>
                                <div class="flex flex-col gap-1.5">
                                    <span id="metodo-rotulo" class="text-sm font-bold text-suave">Método de pagamento</span>
                                    <div role="radiogroup" aria-labelledby="metodo-rotulo" class="grid grid-cols-2 gap-2">
                                        <button v-for="[valor, label] in metodosPagamento" :key="valor" type="button" role="radio"
                                            :aria-checked="fecharContaForm.metodo_pagamento === valor"
                                            class="h-12 rounded-[10px] text-[15px] font-bold transition"
                                            :class="fecharContaForm.metodo_pagamento === valor ? 'border-2 border-laranja bg-laranja-claro text-laranja-texto' : 'border border-linha-forte bg-white text-tinta hover:bg-fundo'"
                                            @click="fecharContaForm.metodo_pagamento = valor">{{ label }}</button>
                                    </div>
                                </div>
                                <label class="flex flex-col gap-1.5">
                                    <span class="text-sm font-bold text-suave">Valor recebido</span>
                                    <span class="flex h-[52px] items-center rounded-[10px] border border-linha-forte px-3.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                                        <input v-model.number="fecharContaForm.valor_recebido" type="number" min="0" step="0.01" inputmode="decimal" class="w-full flex-grow border-0 p-0 text-right text-[22px] font-extrabold focus:ring-0" :placeholder="formatarPreco(totalPedido)">
                                        <span class="pl-1.5 text-suave-2">€</span>
                                    </span>
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white text-[15px] font-bold hover:bg-fundo" @click="pagamentoCerto">Valor certo</button>
                                    <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white text-[15px] font-bold hover:bg-fundo" @click="entregarTroco">Entregar troco</button>
                                </div>
                                <label class="flex flex-col gap-1.5">
                                    <span class="text-sm font-bold text-suave">Troco entregue</span>
                                    <span class="flex h-12 items-center rounded-[10px] border border-linha-forte px-3.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                                        <input v-model.number="fecharContaForm.troco" type="number" min="0" step="0.01" inputmode="decimal" class="w-full flex-grow border-0 p-0 text-right text-lg font-bold focus:ring-0">
                                        <span class="pl-1.5 text-suave-2">€</span>
                                    </span>
                                </label>
                                <button type="button" class="h-11 rounded-[10px] border border-dashed border-verde bg-verde-claro text-sm font-bold text-verde-escuro hover:brightness-95" @click="doarTroco">
                                    Cliente deixa o troco como doação
                                </button>
                                <dl class="flex flex-col gap-1 rounded-[10px] bg-fundo px-3.5 py-3 text-[15px]">
                                    <div class="flex justify-between"><dt class="text-suave">Total</dt><dd class="font-bold">{{ formatarPreco(totalPedido) }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-suave">Recebido</dt><dd class="font-bold">{{ formatarPreco(valorRecebido) }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-suave">Troco possível</dt><dd class="font-bold">{{ formatarPreco(trocoADevolver) }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-suave">Troco entregue</dt><dd class="font-bold">{{ formatarPreco(valorTroco) }}</dd></div>
                                    <div class="flex justify-between"><dt class="text-suave">Doação</dt><dd class="font-bold text-verde">{{ formatarPreco(doacaoEstimada) }}</dd></div>
                                </dl>
                                <div v-if="Object.keys(fecharContaForm.errors).length" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto">
                                    <div v-for="erro in fecharContaForm.errors" :key="erro">{{ erro }}</div>
                                </div>
                                <button
                                    type="submit"
                                    class="h-[60px] rounded-[10px] bg-laranja text-lg font-extrabold text-white transition hover:brightness-95 disabled:opacity-60"
                                    :disabled="fecharContaForm.processing"
                                >
                                    {{ fecharContaForm.processing ? 'A fechar...' : 'Receber e imprimir talão' }}
                                </button>
                            </div>
                        </form>

                        <button
                            type="button"
                            class="h-12 w-full rounded-[10px] border border-[#F2C7C1] bg-white text-[15px] font-bold text-perigo hover:bg-perigo-claro"
                            @click="cancelarPedido"
                        >
                            Cancelar pedido
                        </button>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
