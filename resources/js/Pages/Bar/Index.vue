<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ pedidos: Array, caixas: Array });
const pontosPadrao = ['Cafe', 'Bar 1', 'Bar 2'];
const pontoBar = ref('');
let intervalo = null;
const contas = computed(() => (props.pedidos ?? []).filter((p) => p.tipo === 'bar_conta' && !['entregue', 'cancelado'].includes(p.estado)));
const prepagos = computed(() => (props.pedidos ?? []).filter((p) => p.tipo === 'bar_prepago'));
const totaisPorPonto = computed(() => Object.entries((props.pedidos ?? []).reduce((acc, pedido) => {
    const ponto = pedido.ponto_bar || 'Sem ponto definido';
    acc[ponto] = (acc[ponto] || 0) + Number(pedido.total ?? pedido.total_calculado ?? 0);
    return acc;
}, {})).map(([ponto, total]) => ({ ponto, total })));
const total = (pedido) => Number(pedido.total ?? pedido.total_calculado ?? 0).toFixed(2) + '€';
const euros = (valor) => Number(valor ?? 0).toFixed(2) + '€';
const hora = (data) => new Date(data).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
const caixaAberta = computed(() => (props.caixas ?? []).some((caixa) => caixa.ponto === pontoBar.value && caixa.estado === 'aberta'));
const caixasPorPonto = computed(() => Object.fromEntries((props.caixas ?? []).map((caixa) => [caixa.ponto, caixa])));
const pontoQuery = computed(() => pontoBar.value ? { ponto: pontoBar.value } : {});

watch(pontoBar, (valor) => localStorage.setItem('santana_ponto_bar', valor || ''));
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    pontoBar.value = params.get('ponto') || localStorage.getItem('santana_ponto_bar') || '';
    intervalo = setInterval(() => router.reload({ only: ['pedidos', 'caixas'], preserveScroll: true }), 20000);
});
onBeforeUnmount(() => clearInterval(intervalo));
</script>

<template>
    <main class="min-h-screen bg-gradient-to-br from-amber-50 via-orange-50 to-emerald-50 p-4 text-slate-950 sm:p-6">
        <header class="mb-6 rounded-3xl bg-white/85 p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h1 class="text-3xl font-black">Caixas - Senhas</h1><p class="text-sm font-semibold text-slate-500">Bebidas por senha impressa e contas dos balcões.</p></div>
                <div class="flex gap-2"><Link :href="route('caixa.index')" class="rounded-2xl bg-slate-900 px-5 py-4 font-black text-white">Voltar às Caixas</Link><Link :href="route('bar.nova-conta', pontoQuery)" class="rounded-2xl bg-blue-600 px-5 py-4 font-black text-white">Nova Conta</Link><Link :href="route('bar.prepago', pontoQuery)" class="rounded-2xl bg-emerald-600 px-5 py-4 font-black text-white">Pré-Pago</Link></div>
            </div>
            <div class="mt-4 grid gap-3 rounded-2xl bg-slate-50 p-3 md:grid-cols-[1fr_auto] md:items-end">
                <label class="block font-black">Nome deste ponto de venda
                    <input v-model="pontoBar" list="pontos-bar" class="mt-1 w-full rounded-xl border-slate-300 text-lg font-black" placeholder="Ex: Café Dia, Café Noite, Bar 1, Bar 2">
                </label>
                <div class="rounded-xl bg-white px-4 py-3 text-sm font-bold" :class="pontoBar && caixaAberta ? 'text-emerald-700' : 'text-amber-700'">
                    <span v-if="pontoBar && caixaAberta">Caixa aberta · fundo {{ euros(caixasPorPonto[pontoBar]?.fundo_maneio) }}</span>
                    <span v-else>Abre a caixa deste ponto antes de vender.</span>
                </div>
                <datalist id="pontos-bar"><option v-for="ponto in pontosPadrao" :key="ponto" :value="ponto" /></datalist>
            </div>
        </header>

        <section class="mb-5 rounded-3xl bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-xl font-black">Dinheiro por ponto hoje</h2>
            <div class="grid gap-3 md:grid-cols-4"><div v-for="linha in totaisPorPonto" :key="linha.ponto" class="rounded-2xl bg-amber-50 p-4"><div class="font-black">{{ linha.ponto }}</div><div class="text-2xl font-black text-amber-700">{{ euros(linha.total) }}</div></div></div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-3xl bg-white p-4 shadow-sm">
                <h2 class="mb-4 text-xl font-black">Contas Abertas</h2>
                <div v-if="!contas.length" class="rounded-2xl bg-slate-50 p-6 text-center font-semibold text-slate-500">Sem contas abertas.</div>
                <div v-for="pedido in contas" :key="pedido.id" class="mb-3 rounded-2xl border border-blue-100 bg-blue-50 p-4"><div class="flex items-center justify-between gap-3"><div><div class="text-lg font-black">Conta #{{ pedido.id }}</div><div class="text-sm text-slate-600">{{ hora(pedido.created_at) }} · {{ pedido.ponto_bar || 'Sem ponto' }} · {{ pedido.observacoes || 'Sem identificação' }}</div></div><div class="text-2xl font-black">{{ total(pedido) }}</div></div><Link :href="route('bar.show', pedido.id)" class="mt-3 block rounded-xl bg-blue-600 px-4 py-3 text-center font-black text-white">Ver Conta</Link></div>
            </section>

            <section class="rounded-3xl bg-white p-4 shadow-sm">
                <h2 class="mb-4 text-xl font-black">Pré-Pagos Hoje</h2>
                <div v-if="!prepagos.length" class="rounded-2xl bg-slate-50 p-6 text-center font-semibold text-slate-500">Sem pré-pagos emitidos.</div>
                <div v-for="pedido in prepagos" :key="pedido.id" class="mb-3 rounded-2xl border border-emerald-100 bg-emerald-50 p-4"><div class="flex items-center justify-between gap-3"><div><div class="text-2xl font-black">Senha #{{ pedido.numero_senha }}</div><div class="text-sm text-slate-600">{{ hora(pedido.created_at) }} · {{ pedido.ponto_bar || 'Sem ponto' }} · {{ pedido.estado }}</div></div><div class="text-2xl font-black">{{ total(pedido) }}</div></div><Link :href="route('bar.talao', pedido.id)" class="mt-3 block rounded-xl bg-emerald-600 px-4 py-3 text-center font-black text-white">Talão</Link></div>
            </section>
        </div>
    </main>
</template>
