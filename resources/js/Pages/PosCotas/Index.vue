<script setup>
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
defineProps({ sociosEmAtraso: Number, cobradosHoje: [Number, String], cotasHoje: Number });
const agora = ref(new Date());
let timer = null;
let refresh = null;
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
onMounted(() => { timer = setInterval(() => (agora.value = new Date()), 1000); refresh = setInterval(() => router.reload({ preserveScroll: true }), 60000); });
onBeforeUnmount(() => { clearInterval(timer); clearInterval(refresh); });
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-6 flex items-center justify-between gap-3"><h1 class="text-3xl font-black">💳 TESOURARIA - Associação de Santana</h1><div class="font-black">{{ agora.toLocaleTimeString('pt-PT') }}</div><button class="rounded-lg bg-red-600 px-4 py-3 font-black" @click="router.post(route('pos.logout'))">LOGOUT</button></header>
        <section class="grid gap-4 md:grid-cols-3"><div class="rounded-lg bg-emerald-700 p-5"><div class="font-bold">💰 Cobrado Hoje</div><div class="text-4xl font-black">{{ euros(cobradosHoje) }}</div></div><div class="rounded-lg bg-blue-700 p-5"><div class="font-bold">📋 Cotas Hoje</div><div class="text-4xl font-black">{{ cotasHoje }}</div></div><Link :href="route('pos.cotas.em-atraso')" class="rounded-lg bg-red-700 p-5"><div class="font-bold">⚠️ Sócios em Atraso</div><div class="text-4xl font-black">{{ sociosEmAtraso }}</div></Link></section>
        <Link :href="route('pos.cotas.socio.pesquisa')" class="mt-8 block rounded-lg bg-blue-600 p-8 text-center text-3xl font-black">🔍 PESQUISAR SÓCIO</Link>
        <section class="mt-4 grid gap-3 md:grid-cols-2"><Link :href="route('pos.cotas.em-atraso')" class="rounded-lg bg-red-600 p-5 text-center font-black">📋 LISTA EM ATRASO</Link><Link :href="route('pos.cotas.socio.novo.form')" class="rounded-lg bg-emerald-600 p-5 text-center font-black">👤 NOVO SÓCIO</Link><Link :href="route('pos.cotas.resumo-dia')" class="rounded-lg bg-gray-700 p-5 text-center font-black">📊 RESUMO DO DIA</Link><button class="rounded-lg bg-gray-700 p-5 font-black" @click="window.print()">🖨️ IMPRIMIR RESUMO</button></section>
    </main>
</template>
