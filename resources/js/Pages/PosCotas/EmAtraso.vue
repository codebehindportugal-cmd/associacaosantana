<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ socios: Array });
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const total = computed(() => (props.socios ?? []).reduce((s, socio) => s + Number(socio.valor_em_divida ?? 0), 0));
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-5 flex items-center justify-between"><h1 class="text-3xl font-black">⚠️ SÓCIOS COM COTAS EM ATRASO</h1><Link :href="route('pos.cotas.index')" class="rounded-lg bg-gray-800 px-4 py-3 font-black">← VOLTAR</Link></header>
        <div class="mb-5 rounded-lg bg-red-700 p-5 text-3xl font-black">{{ euros(total) }}</div>
        <div class="grid gap-3"><div v-for="socio in socios" :key="socio.id" class="grid gap-3 rounded-lg bg-gray-800 p-4 md:grid-cols-[1fr_auto_auto_auto] md:items-center"><div><div class="text-xl font-black">{{ socio.nome }}</div><div class="font-bold text-gray-300">N.º {{ socio.numero_socio }}</div></div><div class="font-black">{{ socio.meses_em_atraso }} meses</div><div class="font-mono text-xl font-black text-red-400">{{ euros(socio.valor_em_divida) }}</div><Link :href="route('pos.cotas.socio', socio.id)" class="rounded bg-emerald-600 px-5 py-3 text-center font-black">COBRAR</Link></div></div>
    </main>
</template>
