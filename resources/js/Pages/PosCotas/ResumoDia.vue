<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ cotas: Array });
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const total = computed(() => (props.cotas ?? []).reduce((s, c) => s + Number(c.valor ?? 0), 0));
const porMetodo = computed(() => (props.cotas ?? []).reduce((acc, c) => { acc[c.metodo_pagamento || 'outro'] = (acc[c.metodo_pagamento || 'outro'] || 0) + Number(c.valor); return acc; }, {}));
const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-5 flex items-center justify-between"><h1 class="text-3xl font-black">📊 RESUMO DO DIA - {{ new Date().toLocaleDateString('pt-PT') }}</h1><Link :href="route('pos.cotas.index')" class="rounded-lg bg-gray-800 px-4 py-3 font-black print:hidden">← VOLTAR</Link></header>
        <section class="mb-5 grid gap-3 md:grid-cols-4"><div class="rounded bg-emerald-700 p-4"><div>Total cobrado</div><strong class="text-3xl">{{ euros(total) }}</strong></div><div class="rounded bg-blue-700 p-4"><div>N.º cotas</div><strong class="text-3xl">{{ cotas.length }}</strong></div><div v-for="(valor, metodo) in porMetodo" :key="metodo" class="rounded bg-gray-700 p-4"><div class="uppercase">{{ metodo }}</div><strong class="text-2xl">{{ euros(valor) }}</strong></div></section>
        <table class="w-full rounded bg-gray-800 text-left"><thead><tr class="border-b border-gray-700"><th class="p-3">Hora</th><th>Sócio</th><th>Período</th><th>Valor</th><th>Método</th></tr></thead><tbody><tr v-for="cota in cotas" :key="cota.id" class="border-b border-gray-700"><td class="p-3">{{ new Date(cota.updated_at).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' }) }}</td><td>{{ cota.socio?.nome }}</td><td>{{ cota.tipo === 'anual' ? 'Anual' : meses[cota.mes - 1] }} {{ cota.ano }}</td><td>{{ euros(cota.valor) }}</td><td>{{ cota.metodo_pagamento }}</td></tr></tbody></table>
        <button class="mt-4 rounded bg-gray-700 px-5 py-3 font-black print:hidden" @click="window.print()">🖨️ IMPRIMIR RESUMO</button>
    </main>
</template>
