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
    mostrarAviso('Produto registado. No fim, abre "Validar e enviar" para enviar o pedido ao funcionário.');
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
        onSuccess: () => mostrarAviso('Funcionário chamado! Aguarde um momento. 🔔'),
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
    <main class="min-h-screen bg-slate-950 text-white">
        <header class="sticky top-0 z-20 border-b border-white/10 bg-slate-950/95 px-4 py-4 backdrop-blur">
            <div class="mx-auto flex max-w-xl items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-black uppercase tracking-wide text-emerald-300">ARDC Santana</div>
                    <h1 class="text-2xl font-black">Mesa {{ pedido.mesa }}</h1>
                </div>
                <div class="flex gap-2">
                    <button
                        v-if="pedido.disponivel"
                        type="button"
                        class="rounded-full px-3 py-2 text-xs font-black disabled:opacity-50"
                        :class="chamarForm.processing ? 'bg-amber-600' : 'bg-amber-500 text-slate-950'"
                        :disabled="chamarForm.processing"
                        @click="chamarFuncionario"
                    >
                        🔔 Chamar
                    </button>
                    <button type="button" class="rounded-full bg-white/10 px-3 py-2 text-xs font-black" @click="separadorAtual = 'enviados'">Enviados</button>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-xl px-4 py-5">
            <div v-if="!pedido.disponivel" class="rounded-2xl border border-amber-400/40 bg-amber-400/10 p-5 text-center">
                <h2 class="text-xl font-black">Pedido indisponível</h2>
                <p class="mt-2 text-sm text-amber-100">Este pedido já foi fechado ou cancelado. Chame um elemento da equipa.</p>
            </div>

            <template v-else>
                <div class="mb-4 overflow-x-auto rounded-2xl bg-white/10 p-1">
                    <div class="flex min-w-max gap-2">
                    <button
                        type="button"
                        class="min-h-12 shrink-0 rounded-xl px-4 py-3 text-sm font-black"
                        :class="separadorAtual === 'produtos' ? 'bg-white text-slate-950' : 'text-white'"
                        @click="separadorAtual = 'produtos'"
                    >
                        Produtos
                    </button>
                    <button
                        type="button"
                        class="min-h-12 shrink-0 rounded-xl px-4 py-3 text-sm font-black"
                        :class="separadorAtual === 'envio' ? 'bg-white text-slate-950' : 'text-white'"
                        @click="separadorAtual = 'envio'"
                    >
                        Validar e enviar
                        <span v-if="totalItens" class="ml-1 rounded-full bg-emerald-500 px-2 py-0.5 text-xs text-slate-950">{{ totalItens }}</span>
                    </button>
                    <button
                        type="button"
                        class="min-h-12 shrink-0 rounded-xl px-4 py-3 text-sm font-black"
                        :class="separadorAtual === 'enviados' ? 'bg-white text-slate-950' : 'text-white'"
                        @click="separadorAtual = 'enviados'"
                    >
                        Enviados
                        <span v-if="totalEnviados" class="ml-1 rounded-full bg-sky-400 px-2 py-0.5 text-xs text-slate-950">{{ totalEnviados }}</span>
                    </button>
                    </div>
                </div>

                <div v-if="form.errors.pedido" class="mb-4 rounded-xl bg-red-600 p-3 text-sm font-bold">
                    {{ form.errors.pedido }}
                </div>
                <div v-if="form.errors.items" class="mb-4 rounded-xl bg-red-600 p-3 text-sm font-bold">
                    Não foi possível enviar esse pedido.
                </div>
                <div v-if="aviso" class="mb-4 rounded-xl border border-emerald-400/40 bg-emerald-400/15 p-3 text-sm font-black text-emerald-100">
                    {{ aviso }}
                </div>
                <div v-if="avisoFlash" class="mb-4 rounded-xl border border-amber-400/40 bg-amber-400/15 p-3 text-sm font-black text-amber-100">
                    {{ avisoFlash }}
                </div>

                <div v-if="separadorAtual === 'produtos'" class="mb-4 overflow-x-auto pb-2">
                    <div class="flex min-w-max gap-2">
                        <button
                            v-for="categoria in categorias"
                            :key="categoria"
                            type="button"
                            class="min-h-11 shrink-0 rounded-full px-4 py-2 text-sm font-black"
                            :class="categoria === categoriaAtual ? 'bg-emerald-500 text-slate-950' : 'bg-white/10 text-white'"
                            @click="categoriaAtual = categoria"
                        >
                            {{ categoria }}
                        </button>
                    </div>
                </div>

                <div v-if="separadorAtual === 'produtos'" class="grid gap-3">
                    <article v-for="produto in lista" :key="produto.id" class="rounded-2xl bg-white p-4 text-slate-950 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-lg font-black">{{ produto.nome }}</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">{{ produto.categoria?.nome || 'Produto' }}</p>
                        </div>

                        <label class="mb-4 block">
                            <span class="text-xs font-black uppercase text-slate-500">Observações</span>
                            <textarea
                                v-model="observacoes[produto.id]"
                                rows="2"
                                maxlength="255"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm"
                                placeholder="Ex.: sem cebola, bem passado"
                            ></textarea>
                        </label>

                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center overflow-hidden rounded-full border border-slate-200">
                                <button type="button" class="h-11 w-12 bg-slate-100 text-xl font-black" @click="alterarQuantidade(produto, -1)">-</button>
                                <span class="w-12 text-center text-lg font-black">{{ quantidade(produto) }}</span>
                                <button type="button" class="h-11 w-12 bg-slate-100 text-xl font-black" @click="alterarQuantidade(produto, 1)">+</button>
                            </div>
                            <button
                                type="button"
                                class="min-h-11 flex-1 rounded-full bg-emerald-500 px-4 py-3 text-sm font-black text-slate-950 disabled:opacity-60"
                                @click="adicionar(produto)"
                            >
                                Adicionar à lista
                            </button>
                        </div>
                    </article>
                </div>

                <div v-else-if="separadorAtual === 'envio'" class="rounded-2xl bg-white p-4 text-slate-950 shadow-sm">
                    <div class="mb-4">
                        <h2 class="text-xl font-black">Confirmar pedido</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Revê as escolhas antes de enviar para a equipa.</p>
                    </div>

                    <div v-if="!carrinho.length" class="rounded-2xl bg-slate-100 p-5 text-center text-sm font-bold text-slate-500">
                        Ainda não escolheste produtos.
                        <button type="button" class="mt-3 block w-full rounded-xl bg-slate-950 px-4 py-3 font-black text-white" @click="separadorAtual = 'produtos'">
                            Escolher produtos
                        </button>
                    </div>

                    <div v-else class="grid gap-3">
                        <article v-for="(item, index) in carrinho" :key="`${item.produto_id}-${index}`" class="rounded-2xl bg-slate-100 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-black">{{ item.nome }}</h3>
                                    <p v-if="item.observacoes" class="mt-1 text-sm font-semibold text-slate-600">{{ item.observacoes }}</p>
                                </div>
                                <button type="button" class="rounded-full bg-red-600 px-3 py-2 text-sm font-black text-white" @click="alterarQuantidadeCarrinho(item, -item.quantidade)">
                                    Remover
                                </button>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <div class="flex items-center overflow-hidden rounded-full border border-slate-200 bg-white">
                                    <button type="button" class="h-10 w-11 bg-slate-100 text-xl font-black" @click="alterarQuantidadeCarrinho(item, -1)">-</button>
                                    <span class="w-12 text-center text-lg font-black">{{ item.quantidade }}</span>
                                    <button type="button" class="h-10 w-11 bg-slate-100 text-xl font-black" @click="alterarQuantidadeCarrinho(item, 1)">+</button>
                                </div>
                                <span class="rounded-full bg-slate-950 px-3 py-1 text-sm font-black text-white">{{ item.quantidade }}x</span>
                            </div>
                        </article>

                        <button
                            type="button"
                            class="rounded-2xl bg-emerald-500 px-5 py-4 text-lg font-black text-slate-950 disabled:opacity-50"
                            :disabled="!carrinho.length || form.processing"
                            @click="enviarPedido"
                        >
                            {{ form.processing ? 'A enviar...' : 'Enviar pedido' }}
                        </button>
                    </div>
                </div>

                <div v-else class="rounded-2xl bg-white p-4 text-slate-950 shadow-sm">
                    <div class="mb-4">
                        <h2 class="text-xl font-black">Produtos enviados</h2>
                        <p class="mt-1 text-sm font-semibold text-slate-500">Aqui aparecem os produtos que já foram enviados para a equipa.</p>
                    </div>

                    <div v-if="!itemsEnviados?.length" class="rounded-2xl bg-slate-100 p-5 text-center text-sm font-bold text-slate-500">
                        Ainda não foram enviados produtos.
                        <button type="button" class="mt-3 block w-full rounded-xl bg-slate-950 px-4 py-3 font-black text-white" @click="separadorAtual = 'produtos'">
                            Escolher produtos
                        </button>
                    </div>

                    <div v-else class="grid gap-3">
                        <article v-for="item in itemsEnviados" :key="item.id" class="rounded-2xl bg-slate-100 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-black">{{ item.nome }}</h3>
                                    <p class="mt-1 text-xs font-semibold uppercase text-slate-500">
                                        {{ item.hora || 'Enviado' }}
                                        <span v-if="item.estado"> · {{ item.estado }}</span>
                                    </p>
                                    <p v-if="item.observacoes" class="mt-1 text-sm font-semibold text-slate-700">{{ item.observacoes }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-slate-950 px-3 py-1 text-sm font-black text-white">{{ item.quantidade }}x</span>
                            </div>
                        </article>
                    </div>
                </div>
            </template>
        </section>
    </main>
</template>
