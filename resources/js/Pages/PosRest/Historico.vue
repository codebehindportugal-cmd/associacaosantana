<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ pedidos: Array });
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const hora = (d) => new Date(d).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
const totalDia = computed(() => (props.pedidos ?? []).reduce((s, p) => s + Number(p.total ?? 0), 0));
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-5 flex items-center justify-between"><h1 class="text-3xl font-black">HISTORICO DO DIA</h1><Link :href="route('pos.rest.index')" class="rounded-lg bg-gray-800 px-4 py-3 font-black">VOLTAR</Link></header>
        <div class="space-y-3"><div v-for="pedido in pedidos" :key="pedido.id" class="rounded-lg bg-gray-800 p-4"><div class="flex justify-between gap-3"><strong>{{ hora(pedido.created_at) }} · Mesa {{ pedido.mesa?.numero }}</strong><strong class="text-emerald-400">{{ euros(pedido.total) }}</strong></div><div class="text-gray-300">{{ pedido.items.map(i => `${i.quantidade}x ${i.produto?.nome}`).join(', ') }}</div><div class="text-sm uppercase text-gray-400">{{ pedido.metodo_pagamento }}</div></div></div>
        <div class="mt-5 rounded-lg bg-emerald-700 p-5 text-right text-3xl font-black">{{ euros(totalDia) }}</div>
    </main>
</template>
