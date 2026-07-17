<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
const props = defineProps({ cotas: Object, totais: Object, socios: Array, filters: Object });
const form = useForm({ socio_id: props.socios?.[0]?.id ?? '', ano: new Date().getFullYear(), mes: new Date().getMonth() + 1, tipo: 'mensal', valor: 5, data_vencimento: new Date().toISOString().slice(0, 10), estado: 'pago', metodo_pagamento: 'dinheiro' });

const anoAtual = new Date().getFullYear();
const anos = [anoAtual + 1, anoAtual, anoAtual - 1, anoAtual - 2];
const filtros = reactive({
    ano: props.filters?.ano ?? '',
    mes: props.filters?.mes ?? '',
    estado: props.filters?.estado ?? '',
});
const filtrar = () => router.get(route('cotas.index'), {
    ano: filtros.ano || undefined,
    mes: filtros.mes || undefined,
    estado: filtros.estado || undefined,
}, { preserveState: true, preserveScroll: true, replace: true });
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
            <button class="rounded-md bg-slate-900 px-4 py-2 text-white" :disabled="form.processing">{{ form.processing ? 'A registar...' : 'Registar' }}</button>
            <div v-if="Object.keys(form.errors).length" class="col-span-full rounded-md bg-red-50 p-3 text-sm text-red-700">
                <div v-for="(erro, campo) in form.errors" :key="campo">{{ erro }}</div>
            </div>
        </form>
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <select v-model="filtros.ano" class="rounded-md border-slate-300 text-sm" @change="filtrar"><option value="">Todos os anos</option><option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option></select>
            <select v-model="filtros.mes" class="rounded-md border-slate-300 text-sm" @change="filtrar"><option value="">Todos os meses</option><option v-for="mes in 12" :key="mes" :value="mes">{{ mes }}</option></select>
            <button
                v-for="opcao in [['', 'Todas'], ['pago', 'Pagas'], ['pendente', 'Pendentes'], ['em_atraso', 'Em atraso']]"
                :key="opcao[0]"
                type="button"
                class="rounded-md border px-3 py-2 text-sm font-bold"
                :class="filtros.estado === opcao[0] ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700'"
                @click="filtros.estado = opcao[0]; filtrar()"
            >
                {{ opcao[1] }}
            </button>
        </div>
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm"><table class="w-full min-w-[400px] text-left text-sm"><tbody><tr v-for="cota in cotas.data" :key="cota.id" class="border-t"><td class="p-3">{{ cota.socio?.nome }}</td><td>{{ cota.ano }}/{{ cota.mes }}</td><td>{{ cota.valor }}€</td><td>{{ cota.estado }}</td></tr></tbody></table></div>
    </AppLayout>
</template>
