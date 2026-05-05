<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    posNome: String,
    pontoBar: String,
    caixaAberta: Boolean,
    produtos: Array,
    senhasHoje: Array,
});

const agora = ref(new Date());
const carrinho = ref([]);
const recebido = ref('');
const form = useForm({ items: [], valor_recebido: 0 });
let relogio = null;
let refresh = null;

const total = computed(() => carrinho.value.reduce((soma, item) => soma + Number(item.preco) * item.quantidade, 0));
const troco = computed(() => Math.max(0, Number(recebido.value || 0) - total.value));
const euros = (valor) => Number(valor ?? 0).toFixed(2) + '€';
const hora = (data) => new Date(data).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
const logout = () => router.post(route('pos.logout'));

const adicionar = (produto) => {
    const item = carrinho.value.find((linha) => linha.produto_id === produto.id);
    item ? item.quantidade++ : carrinho.value.push({ produto_id: produto.id, nome: produto.nome, preco: produto.preco, quantidade: 1 });
};

const alterar = (item, delta) => {
    item.quantidade += delta;
    carrinho.value = carrinho.value.filter((linha) => linha.quantidade > 0);
};

const cobrar = () => {
    form.items = carrinho.value.map(({ produto_id, quantidade }) => ({ produto_id, quantidade }));
    form.valor_recebido = recebido.value || total.value;
    form.post(route('pos.prepago.store'));
};

onMounted(() => {
    relogio = setInterval(() => (agora.value = new Date()), 1000);
    refresh = setInterval(() => router.reload({ only: ['caixaAberta', 'senhasHoje'], preserveScroll: true }), 20000);
});

onBeforeUnmount(() => {
    clearInterval(relogio);
    clearInterval(refresh);
});
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-4 text-white">
        <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-3xl font-black">POS {{ pontoBar }}</h1>
                <p class="font-bold text-gray-300">{{ posNome }} · {{ agora.toLocaleTimeString('pt-PT') }}</p>
            </div>
            <button class="rounded-lg bg-red-600 px-5 py-3 font-black" @click="logout">LOGOUT</button>
        </header>

        <div v-if="!caixaAberta" class="mb-5 rounded-lg bg-red-700 p-5 text-center text-xl font-black">
            Caixa fechada para {{ pontoBar }}. Abre a caixa no backoffice antes de vender.
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_420px]">
            <section class="rounded-lg bg-gray-800 p-4">
                <h2 class="mb-4 text-2xl font-black">BEBIDAS</h2>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                    <button v-for="produto in produtos" :key="produto.id" class="min-h-28 rounded-lg bg-blue-600 p-4 text-left font-black disabled:opacity-50" :disabled="!caixaAberta" @click="adicionar(produto)">
                        <span class="block text-lg">{{ produto.nome }}</span>
                        <span class="mt-2 block text-2xl">{{ euros(produto.preco) }}</span>
                    </button>
                </div>
            </section>

            <aside class="rounded-lg bg-gray-800 p-4">
                <h2 class="mb-3 text-2xl font-black">SENHA</h2>
                <div v-if="!carrinho.length" class="rounded-lg bg-gray-900 p-6 text-center font-bold text-gray-300">Escolhe bebidas. Ex.: 2 Imperiais.</div>
                <div v-for="item in carrinho" :key="item.produto_id" class="mb-3 rounded-lg bg-gray-900 p-3">
                    <div class="font-black">{{ item.nome }}</div>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <button class="h-12 w-12 rounded bg-gray-700 text-xl font-black" @click="alterar(item, -1)">-</button>
                            <strong class="text-xl">{{ item.quantidade }}</strong>
                            <button class="h-12 w-12 rounded bg-emerald-600 text-xl font-black" @click="alterar(item, 1)">+</button>
                        </div>
                        <strong class="font-mono text-xl">{{ euros(item.preco * item.quantidade) }}</strong>
                    </div>
                </div>
                <div class="my-4 rounded-lg bg-emerald-700 p-4">
                    <div class="font-bold">Total</div>
                    <div class="text-4xl font-black">{{ euros(total) }}</div>
                </div>
                <input v-model="recebido" inputmode="decimal" class="w-full rounded-lg border-gray-700 bg-gray-900 p-4 text-2xl font-black text-white" placeholder="Recebido">
                <div class="mt-3 text-xl font-black text-emerald-400">Troco: {{ euros(troco) }}</div>
                <div v-if="form.errors.ponto_bar || form.errors.valor_recebido" class="mt-3 rounded bg-red-700 p-3 font-bold">{{ form.errors.ponto_bar || form.errors.valor_recebido }}</div>
                <button class="mt-4 w-full rounded-lg bg-emerald-600 p-5 text-xl font-black disabled:opacity-50" :disabled="!caixaAberta || !carrinho.length || form.processing" @click="cobrar">
                    COBRAR E TIRAR SENHA
                </button>
            </aside>
        </div>

        <section class="mt-5 rounded-lg bg-gray-800 p-4">
            <h2 class="mb-3 text-xl font-black">ÚLTIMAS SENHAS</h2>
            <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-4">
                <div v-for="pedido in senhasHoje" :key="pedido.id" class="rounded-lg bg-gray-900 p-3">
                    <div class="text-2xl font-black">#{{ pedido.numero_senha }}</div>
                    <div class="text-sm text-gray-400">{{ hora(pedido.created_at) }}</div>
                    <div class="mt-2 text-sm">{{ pedido.items.map((item) => `${item.quantidade}x ${item.produto?.nome}`).join(', ') }}</div>
                </div>
            </div>
        </section>
    </main>
</template>
