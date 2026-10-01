<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    token: String,
    pedido: Object,
    produtos: Object,
    itemsEnviados: Array,
});

const categoriaAtual = ref(Object.keys(props.produtos ?? {})[0] || '');
const separadorAtual = ref('produtos');
const quantidades = ref({});
const observacoes = ref({});
const carrinho = ref([]);
const aviso = ref('');
const form = useForm({ items: [] });
const chamarForm = useForm({});
let avisoTimer;
const page = usePage();

const categorias = computed(() => Object.keys(props.produtos ?? {}));
const lista = computed(() => props.produtos?.[categoriaAtual.value] ?? []);
const totalItens = computed(() => carrinho.value.reduce((soma, item) => soma + Number(item.quantidade), 0));
const totalEnviados = computed(() => (props.itemsEnviados ?? []).reduce((soma, item) => soma + Number(item.quantidade), 0));
const avisoFlash = computed(() => page.props.flash?.avisoCliente ?? '');

// Preços só para mostrar (o servidor volta a ler o preço ao gravar o pedido)
const precosPorProduto = computed(() => {
    const mapa = {};
    Object.values(props.produtos ?? {}).forEach((grupo) => {
        (grupo ?? []).forEach((produto) => {
            mapa[produto.id] = Number(produto.preco ?? 0);
        });
    });
    return mapa;
});
const precoDe = (produtoId) => precosPorProduto.value[produtoId] ?? 0;
const totalCarrinho = computed(() => carrinho.value.reduce((soma, item) => soma + precoDe(item.produto_id) * Number(item.quantidade), 0));
const euros = (valor) => `${Number(valor ?? 0).toFixed(2).replace('.', ',')} €`;
const textoArtigos = computed(() => (totalItens.value === 1 ? '1 artigo na lista' : `${totalItens.value} artigos na lista`));

const linhaDoProduto = (produto) => carrinho.value.find((linha) => linha.produto_id === produto.id);
const quantidadeNaLista = (produto) => carrinho.value
    .filter((linha) => linha.produto_id === produto.id)
    .reduce((soma, linha) => soma + Number(linha.quantidade), 0);
const maisUm = (produto) => {
    const linha = linhaDoProduto(produto);
    if (!linha) {
        quantidades.value[produto.id] = 1;
        adicionar(produto);
        return;
    }
    alterarQuantidadeCarrinho(linha, 1);
};
const menosUm = (produto) => {
    const linha = linhaDoProduto(produto);
    if (linha) alterarQuantidadeCarrinho(linha, -1);
};

const separadores = computed(() => [
    { id: 'produtos', label: 'Produtos', badge: 0, badgeClass: '' },
    { id: 'envio', label: 'Enviar', badge: totalItens.value, badgeClass: 'bg-verde text-white' },
    { id: 'enviados', label: 'Enviados', badge: totalEnviados.value, badgeClass: 'bg-azul text-white' },
]);

const estadoItem = (estado) => {
    if (estado === 'pronto') return { label: 'Pronto', classe: 'bg-verde-claro text-verde-escuro' };
    if (estado === 'preparacao') return { label: 'Em preparação', classe: 'bg-laranja-claro text-laranja-texto' };
    if (estado === 'pendente') return { label: 'Pendente', classe: 'bg-linha-fraca text-suave' };
    return { label: estado, classe: 'bg-linha-fraca text-suave' };
};

const quantidade = (produto) => Number(quantidades.value[produto.id] ?? 1);
const alterarQuantidade = (produto, delta) => {
    quantidades.value[produto.id] = Math.min(10, Math.max(1, quantidade(produto) + delta));
};

const mostrarAviso = (mensagem) => {
    aviso.value = mensagem;
    window.clearTimeout(avisoTimer);
    avisoTimer = window.setTimeout(() => {
        aviso.value = '';
    }, 3500);
};

const adicionar = (produto) => {
    if (!props.pedido?.disponivel) {
        return;
    }

    const item = {
        produto_id: produto.id,
        nome: produto.nome,
        quantidade: quantidade(produto),
        observacoes: observacoes.value[produto.id] || '',
    };
    const existente = carrinho.value.find((linha) => linha.produto_id === item.produto_id && linha.observacoes === item.observacoes);

    if (existente) {
        existente.quantidade = Math.min(10, existente.quantidade + item.quantidade);
    } else {
        carrinho.value.push(item);
    }

    quantidades.value[produto.id] = 1;
    observacoes.value[produto.id] = '';
    mostrarAviso('Adicionado. No fim, toque em "Ver pedido e enviar".');
};

const alterarQuantidadeCarrinho = (item, delta) => {
    item.quantidade = Math.min(10, item.quantidade + delta);
    if (item.quantidade <= 0) {
        carrinho.value = carrinho.value.filter((linha) => linha !== item);
    }
};

const chamarFuncionario = () => {
    if (chamarForm.processing) return;
    chamarForm.post(route('cliente.chamar', props.token), {
        preserveScroll: true,
        onSuccess: () => mostrarAviso('Funcionário chamado! Aguarde um momento.'),
        onError: () => mostrarAviso('Não foi possível chamar. Tente novamente.'),
    });
};

const enviarPedido = () => {
    if (!carrinho.value.length || form.processing) return;

    form.items = carrinho.value.map((item) => ({
        produto_id: item.produto_id,
        quantidade: item.quantidade,
        observacoes: item.observacoes || '',
    }));

    form.post(route('cliente.items', props.token), {
        preserveScroll: true,
    });
};
</script>

<template>
    <main class="flex min-h-screen flex-col bg-fundo font-sans tabular-nums text-tinta">
        <header class="sticky top-0 z-20 bg-escuro text-white">
            <div class="mx-auto flex max-w-xl items-center justify-between gap-3 px-4 py-3.5">
                <div class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-xs font-bold uppercase tracking-[.08em] text-[#8FD3B5]">ARDC Santana</span>
                    <h1 class="truncate text-[26px] font-extrabold leading-none">{{ pedido.mesa }}</h1>
                </div>
                <button
                    v-if="pedido.disponivel"
                    type="button"
                    class="flex h-12 shrink-0 items-center gap-2 rounded-full bg-laranja px-4 text-base font-extrabold text-white disabled:opacity-50"
                    :disabled="chamarForm.processing"
                    @click="chamarFuncionario"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                    {{ chamarForm.processing ? 'A chamar...' : 'Chamar' }}
                </button>
            </div>
        </header>

        <section v-if="!pedido.disponivel" class="mx-auto w-full max-w-xl px-4 py-5">
            <div class="rounded-[14px] border border-laranja/30 bg-laranja-claro p-5 text-center text-laranja-texto">
                <h2 class="text-xl font-extrabold">Pedido indisponível</h2>
                <p class="mt-2 text-[15px] font-semibold">Este pedido já foi fechado ou cancelado. Chame um elemento da equipa.</p>
            </div>
        </section>

        <template v-else>
            <nav aria-label="Separadores" class="border-b border-linha bg-white">
                <div class="mx-auto grid max-w-xl grid-cols-3 gap-1.5 px-4 py-2.5">
                    <button
                        v-for="tab in separadores"
                        :key="tab.id"
                        type="button"
                        class="flex h-12 items-center justify-center gap-1.5 rounded-[10px] border text-[15px] font-extrabold"
                        :class="separadorAtual === tab.id ? 'border-escuro bg-escuro text-white' : 'border-linha-forte bg-white text-tinta'"
                        :aria-current="separadorAtual === tab.id ? 'page' : 'false'"
                        @click="separadorAtual = tab.id"
                    >
                        {{ tab.label }}
                        <span
                            v-if="tab.badge"
                            class="inline-flex h-[22px] min-w-[22px] items-center justify-center rounded-full px-1.5 text-[13px]"
                            :class="separadorAtual === tab.id ? 'bg-white text-tinta' : tab.badgeClass"
                        >{{ tab.badge }}</span>
                    </button>
                </div>
            </nav>

            <div class="mx-auto flex w-full max-w-xl flex-1 flex-col">
                <div class="space-y-2 px-4 pt-3 empty:hidden">
                    <div v-if="form.errors.pedido" role="alert" class="rounded-xl bg-perigo-claro px-3.5 py-3 text-[15px] font-bold text-perigo-texto">
                        {{ form.errors.pedido }}
                    </div>
                    <div v-if="form.errors.items" role="alert" class="rounded-xl bg-perigo-claro px-3.5 py-3 text-[15px] font-bold text-perigo-texto">
                        Não foi possível enviar esse pedido.
                    </div>
                    <div v-if="aviso" role="status" class="rounded-xl bg-verde-claro px-3.5 py-3 text-[15px] font-bold text-verde-escuro">
                        {{ aviso }}
                    </div>
                    <div v-if="avisoFlash" role="status" class="rounded-xl bg-laranja-claro px-3.5 py-3 text-[15px] font-bold text-laranja-texto">
                        {{ avisoFlash }}
                    </div>
                </div>

                <!-- Produtos -->
                <template v-if="separadorAtual === 'produtos'">
                    <div class="flex gap-2 overflow-x-auto px-4 pb-1 pt-3">
                        <button
                            v-for="categoria in categorias"
                            :key="categoria"
                            type="button"
                            class="h-11 shrink-0 rounded-full border border-linha-forte px-[18px] text-base font-bold"
                            :class="categoria === categoriaAtual ? 'border-verde bg-verde text-white' : 'bg-white text-tinta'"
                            @click="categoriaAtual = categoria"
                        >
                            {{ categoria }}
                        </button>
                    </div>

                    <div class="flex flex-col gap-2.5 px-4 pb-4 pt-2">
                        <article
                            v-for="produto in lista"
                            :key="produto.id"
                            class="flex flex-col gap-3 rounded-[14px] bg-white p-3.5"
                            :class="quantidadeNaLista(produto) ? 'border-2 border-verde' : 'border border-linha'"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="min-w-0 text-[19px] font-extrabold leading-tight">{{ produto.nome }}</h2>
                                <span v-if="produto.preco != null" class="shrink-0 text-lg font-extrabold">{{ euros(produto.preco) }}</span>
                            </div>

                            <template v-if="!quantidadeNaLista(produto)">
                                <label class="flex flex-col gap-1.5">
                                    <span class="text-[13px] font-bold text-suave">Observações (opcional)</span>
                                    <input
                                        v-model="observacoes[produto.id]"
                                        type="text"
                                        maxlength="255"
                                        class="h-[46px] rounded-[10px] border-linha-forte px-3 text-base text-tinta"
                                        placeholder="Ex.: sem cebola, bem passado"
                                    />
                                </label>
                                <button
                                    type="button"
                                    class="flex h-[52px] items-center justify-center gap-2 rounded-[10px] bg-verde text-[17px] font-extrabold text-white hover:bg-verde-escuro"
                                    @click="adicionar(produto)"
                                >
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                    Adicionar
                                </button>
                            </template>

                            <template v-else>
                                <div class="flex items-center justify-between gap-2.5">
                                    <button type="button" aria-label="Retirar um" class="flex h-[52px] w-14 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo text-tinta" @click="menosUm(produto)">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                                    </button>
                                    <span class="flex-1 text-center text-[22px] font-extrabold text-verde">{{ quantidadeNaLista(produto) }} na lista</span>
                                    <button type="button" aria-label="Adicionar mais um" class="flex h-[52px] w-14 items-center justify-center rounded-[10px] bg-verde text-white hover:bg-verde-escuro" @click="maisUm(produto)">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                    </button>
                                </div>
                                <label class="flex flex-col gap-1.5">
                                    <span class="text-[13px] font-bold text-suave">Observações (opcional)</span>
                                    <input
                                        :value="linhaDoProduto(produto)?.observacoes ?? ''"
                                        type="text"
                                        maxlength="255"
                                        class="h-[46px] rounded-[10px] border-linha-forte px-3 text-base text-tinta"
                                        placeholder="Ex.: sem cebola, bem passado"
                                        @input="linhaDoProduto(produto).observacoes = $event.target.value"
                                    />
                                </label>
                            </template>
                        </article>
                    </div>
                </template>

                <!-- Validar e enviar -->
                <div v-else-if="separadorAtual === 'envio'" class="flex flex-col gap-2.5 p-4">
                    <div>
                        <h2 class="text-[22px] font-extrabold">Confirmar pedido</h2>
                        <p class="mt-1 text-[15px] text-suave">Revê as escolhas antes de enviar para a equipa.</p>
                    </div>

                    <div v-if="!carrinho.length" class="flex flex-col gap-3.5 rounded-[14px] border border-linha bg-white px-4 py-6 text-center">
                        <span class="text-base font-semibold text-suave-2">Ainda não escolheste produtos.</span>
                        <button type="button" class="h-[52px] rounded-[10px] bg-escuro text-base font-extrabold text-white" @click="separadorAtual = 'produtos'">
                            Escolher produtos
                        </button>
                    </div>

                    <article
                        v-for="(item, index) in carrinho"
                        :key="`${item.produto_id}-${index}`"
                        class="flex flex-col gap-2.5 rounded-[14px] border border-linha bg-white px-3.5 py-3"
                    >
                        <div class="flex justify-between gap-2.5">
                            <div class="min-w-0">
                                <h3 class="text-lg font-extrabold">{{ item.nome }}</h3>
                                <p v-if="item.observacoes" class="mt-0.5 text-[15px] font-semibold text-suave">{{ item.observacoes }}</p>
                            </div>
                            <span class="shrink-0 text-lg font-extrabold">{{ euros(precoDe(item.produto_id) * item.quantidade) }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" aria-label="Retirar um" class="flex h-12 w-12 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo text-tinta" @click="alterarQuantidadeCarrinho(item, -1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                            </button>
                            <span class="w-9 text-center text-xl font-extrabold">{{ item.quantidade }}</span>
                            <button type="button" aria-label="Adicionar mais um" class="flex h-12 w-12 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo text-tinta" @click="alterarQuantidadeCarrinho(item, 1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            </button>
                            <button type="button" class="ml-auto h-12 rounded-[10px] border border-[#F0C9C2] bg-perigo-claro px-3.5 text-[15px] font-bold text-perigo-texto" @click="alterarQuantidadeCarrinho(item, -item.quantidade)">
                                Remover
                            </button>
                        </div>
                    </article>
                </div>

                <!-- Enviados -->
                <div v-else class="flex flex-col gap-2.5 p-4">
                    <div>
                        <h2 class="text-[22px] font-extrabold">Produtos enviados</h2>
                        <p class="mt-1 text-[15px] text-suave">Aqui aparecem os produtos que já foram enviados para a equipa.</p>
                    </div>

                    <div v-if="!itemsEnviados?.length" class="flex flex-col gap-3.5 rounded-[14px] border border-linha bg-white px-4 py-6 text-center">
                        <span class="text-base font-semibold text-suave-2">Ainda não foram enviados produtos.</span>
                        <button type="button" class="h-[52px] rounded-[10px] bg-escuro text-base font-extrabold text-white" @click="separadorAtual = 'produtos'">
                            Escolher produtos
                        </button>
                    </div>

                    <article
                        v-for="item in itemsEnviados"
                        :key="item.id"
                        class="flex items-center justify-between gap-3 rounded-[14px] border border-linha bg-white px-3.5 py-3"
                    >
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="text-lg font-extrabold">{{ item.nome }}</span>
                            <span v-if="item.observacoes" class="text-[15px] font-semibold text-suave">{{ item.observacoes }}</span>
                            <span v-if="item.estado" class="self-start rounded-full px-2.5 py-0.5 text-[13px] font-bold" :class="estadoItem(item.estado).classe">{{ estadoItem(item.estado).label }}</span>
                        </div>
                        <span class="flex h-9 min-w-12 shrink-0 items-center justify-center rounded-full bg-escuro px-2.5 text-base font-extrabold text-white">{{ item.quantidade }}x</span>
                    </article>

                    <div class="rounded-xl bg-laranja-claro px-3.5 py-3 text-[15px] font-bold text-laranja-texto">Se se enganou no pedido, chame um funcionário para ajudar.</div>
                </div>
            </div>

            <footer class="sticky bottom-0 z-20 border-t border-linha bg-white shadow-[0_-6px_18px_rgba(22,32,28,.08)]">
                <div class="mx-auto flex max-w-xl flex-col gap-2.5 px-4 pb-5 pt-3">
                    <div class="flex items-baseline justify-between">
                        <span class="text-base font-semibold text-suave">{{ textoArtigos }}</span>
                        <span class="text-[28px] font-extrabold">{{ euros(totalCarrinho) }}</span>
                    </div>
                    <button
                        v-if="separadorAtual !== 'envio'"
                        type="button"
                        class="h-[60px] rounded-xl bg-verde text-[19px] font-extrabold text-white hover:bg-verde-escuro disabled:cursor-not-allowed disabled:opacity-45"
                        :disabled="!carrinho.length"
                        @click="separadorAtual = 'envio'"
                    >
                        Ver pedido e enviar
                    </button>
                    <button
                        v-else
                        type="button"
                        class="h-[60px] rounded-xl bg-verde text-[19px] font-extrabold text-white hover:bg-verde-escuro disabled:cursor-not-allowed disabled:bg-[#9DB8AC]"
                        :disabled="!carrinho.length || form.processing"
                        @click="enviarPedido"
                    >
                        {{ form.processing ? 'A enviar...' : 'Enviar pedido' }}
                    </button>
                </div>
            </footer>
        </template>
    </main>
</template>
