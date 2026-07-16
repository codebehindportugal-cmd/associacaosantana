<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({ produtos: Array });
const pontosPadrao = ['Cafe', 'Bar 1', 'Bar 2'];
const carrinho = ref([]);
const pontoBar = ref('');
const form = useForm({ observacoes: '', items: [], ponto_bar: '' });
const porCategoria = computed(() => (props.produtos ?? []).reduce((acc, p) => { const k = p.categoria?.nome ?? 'Outros'; if (!acc[k]) acc[k] = []; acc[k].push(p); return acc; }, {}));
const total = computed(() => carrinho.value.reduce((s, i) => s + Number(i.preco) * i.quantidade, 0));
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const add = (produto) => { const item = carrinho.value.find((i) => i.produto_id === produto.id); item ? item.quantidade++ : carrinho.value.push({ produto_id: produto.id, nome: produto.nome, preco: produto.preco, quantidade: 1 }); };
const inc = (item, delta) => { item.quantidade += delta; carrinho.value = carrinho.value.filter((i) => i.quantidade > 0); };
const guardarPonto = () => localStorage.setItem('santana_ponto_bar', pontoBar.value || '');
const abrir = () => { guardarPonto(); form.ponto_bar = pontoBar.value; form.items = carrinho.value.map(({ produto_id, quantidade }) => ({ produto_id, quantidade })); form.post(route('bar.store-conta')); };
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    pontoBar.value = params.get('ponto') || localStorage.getItem('santana_ponto_bar') || '';
});
</script>

<template>
    <main class="min-h-screen bg-blue-50 p-4 text-slate-950 sm:p-6">
        <header class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-white p-4 shadow-sm">
            <div>
                <h1 class="text-3xl font-black">Nova Conta Bar</h1>
                <p class="font-semibold text-slate-500">Modo conta: paga no final.</p>
                <label class="mt-3 block text-sm font-black text-slate-700">Ponto de venda
                    <input v-model="pontoBar" list="pontos-bar-conta" class="mt-1 w-full rounded-xl border-slate-300 font-black" placeholder="Cafe, Bar 1, Bar 2..." @change="guardarPonto">
                </label>
                <datalist id="pontos-bar-conta"><option v-for="ponto in pontosPadrao" :key="ponto" :value="ponto" /></datalist>
            </div>
            <Link :href="route('bar.index', pontoBar ? { ponto: pontoBar } : {})" class="rounded-xl border px-4 py-3 font-black">Voltar</Link>
        </header>
        <div class="grid gap-5 lg:grid-cols-[1fr_420px]">
            <section class="space-y-5"><div v-for="(lista, categoria) in porCategoria" :key="categoria" class="rounded-3xl bg-white p-4 shadow-sm"><h2 class="mb-3 text-xl font-black">{{ categoria }}</h2><div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4"><button v-for="produto in lista" :key="produto.id" type="button" class="min-h-24 rounded-2xl border border-blue-100 bg-blue-50 p-3 text-left font-black" @click="add(produto)"><span class="block">{{ produto.nome }}</span><span class="mt-2 block text-blue-700">{{ euros(produto.preco) }}</span></button></div></div></section>
            <form class="sticky bottom-3 self-start rounded-3xl bg-white p-4 shadow-xl" @submit.prevent="abrir"><h2 class="mb-3 text-xl font-black">Conta</h2><div v-if="!pontoBar" class="mb-3 rounded-2xl bg-red-50 p-3 font-bold text-red-700">Define o ponto de venda antes de abrir conta.</div><div v-if="form.errors.ponto_bar" class="mb-3 rounded-2xl bg-red-50 p-3 font-bold text-red-700">{{ form.errors.ponto_bar }}</div><label class="block font-bold">Nome/identificação<input v-model="form.observacoes" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Mesa 3, João, balcão..."></label><div class="my-4 divide-y rounded-2xl bg-slate-50"><div v-for="item in carrinho" :key="item.produto_id" class="p-3"><div class="font-black">{{ item.nome }}</div><div class="mt-2 flex items-center justify-between"><div class="flex items-center gap-2"><button type="button" class="h-11 w-11 rounded-xl bg-slate-200 font-black" @click="inc(item, -1)">-</button><strong>{{ item.quantidade }}</strong><button type="button" class="h-11 w-11 rounded-xl bg-slate-900 font-black text-white" @click="inc(item, 1)">+</button></div><strong>{{ euros(item.preco * item.quantidade) }}</strong></div></div></div><div class="rounded-2xl bg-blue-600 p-4 text-white"><div class="text-sm font-bold">Total atual</div><div class="text-4xl font-black">{{ euros(total) }}</div></div><button class="mt-4 w-full rounded-2xl bg-blue-600 p-5 text-lg font-black text-white disabled:opacity-50" :disabled="!carrinho.length || !pontoBar || form.processing">ABRIR CONTA</button></form>
        </div>
    </main>
</template>
