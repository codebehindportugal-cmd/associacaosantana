<script setup>
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({ posNome: String, vendasHoje: [Number, String], mesasLivres: Number, mesasOcupadas: Number });
const agora = ref(new Date());
let relogio = null;
let refresh = null;
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const logout = () => router.post(route('pos.logout'));
onMounted(() => {
    relogio = setInterval(() => (agora.value = new Date()), 1000);
    refresh = setInterval(() => router.reload({ preserveScroll: true }), 30000);
});
onBeforeUnmount(() => { clearInterval(relogio); clearInterval(refresh); });
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-6 flex items-center justify-between gap-3">
            <div><h1 class="text-3xl font-black">POS RESTAURANTE</h1><p class="font-bold text-gray-300">{{ posNome }} · {{ agora.toLocaleTimeString('pt-PT') }}</p></div>
            <button class="rounded-lg bg-red-600 px-5 py-3 font-black" @click="logout">LOGOUT</button>
        </header>
        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg bg-emerald-700 p-5"><div class="font-bold">Mesas Livres</div><div class="text-5xl font-black">{{ mesasLivres }}</div></div>
            <div class="rounded-lg bg-red-700 p-5"><div class="font-bold">Mesas Ocupadas</div><div class="text-5xl font-black">{{ mesasOcupadas }}</div></div>
            
        </section>
        <Link :href="route('pos.rest.mesas')" class="mt-8 block rounded-lg bg-emerald-600 p-8 text-center text-3xl font-black">🍽️ VER MESAS</Link>
        <Link :href="route('pos.rest.historico')" class="mt-4 block rounded-lg bg-blue-600 p-6 text-center text-2xl font-black">📋 HISTORICO DO DIA</Link>
    </main>
</template>
