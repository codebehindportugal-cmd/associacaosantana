<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({ pedido: Object });

const operador = computed(() => props.pedido.operador_nome ?? props.pedido.user?.name ?? props.pedido.pos?.nome ?? 'Sem operador');
const mesaLabel = computed(() => props.pedido.mesa?.designacao ?? 'Para levar');
// Número da mesa em grande (sem o prefixo "Mesa"); nome livre ou "Para levar" em tamanho menor
const mesaGrande = computed(() => props.pedido.mesa?.nome || props.pedido.mesa?.numero || mesaLabel.value);
const mesaCurta = computed(() => String(mesaGrande.value).length <= 4);
const agora = new Date().toLocaleString('pt-PT', { useGrouping: 'always', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
const euros = (v) => Number(v ?? 0).toFixed(2) + ' EUR';
const items = computed(() => Object.values((props.pedido.items ?? []).reduce((grupos, item) => {
    const chave = [item.produto?.id, item.produto?.nome, item.preco_unitario, item.secao].join('|');
    grupos[chave] ??= { ...item, id: chave, quantidade: 0 };
    grupos[chave].quantidade += Number(item.quantidade ?? 0);
    return grupos;
}, {})));

const imprimir = () => window.print();

onMounted(() => setTimeout(() => window.print(), 300));
</script>

<template>
    <main class="min-h-screen bg-fundo p-5 text-black tabular-nums print:bg-white">
        <section class="mx-auto max-w-[302px] bg-white px-3 pb-10 pt-3.5 font-mono shadow print:shadow-none">
            <div class="text-center">
                <h1 class="text-[15px] font-bold">Associação de Santana</h1>
                <div class="text-xs tracking-[.14em]">RESTAURANTE</div>
            </div>
            <div class="my-2.5 border-y border-black py-1.5 text-center text-[11px] font-bold uppercase">Este documento não serve de fatura</div>
            <div class="pb-2.5 pt-1 text-center">
                <div class="text-[11px] uppercase tracking-[.12em]">{{ pedido.mesa ? 'Mesa' : 'Tipo' }}</div>
                <div class="font-sans font-extrabold leading-none" :class="mesaCurta ? 'text-[80px]' : 'text-[32px]'">{{ mesaGrande }}</div>
                <div class="mt-1 text-[11px]">Operador: {{ operador }}</div>
            </div>
            <div class="border-t border-dashed border-black pt-2 text-[13px] leading-relaxed">
                <div v-for="item in items" :key="item.id" class="flex justify-between gap-2">
                    <span>{{ item.quantidade }}x {{ item.produto?.nome }}</span>
                    <span class="font-bold">{{ euros(item.quantidade * item.preco_unitario) }}</span>
                </div>
            </div>
            <div class="mt-2 border-t border-dashed border-black pt-2 text-[13px] leading-relaxed">
                <div class="flex items-baseline justify-between text-xl font-bold"><span>TOTAL</span><span>{{ euros(pedido.total) }}</span></div>
                <div class="flex justify-between"><span>Recebido</span><span>{{ euros(pedido.valor_recebido) }}</span></div>
                <div class="flex justify-between font-bold"><span>Troco</span><span>{{ euros(pedido.troco) }}</span></div>
                <div class="flex justify-between"><span>Método</span><span>{{ pedido.metodo_pagamento }}</span></div>
            </div>
            <div class="mt-3 text-center text-[11px]">{{ agora }}</div>
            <div class="h-8"></div>
        </section>
        <div class="mx-auto mt-5 grid max-w-[302px] gap-2 font-sans print:hidden">
            <button class="flex h-14 items-center justify-center gap-2 rounded-[10px] bg-escuro font-extrabold text-white" @click="imprimir">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" /></svg>
                Imprimir
            </button>
            <Link :href="route('pos.rest.mesas')" class="flex h-14 items-center justify-center rounded-[10px] bg-verde text-center font-extrabold text-white hover:bg-verde-escuro">Ver mesas</Link>
            <Link :href="route('pos.rest.index')" class="flex h-14 items-center justify-center rounded-[10px] border border-linha-forte bg-white text-center font-bold text-tinta">Ecrã principal</Link>
        </div>
    </main>
</template>
