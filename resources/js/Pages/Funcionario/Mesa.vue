<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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
const page = usePage();
let avisoTimer;

// Notificação de chamada do cliente
const clienteChamou = ref(!!props.pedido?.chamado_em);
let pollingInterval = null;

const verificarChamada = async () => {
    try {
        const res = await fetch(route('funcionario.estado', props.token));
        const data = await res.json();
        clienteChamou.value = !!data.chamado_em;
    } catch {
        // ignora erros de rede
    }
};

const confirmarChamada = async () => {
    clienteChamou.value = false;
    try {
        await fetch(route('funcionario.confirmar-chamada', props.token), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });
    } catch {
        // ignora
    }
};

onMounted(() => {
    // Polling a cada 20 segundos para detectar chamadas do cliente
    pollingInterval = setInterval(verificarChamada, 20000);
});

onUnmounted(() => {
    clearInterval(pollingInterval);
});

const categorias = computed(() => Object.keys(props.produtos ?? {}));
const lista = computed(() => props.produtos?.[categoriaAtual.value] ?? []);
const totalItens = computed(() => carrinho.value.reduce((soma, item) => soma + Number(item.quantidade), 0));
const totalEnviados = computed(() => (props.itemsEnviados ?? []).reduce((soma, item) => soma + Number(item.quantidade), 0));
const avisoFlash = computed(() => page.props.flash?.avisoFuncionario ?? '');

const quantidadeNoPedido = (produto) => carrinho.value
    .filter((linha) => linha.produto_id === produto.id)
    .reduce((soma, linha) => soma + Number(linha.quantidade), 0);
const textoEnviar = computed(() => `Enviar ${totalItens.value} ${totalItens.value === 1 ? 'item' : 'itens'}`);
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
    if (!props.pedido?.disponivel) return;

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
    mostrarAviso('Adicionado ao pedido. Vai a "Enviar" para confirmar.');
};

const alterarQuantidadeCarrinho = (item, delta) => {
    item.quantidade = Math.min(10, item.quantidade + delta);
    if (item.quantidade <= 0) {
        carrinho.value = carrinho.value.filter((linha) => linha !== item);
    }
};

const enviarPedido = () => {
    if (!carrinho.value.length || form.processing) return;

    form.items = carrinho.value.map((item) => ({
        produto_id: item.produto_id,
        quantidade: item.quantidade,
        observacoes: item.observacoes || '',
    }));

    form.post(route('funcionario.items', props.token), {
        preserveScroll: true,
        onSuccess: () => {
            carrinho.value = [];
            separadorAtual.value = 'enviados';
            mostrarAviso('Pedido enviado!');
        },
    });
};
</script>

<template>
    <main class="flex min-h-screen flex-col bg-fundo font-sans tabular-nums text-tinta">
        <div class="sticky top-0 z-30">
            <header class="bg-escuro text-white">
                <div class="mx-auto flex max-w-xl items-center justify-between gap-3 px-4 py-3.5">
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span class="text-xs font-bold uppercase tracking-[.08em] text-escuro-inativo">Funcionário · ARDC Santana</span>
                        <h1 class="truncate text-[26px] font-extrabold leading-none">{{ pedido.mesa }}</h1>
                    </div>
                    <span class="flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-escuro-2 px-3 text-sm font-bold">
                        <span class="h-2 w-2 rounded-full" :class="pedido.disponivel ? 'bg-verde-ok' : 'bg-escuro-inativo'"></span>
                        {{ pedido.disponivel ? 'Ocupada' : 'Fechado' }}
                    </span>
                </div>
            </header>

            <!-- Banner de chamada do cliente -->
            <div v-if="clienteChamou" role="alert" class="bg-perigo text-white">
                <div class="mx-auto flex max-w-xl items-center gap-3 px-4 py-3">
                    <svg class="shrink-0 animate-pulse" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="text-[17px] font-extrabold">Cliente chamou</span>
                        <span class="text-sm font-medium text-perigo-claro">{{ pedido.mesa }} precisa de atenção</span>
                    </div>
                    <button type="button" class="h-12 shrink-0 rounded-[10px] bg-white px-[18px] text-[17px] font-extrabold text-perigo" @click="confirmarChamada">
                        Vou já
                    </button>
                </div>
            </div>
        </div>

        <section v-if="!pedido.disponivel" class="mx-auto w-full max-w-xl px-4 py-5">
            <div class="rounded-[14px] border border-laranja/30 bg-laranja-claro p-5 text-center text-laranja-texto">
                <h2 class="text-xl font-extrabold">Pedido indisponível</h2>
                <p class="mt-2 text-[15px] font-semibold">Este pedido já foi fechado ou cancelado.</p>
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
                    <div v-if="form.errors.pedido" role="alert" class="rounded-xl bg-perigo-claro px-3.5 py-2.5 text-[15px] font-bold text-perigo-texto">
                        {{ form.errors.pedido }}
                    </div>
                    <AvisoErros :errors="form.errors" :excluir="['pedido']" class="text-[15px]" />
                    <div v-if="aviso" role="status" class="rounded-xl bg-verde-claro px-3.5 py-2.5 text-[15px] font-bold text-verde-escuro">
                        {{ aviso }}
                    </div>
                    <div v-if="avisoFlash" role="status" class="rounded-xl bg-verde-claro px-3.5 py-2.5 text-[15px] font-bold text-verde-escuro">
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
                            class="h-11 shrink-0 rounded-full border px-[18px] text-base font-bold"
                            :class="categoria === categoriaAtual ? 'border-verde bg-verde text-white' : 'border-linha-forte bg-white text-tinta'"
                            @click="categoriaAtual = categoria"
                        >
                            {{ categoria }}
                        </button>
                    </div>

                    <div class="flex flex-col gap-2 px-4 pb-4 pt-2">
                        <article
                            v-for="produto in lista"
                            :key="produto.id"
                            class="flex flex-col gap-2.5 rounded-[14px] bg-white px-3.5 py-3"
                            :class="quantidadeNoPedido(produto) ? 'border-2 border-verde' : 'border border-linha'"
                        >
                            <div class="flex items-center justify-between gap-2.5">
                                <h2 class="min-w-0 text-lg font-extrabold">{{ produto.nome }}</h2>
                                <span v-if="quantidadeNoPedido(produto)" class="shrink-0 rounded-full bg-verde-claro px-2.5 py-0.5 text-[13px] font-extrabold text-verde-escuro">
                                    {{ quantidadeNoPedido(produto) }} no pedido
                                </span>
                            </div>

                            <label class="flex flex-col gap-1">
                                <span class="text-[13px] font-bold text-suave">Observações</span>
                                <input
                                    v-model="observacoes[produto.id]"
                                    type="text"
                                    maxlength="255"
                                    class="h-11 rounded-[10px] border-linha-forte px-3 text-base"
                                    placeholder="Ex.: sem cebola, bem passado"
                                />
                            </label>

                            <div class="flex items-center gap-2">
                                <button type="button" aria-label="Menos um" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo text-tinta" @click="alterarQuantidade(produto, -1)">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                                </button>
                                <span class="w-8 text-center text-xl font-extrabold">{{ quantidade(produto) }}</span>
                                <button type="button" aria-label="Mais um" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo text-tinta" @click="alterarQuantidade(produto, 1)">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                </button>
                                <button
                                    type="button"
                                    class="flex h-12 flex-1 items-center justify-center gap-2 rounded-[10px] bg-verde text-[17px] font-extrabold text-white hover:bg-verde-escuro"
                                    @click="adicionar(produto)"
                                >
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                    Adicionar
                                </button>
                            </div>
                        </article>
                    </div>
                </template>

                <!-- Enviar -->
                <div v-else-if="separadorAtual === 'envio'" class="flex flex-col gap-2.5 p-4">
                    <div>
                        <h2 class="text-[22px] font-extrabold">Confirmar e enviar</h2>
                        <p class="mt-1 text-[15px] text-suave">Pedido vai direto para a cozinha/bar.</p>
                    </div>

                    <div v-if="!carrinho.length" class="flex flex-col gap-3.5 rounded-[14px] border border-linha bg-white px-4 py-6 text-center">
                        <span class="text-base font-semibold text-suave-2">Carrinho vazio.</span>
                        <button type="button" class="h-[52px] rounded-[10px] bg-escuro text-base font-extrabold text-white" @click="separadorAtual = 'produtos'">
                            Escolher produtos
                        </button>
                    </div>

                    <article
                        v-for="(item, index) in carrinho"
                        :key="`${item.produto_id}-${index}`"
                        class="flex flex-col gap-2.5 rounded-[14px] border border-linha bg-white px-3.5 py-3"
                    >
                        <div class="min-w-0">
                            <h3 class="text-lg font-extrabold">{{ item.nome }}</h3>
                            <p v-if="item.observacoes" class="mt-0.5 text-[15px] font-semibold text-suave">{{ item.observacoes }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" aria-label="Retirar um" class="flex h-12 w-12 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo text-tinta" @click="alterarQuantidadeCarrinho(item, -1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                            </button>
                            <span class="w-9 text-center text-xl font-extrabold">{{ item.quantidade }}</span>
                            <button type="button" aria-label="Adicionar mais um" class="flex h-12 w-12 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo text-tinta" @click="alterarQuantidadeCarrinho(item, 1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            </button>
                            <button type="button" class="ml-auto flex h-12 items-center gap-1.5 rounded-[10px] border border-[#F0C9C2] bg-perigo-claro px-3.5 text-[15px] font-bold text-perigo-texto" @click="alterarQuantidadeCarrinho(item, -item.quantidade)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
                                Remover
                            </button>
                        </div>
                    </article>
                </div>

                <!-- Enviados -->
                <div v-else class="flex flex-col gap-2.5 p-4">
                    <div>
                        <h2 class="text-[22px] font-extrabold">Itens enviados</h2>
                        <p class="mt-1 text-[15px] text-suave">Todos os itens desta mesa.</p>
                    </div>

                    <div v-if="!itemsEnviados?.length" class="rounded-[14px] border border-linha bg-white px-4 py-6 text-center text-base font-semibold text-suave-2">
                        Ainda não foram enviados itens.
                    </div>

                    <article
                        v-for="item in itemsEnviados"
                        :key="item.id"
                        class="flex items-center justify-between gap-3 rounded-[14px] border border-linha bg-white px-3.5 py-3"
                    >
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="text-lg font-extrabold">{{ item.nome }}</span>
                            <div class="flex flex-wrap items-center gap-2 text-[13px] font-semibold text-suave">
                                <span>{{ item.hora || 'Enviado' }}</span>
                                <span v-if="item.estado" class="rounded-full px-2.5 py-0.5 font-bold" :class="estadoItem(item.estado).classe">{{ estadoItem(item.estado).label }}</span>
                            </div>
                            <span v-if="item.observacoes" class="text-[15px] font-semibold text-suave">{{ item.observacoes }}</span>
                        </div>
                        <span class="flex h-9 min-w-12 shrink-0 items-center justify-center rounded-full bg-escuro px-2.5 text-base font-extrabold text-white">{{ item.quantidade }}x</span>
                    </article>
                </div>
            </div>

            <footer v-if="carrinho.length || separadorAtual === 'envio'" class="sticky bottom-0 z-20 border-t border-linha bg-white shadow-[0_-6px_18px_rgba(22,32,28,.08)]">
                <div class="mx-auto flex max-w-xl flex-col gap-2 px-4 pb-5 pt-3">
                    <div class="text-[15px] font-semibold text-suave">Pedido vai direto para a cozinha/bar.</div>
                    <button
                        type="button"
                        class="h-[60px] rounded-xl bg-verde text-[19px] font-extrabold text-white hover:bg-verde-escuro disabled:cursor-not-allowed disabled:opacity-45"
                        :disabled="!carrinho.length || form.processing"
                        @click="enviarPedido"
                    >
                        {{ form.processing ? 'A enviar...' : textoEnviar }}
                    </button>
                </div>
            </footer>
        </template>
    </main>
</template>
