<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';
defineProps({ cota: Object });
const meses = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const periodo = (c) => c.tipo === 'anual' ? `Anual ${c.ano}` : `${meses[c.mes - 1]} ${c.ano}`;
onMounted(() => setTimeout(() => window.print(), 300));
</script>

<template>
    <main class="min-h-screen bg-gray-200 p-5 text-gray-950 print:bg-white">
        <section class="mx-auto max-w-sm bg-white p-5 text-center font-mono"><h1 class="font-black">Associação de Santana</h1><h2 class="mb-3 font-black">RECIBO DE PAGAMENTO DE COTA</h2><div class="border-y py-3 text-left">Recibo: #{{ cota.id }}<br>Nome: {{ cota.socio?.nome }}<br>Sócio: {{ cota.socio?.numero_socio }}<br>Período: {{ periodo(cota) }}<br>Valor: {{ euros(cota.valor) }}<br>Método: {{ cota.metodo_pagamento }}<br>Data: {{ new Date(cota.data_pagamento).toLocaleString('pt-PT') }}</div><p class="mt-3">Obrigado pela sua contribuição!</p></section>
        <div class="mx-auto mt-5 grid max-w-sm gap-2 print:hidden"><button class="rounded bg-gray-900 p-3 font-black text-white" @click="window.print()">🖨️ IMPRIMIR RECIBO</button><Link :href="route('pos.cotas.index')" class="rounded bg-blue-600 p-3 text-center font-black text-white">OUTRO SÓCIO</Link><Link :href="route('pos.cotas.socio', cota.socio_id)" class="rounded bg-emerald-600 p-3 text-center font-black text-white">MESMO SÓCIO</Link></div>
    </main>
</template>
