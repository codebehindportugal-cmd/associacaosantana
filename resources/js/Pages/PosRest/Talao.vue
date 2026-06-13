<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({ pedido: Object });

const operador = computed(() => props.pedido.operador_nome ?? props.pedido.user?.name ?? props.pedido.pos?.nome ?? 'Sem operador');
const mesaLabel = computed(() => props.pedido.mesa?.designacao ?? 'Para levar');
const euros = (v) => Number(v ?? 0).toFixed(2) + ' EUR';
const items = computed(() => Object.values((props.pedido.items ?? []).reduce((grupos, item) => {
    const chave = [item.produto?.id, item.produto?.nome, item.preco_unitario, item.secao].join('|');
    grupos[chave] ??= { ...item, id: chave, quantidade: 0 };
    grupos[chave].quantidade += Number(item.quantidade ?? 0);
    return grupos;
}, {})));

onMounted(() => setTimeout(() => window.print(), 300));
</script>

<template>
    <main class="min-h-screen bg-gray-200 p-5 text-gray-950 print:bg-white">
        <section class="mx-auto max-w-sm bg-white p-5 pb-12 font-mono">
            <h1 class="text-center text-lg font-black">Associacao de Santana - Restaurante</h1>
            <div class="my-3 border-y py-2 text-center text-sm font-black uppercase">Este documento nao serve de fatura</div>
            <div class="my-3 border-b pb-2">
                Mesa: {{ mesaLabel }}<br>
                Operador: {{ operador }}
            </div>
            <div v-for="item in items" :key="item.id" class="flex justify-between gap-2">
                <span>{{ item.quantidade }}x {{ item.produto?.nome }}</span>
                <strong>{{ euros(item.quantidade * item.preco_unitario) }}</strong>
            </div>
            <div class="mt-3 border-t pt-2 text-right">
                <div>Total: {{ euros(pedido.total) }}</div>
                <div>Recebido: {{ euros(pedido.valor_recebido) }}</div>
                <div>Troco: {{ euros(pedido.troco) }}</div>
                <div>Metodo: {{ pedido.metodo_pagamento }}</div>
            </div>
            <div class="h-8"></div>
        </section>
        <div class="mx-auto mt-5 grid max-w-sm gap-2 print:hidden">
            <button class="rounded bg-gray-900 p-3 font-black text-white" @click="window.print()">IMPRIMIR</button>
            <Link :href="route('pos.rest.mesas')" class="rounded bg-emerald-600 p-3 text-center font-black text-white">VER MESAS</Link>
            <Link :href="route('pos.rest.index')" class="rounded bg-blue-600 p-3 text-center font-black text-white">ECRA PRINCIPAL</Link>
        </div>
    </main>
</template>
