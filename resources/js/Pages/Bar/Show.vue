<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ pedido: Object, produtos: Array });
const mostrarProdutos = ref(true);
const valorRecebido = ref('');
const troco = ref('');
const itemForm = useForm({ pedido_id: props.pedido.id, produto_id: '', quantidade: 1 });
const fecharForm = useForm({ valor_recebido: '', troco: 0 });
const porCategoria = computed(() => Object.groupBy(props.produtos ?? [], (p) => p.categoria?.nome ?? 'Outros'));
const total = computed(() => Number(props.pedido.total_calculado ?? props.pedido.total ?? 0));
const trocoCalc = computed(() => Math.max(0, Number(valorRecebido.value || total.value) - total.value));
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const add = (produto) => { itemForm.produto_id = produto.id; itemForm.post(route('pedido-items.store'), { preserveScroll: true }); };
const fechar = () => { fecharForm.valor_recebido = valorRecebido.value || total.value; fecharForm.troco = troco.value === '' ? trocoCalc.value : troco.value; fecharForm.patch(route('bar.fechar', props.pedido.id)); };
</script>

<template>
    <main class="min-h-screen bg-blue-50 p-4 text-slate-950 sm:p-6">
        <header class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-3xl bg-white p-4 shadow-sm"><div><h1 class="text-3xl font-black">Conta Bar #{{ pedido.id }}</h1><p class="font-semibold text-slate-500">{{ pedido.observacoes || 'Sem identificação' }} · {{ pedido.estado }}</p></div><Link :href="route('bar.index')" class="rounded-xl border px-4 py-3 font-black">Voltar</Link></header>
        <div class="grid gap-5 lg:grid-cols-[1fr_420px]">
            <section class="rounded-3xl bg-white p-4 shadow-sm"><h2 class="mb-3 text-xl font-black">Itens já adicionados</h2><div class="divide-y rounded-2xl bg-slate-50"><div v-for="item in pedido.items" :key="item.id" class="flex items-center justify-between p-3"><div><strong>{{ item.quantidade }}x {{ item.produto?.nome }}</strong><div class="text-sm text-slate-500">{{ item.produto?.categoria?.nome }}</div></div><strong>{{ euros(item.quantidade * item.preco_unitario) }}</strong></div></div><button class="mt-4 w-full rounded-2xl bg-slate-900 p-4 font-black text-white" @click="mostrarProdutos = !mostrarProdutos">Adicionar Itens</button><div v-if="mostrarProdutos" class="mt-5 space-y-5"><div v-for="(lista, categoria) in porCategoria" :key="categoria"><h3 class="mb-2 font-black">{{ categoria }}</h3><div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4"><button v-for="produto in lista" :key="produto.id" type="button" class="min-h-24 rounded-2xl border border-blue-100 bg-blue-50 p-3 text-left font-black" @click="add(produto)">{{ produto.nome }}<span class="mt-2 block text-blue-700">{{ euros(produto.preco) }}</span></button></div></div></div></section>
            <form class="sticky bottom-3 self-start rounded-3xl bg-white p-4 shadow-xl" @submit.prevent="fechar"><div class="rounded-2xl bg-blue-600 p-4 text-white"><div class="text-sm font-bold">Total atual</div><div class="text-4xl font-black">{{ euros(total) }}</div></div><label class="mt-4 block font-bold">Valor recebido<input v-model.number="valorRecebido" type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border-slate-300 text-xl font-black"></label><label class="mt-3 block font-bold">Troco entregue<input v-model.number="troco" type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border-slate-300 text-xl font-black" :placeholder="euros(trocoCalc)"></label><button class="mt-4 w-full rounded-2xl bg-blue-600 p-5 text-lg font-black text-white">FECHAR E IMPRIMIR</button></form>
        </div>
    </main>
</template>
