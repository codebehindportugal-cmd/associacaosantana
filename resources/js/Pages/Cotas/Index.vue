<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ cotas: Object, totais: Object, socios: Array, filters: Object });
const form = useForm({ socio_id: props.socios?.[0]?.id ?? '', ano: new Date().getFullYear(), mes: new Date().getMonth() + 1, tipo: 'mensal', valor: 5, data_vencimento: new Date().toISOString().slice(0, 10), estado: 'pago', metodo_pagamento: 'dinheiro' });
</script>

<template>
    <AppLayout>
        <h1 class="mb-6 text-2xl font-bold">Cotas</h1>
        <div class="mb-6 grid gap-4 md:grid-cols-2"><div class="rounded-lg bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Cobrado</div><div class="text-3xl font-bold">{{ Number(totais?.cobrado ?? 0).toFixed(2) }}€</div></div><div class="rounded-lg bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Pendente</div><div class="text-3xl font-bold">{{ Number(totais?.pendente ?? 0).toFixed(2) }}€</div></div></div>
        <form class="mb-6 grid gap-3 rounded-lg bg-white p-5 shadow-sm md:grid-cols-4" @submit.prevent="form.post(route('cotas.store'))">
            <select v-model="form.socio_id" class="rounded-md border-slate-300"><option v-for="socio in socios" :value="socio.id">{{ socio.numero_socio }} · {{ socio.nome }}</option></select>
            <input v-model="form.ano" type="number" class="rounded-md border-slate-300">
            <input v-model="form.mes" type="number" class="rounded-md border-slate-300">
            <input v-model="form.valor" type="number" step="0.01" class="rounded-md border-slate-300">
            <input v-model="form.data_vencimento" type="date" class="rounded-md border-slate-300">
            <select v-model="form.estado" class="rounded-md border-slate-300"><option>pago</option><option>pendente</option><option>em_atraso</option></select>
            <button class="rounded-md bg-slate-900 px-4 py-2 text-white">Registar</button>
        </form>
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm"><table class="w-full min-w-[400px] text-left text-sm"><tbody><tr v-for="cota in cotas.data" :key="cota.id" class="border-t"><td class="p-3">{{ cota.socio?.nome }}</td><td>{{ cota.ano }}/{{ cota.mes }}</td><td>{{ cota.valor }}€</td><td>{{ cota.estado }}</td></tr></tbody></table></div>
    </AppLayout>
</template>
