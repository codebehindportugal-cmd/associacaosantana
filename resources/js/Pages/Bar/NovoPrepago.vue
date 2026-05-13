<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({ produtos: Array });
const pontosPadrao = ['Cafe', 'Bar 1', 'Bar 2'];
const carrinho = ref([]);
const valorRecebido = ref('');
const trocoEntregue = ref('');
const pontoBar = ref('');
const form = useForm({ items: [], valor_recebido: 0, troco: 0, ponto_bar: '' });
const porCategoria = computed(() => Object.groupBy(props.produtos ?? [], (p) => p.categoria?.nome ?? 'Outros'));
const total = computed(() => carrinho.value.reduce((s, i) => s + Number(i.preco) * i.quantidade, 0));
const troco = computed(() => Math.max(0, Number(valorRecebido.value || 0) - total.value));
const trocoRegistado = computed(() => trocoEntregue.value === '' ? troco.value : Number(trocoEntregue.value || 0));
const doacao = computed(() => Math.max(0, troco.value - trocoRegistado.value));
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const add = (produto) => { const item = carrinho.value.find((i) => i.produto_id === produto.id); item ? item.quantidade++ : carrinho.value.push({ produto_id: produto.id, nome: produto.nome, preco: produto.preco, quantidade: 1 }); };
const inc = (item, delta) => { item.quantidade += delta; carrinho.value = carrinho.value.filter((i) => i.quantidade > 0); };
const guardarPonto = () => localStorage.setItem('santana_ponto_bar', pontoBar.value || '');
const cobrar = () => {
    guardarPonto();
    form.ponto_bar = pontoBar.value;
    form.items = carrinho.value.map(({ produto_id, quantidade }) => ({ produto_id, quantidade }));
    form.valor_recebido = valorRecebido.value || total.value;
    form.troco = trocoRegistado.value;
    form.post(route('bar.store-prepago'));
};
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    pontoBar.value = params.get('ponto') || localStorage.getItem('santana_ponto_bar') || '';
});
</script>

<template>
    <main class="min-h-screen bg-emerald-50 p-4 text-slate-950 sm:p-6">
        <header class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-3xl bg-white p-4 shadow-sm">
            <div>
                <h1 class="text-3xl font-black">Pré-Pagamento</h1>
                <p class="font-bold text-emerald-700">O cliente paga ANTES de levantar.</p>
                <label class="mt-3 block text-sm font-black text-slate-700">Ponto de venda
                    <input v-model="pontoBar" list="pontos-bar-prepago" class="mt-1 w-full rounded-xl border-slate-300 font-black" placeholder="Cafe, Bar 1, Bar 2..." @change="guardarPonto">
                </label>
                <datalist id="pontos-bar-prepago"><option v-for="ponto in pontosPadrao" :key="ponto" :value="ponto" /></datalist>
            </div>
            <Link :href="route('bar.index', pontoBar ? { ponto: pontoBar } : {})" class="rounded-xl border px-4 py-3 font-black">Voltar</Link>
        </header>
        <div class="grid gap-5 lg:grid-cols-[1fr_420px]">
            <section class="space-y-5">
                <div v-for="(lista, categoria) in porCategoria" :key="categoria" class="rounded-3xl bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-xl font-black">{{ categoria }}</h2>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                        <button v-for="produto in lista" :key="produto.id" type="button" class="min-h-24 rounded-2xl border border-emerald-100 bg-emerald-50 p-3 text-left font-black" @click="add(produto)"><span class="block">{{ produto.nome }}</span><span class="mt-2 block text-emerald-700">{{ euros(produto.preco) }}</span></button>
                    </div>
                </div>
            </section>
            <form class="sticky bottom-3 self-start rounded-3xl bg-white p-4 shadow-xl" @submit.prevent="cobrar">
                <h2 class="mb-3 text-xl font-black">Carrinho</h2>
                <div v-if="!pontoBar" class="mb-3 rounded-2xl bg-red-50 p-3 font-bold text-red-700">Define o ponto de venda antes de cobrar.</div>
                <div v-if="form.errors.ponto_bar" class="mb-3 rounded-2xl bg-red-50 p-3 font-bold text-red-700">{{ form.errors.ponto_bar }}</div>
                <div v-if="!carrinho.length" class="rounded-2xl bg-slate-50 p-6 text-center font-semibold text-slate-500">Escolhe produtos.</div>
                <div v-for="item in carrinho" :key="item.produto_id" class="mb-3 rounded-2xl bg-slate-50 p-3"><div class="font-black">{{ item.nome }}</div><div class="mt-2 flex items-center justify-between"><div class="flex items-center gap-2"><button type="button" class="h-11 w-11 rounded-xl bg-slate-200 font-black" @click="inc(item, -1)">-</button><strong>{{ item.quantidade }}</strong><button type="button" class="h-11 w-11 rounded-xl bg-slate-900 font-black text-white" @click="inc(item, 1)">+</button></div><strong>{{ euros(item.preco * item.quantidade) }}</strong></div></div>
                <div class="my-4 rounded-2xl bg-emerald-600 p-4 text-white"><div class="text-sm font-bold">Total</div><div class="text-4xl font-black">{{ euros(total) }}</div></div>
                <label class="block font-bold">Valor recebido<input v-model.number="valorRecebido" type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border-slate-300 text-xl font-black"></label>
                <label class="mt-3 block font-bold">Troco entregue<input v-model.number="trocoEntregue" type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border-slate-300 text-xl font-black" :placeholder="euros(troco)"></label>
                <div class="mt-3 grid grid-cols-2 gap-2 text-lg font-black"><div class="rounded-2xl bg-emerald-50 p-3 text-emerald-700">Troco: {{ euros(trocoRegistado) }}</div><div class="rounded-2xl bg-amber-50 p-3 text-amber-700">Doação: {{ euros(doacao) }}</div></div>
                <button type="button" class="mt-3 w-full rounded-2xl bg-amber-400 p-3 font-black text-amber-950" @click="trocoEntregue = 0">CLIENTE DOA O TROCO</button>
                <div v-if="form.errors.valor_recebido || form.errors.troco" class="mt-3 rounded-2xl bg-red-50 p-3 font-bold text-red-700">{{ form.errors.valor_recebido || form.errors.troco }}</div>
                <button class="mt-4 w-full rounded-2xl bg-emerald-600 p-5 text-lg font-black text-white disabled:opacity-50" :disabled="!carrinho.length || !pontoBar || form.processing">COBRAR E IMPRIMIR SENHA</button>
            </form>
        </div>
    </main>
</template>
