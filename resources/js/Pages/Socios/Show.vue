<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
defineProps({ socio: Object });
</script>

<template>
    <AppLayout>
        <h1 class="mb-6 text-2xl font-bold">{{ socio.nome }}</h1>
        <div class="grid gap-6 lg:grid-cols-[320px_1fr]">
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="text-sm text-slate-500">{{ socio.numero_socio }}</div>
                <div v-if="socio.morada" class="mt-1 text-sm text-slate-600">{{ socio.morada }}</div>
                <div class="mt-2 text-lg font-semibold">{{ socio.telefone || '—' }}</div>
                <div class="mt-4 rounded-md p-4 text-center font-bold" :class="socio.cota_em_dia ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'">{{ socio.cota_em_dia ? 'COTA EM DIA' : `EM ATRASO · ${socio.anos_em_atraso} ano(s) · ${Number(socio.valor_em_divida).toFixed(2)}€` }}</div>
            </div>
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="w-full min-w-[360px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3">Ano</th><th class="p-3">Tipo</th><th class="p-3">Valor</th><th class="p-3">Estado</th></tr></thead>
                    <tbody><tr v-for="cota in socio.cotas" :key="cota.id" class="border-t"><td class="p-3 font-bold">{{ cota.ano }}</td><td class="p-3">{{ cota.mes ? `Mensal (${String(cota.mes).padStart(2, '0')})` : 'Anual' }}</td><td class="p-3">{{ Number(cota.valor).toFixed(2) }}€</td><td class="p-3">{{ cota.estado === 'pago' ? 'Paga' : cota.estado === 'em_atraso' ? 'Em atraso' : 'Pendente' }}</td></tr></tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
