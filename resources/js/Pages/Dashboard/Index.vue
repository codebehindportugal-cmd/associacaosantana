<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
defineProps({ totais: Object });
const euros = (valor) => `${Number(valor ?? 0).toFixed(2)}€`;
</script>

<template>
    <AppLayout>
        <h1 class="mb-6 text-2xl font-bold">Dashboard</h1>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
            <div class="rounded-lg bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Mesas livres</div><div class="mt-2 text-3xl font-bold">{{ totais.mesas_livres }}</div></div>
            <div class="rounded-lg bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Pedidos ativos</div><div class="mt-2 text-3xl font-bold">{{ totais.pedidos_ativos }}</div></div>
            <div class="rounded-lg bg-red-50 p-5 shadow-sm"><div class="text-sm text-red-600">Sócios em atraso</div><div class="mt-2 text-3xl font-bold text-red-700">{{ totais.socios_em_atraso }}</div></div>
            <div class="rounded-lg bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Receita do dia</div><div class="mt-2 text-3xl font-bold">{{ euros(totais.receita_dia) }}</div></div>
            <div class="rounded-lg bg-emerald-50 p-5 shadow-sm"><div class="text-sm text-emerald-700">Bar Hoje</div><div class="mt-2 text-3xl font-bold text-emerald-800">{{ euros(totais.bar_hoje) }}</div></div>
        </div>
        <Link v-if="totais.socios_em_atraso" :href="route('socios.emAtraso')" class="mt-6 block rounded-md border border-red-200 bg-red-50 p-4 font-semibold text-red-700">Há sócios com cotas em atraso. Ver lista</Link>
    </AppLayout>
</template>
