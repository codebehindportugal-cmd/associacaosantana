<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
defineProps({ socios: Array });
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between"><h1 class="text-2xl font-bold">Sócios em atraso</h1><a :href="route('socios.pdf')" class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white">Exportar PDF</a></div>
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm"><table class="w-full min-w-[440px] text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-3">Sócio</th><th>Meses</th><th>Dívida</th></tr></thead><tbody><tr v-for="socio in socios" :key="socio.id" class="border-t"><td class="p-3">{{ socio.numero_socio }} · {{ socio.nome }}</td><td>{{ socio.meses_atraso }}</td><td>{{ Number(socio.valor_divida).toFixed(2) }}€</td></tr></tbody></table></div>
    </AppLayout>
</template>
