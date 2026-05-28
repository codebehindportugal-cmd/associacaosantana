<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
const props = defineProps({ pedido: Object });
const operador = computed(() => props.pedido.operador_nome ?? props.pedido.user?.name ?? props.pedido.pos?.nome ?? 'Sem operador');
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
onMounted(() => setTimeout(() => window.print(), 300));
</script>

<template>
    <main class="min-h-screen bg-gray-200 p-5 text-gray-950 print:bg-white">
        <section class="mx-auto max-w-sm bg-white p-5 font-mono">
            <h1 class="text-center text-lg font-black">Associação de Santana - RESTAURANTE</h1>
            <div class="my-3 border-y py-2">Mesa: {{ pedido.mesa?.numero }}<br>Pedido: #{{ pedido.id }}<br>Operador: {{ operador }}</div>
            <div v-for="item in pedido.items" :key="item.id" class="flex justify-between gap-2"><span>{{ item.quantidade }}x {{ item.produto?.nome }} ({{ item.secao }})</span><strong>{{ euros(item.quantidade * item.preco_unitario) }}</strong></div>
            <div class="mt-3 border-t pt-2 text-right"><div>Total: {{ euros(pedido.total) }}</div><div>Recebido: {{ euros(pedido.valor_recebido) }}</div><div>Troco: {{ euros(pedido.troco) }}</div><div>Método: {{ pedido.metodo_pagamento }}</div></div>
        </section>
        <div class="mx-auto mt-5 grid max-w-sm gap-2 print:hidden"><button class="rounded bg-gray-900 p-3 font-black text-white" @click="window.print()">🖨️ IMPRIMIR</button><Link :href="route('pos.rest.mesas')" class="rounded bg-emerald-600 p-3 text-center font-black text-white">VER MESAS</Link><Link :href="route('pos.rest.index')" class="rounded bg-blue-600 p-3 text-center font-black text-white">ECRÃ PRINCIPAL</Link></div>
    </main>
</template>
