<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ pedidos: Array });
const euros = (v) => Number(v ?? 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const hora = (d) => new Date(d).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
const totalDia = computed(() => (props.pedidos ?? []).reduce((s, p) => s + Number(p.total ?? 0), 0));

// Apresentação (redesign)
const metodos = {
    dinheiro: { label: 'Dinheiro', classe: 'bg-verde-claro text-verde-escuro' },
    mbway: { label: 'MB WAY', classe: 'bg-[#F1EAF6] text-[#5A2C78]' },
    multibanco: { label: 'Multibanco', classe: 'bg-[#EAF0FB] text-[#1E4290]' },
};
const metodoInfo = (m) => metodos[m] ?? { label: m || 'Em aberto', classe: 'bg-fundo text-suave' };
const artigos = (pedido) => (pedido.items ?? []).map((i) => `${i.quantidade}x ${i.produto?.nome}`).join(', ');
const porMetodo = computed(() => Object.keys(metodos).map((k) => ({
    label: metodos[k].label,
    total: (props.pedidos ?? []).filter((p) => p.metodo_pagamento === k).reduce((s, p) => s + Number(p.total ?? 0), 0),
})));
</script>

<template>
    <main class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums">
        <header class="flex h-16 shrink-0 items-center justify-between gap-3 bg-escuro px-4 text-white sm:px-6">
            <div class="flex items-center gap-3.5">
                <Link :href="route('pos.rest.index')" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white hover:text-white">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    Voltar
                </Link>
                <h1 class="text-[22px] font-extrabold">Histórico do dia</h1>
            </div>
            <span class="hidden text-sm text-escuro-inativo sm:block">Só pedidos deste terminal</span>
        </header>

        <div class="grid flex-1 items-start gap-5 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <section aria-label="Pedidos de hoje" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="hidden grid-cols-[80px_110px_minmax(0,1fr)_130px_100px] gap-3 border-b border-linha px-5 py-3 text-xs font-bold uppercase tracking-wider text-suave md:grid">
                    <span>Hora</span><span>Mesa</span><span>Artigos</span><span>Pagamento</span><span class="text-right">Total</span>
                </div>
                <p v-if="!pedidos?.length" class="p-8 text-center text-[15px] text-suave">Ainda não há pedidos hoje neste terminal.</p>
                <div
                    v-for="pedido in pedidos"
                    :key="pedido.id"
                    class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 border-b border-linha-fraca px-5 py-4 last:border-b-0 md:grid-cols-[80px_110px_minmax(0,1fr)_130px_100px] md:gap-3"
                >
                    <span class="text-base font-bold">{{ hora(pedido.created_at) }}</span>
                    <span class="text-lg font-extrabold">Mesa {{ pedido.mesa?.numero }}</span>
                    <span class="col-span-3 row-start-2 truncate text-[15px] text-suave md:col-span-1 md:row-start-auto">{{ artigos(pedido) }}</span>
                    <span class="col-span-2 row-start-3 md:col-span-1 md:row-start-auto"><span class="rounded-full px-2.5 py-1 text-[13px] font-bold" :class="metodoInfo(pedido.metodo_pagamento).classe">{{ metodoInfo(pedido.metodo_pagamento).label }}</span></span>
                    <span class="col-start-3 row-start-1 text-right text-lg font-extrabold md:col-start-auto md:row-start-auto">{{ euros(pedido.total) }}</span>
                </div>
            </section>

            <aside class="flex flex-col gap-4">
                <div class="rounded-[14px] bg-escuro px-6 py-5 text-white">
                    <span class="block text-[15px] text-escuro-inativo">Total do dia</span>
                    <span class="block text-5xl font-extrabold">{{ euros(totalDia) }}</span>
                    <span class="mt-1 block text-sm text-escuro-inativo">{{ pedidos?.length ?? 0 }} pedidos</span>
                </div>
                <div class="rounded-[14px] border border-linha bg-white px-5 py-4">
                    <h2 class="border-b border-linha pb-2 text-base font-bold">Por método de pagamento</h2>
                    <div v-for="m in porMetodo" :key="m.label" class="flex justify-between border-b border-linha-fraca py-2.5 text-[15px] font-semibold last:border-b-0">
                        <span>{{ m.label }}</span><span class="font-bold">{{ euros(m.total) }}</span>
                    </div>
                </div>
            </aside>
        </div>
    </main>
</template>
