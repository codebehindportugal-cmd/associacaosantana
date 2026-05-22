<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({ pedido: Object });
const data = () => new Date(props.pedido.created_at).toLocaleString('pt-PT');
const euros = (valor) => Number(valor ?? 0).toFixed(2) + '€';
onMounted(() => setTimeout(() => window.print(), 500));
</script>

<template>
    <main class="thermal-ticket-page min-h-screen bg-slate-100 p-4 text-slate-950 print:min-h-0 print:bg-white print:p-0">
        <section class="thermal-ticket mx-auto max-w-[300px] bg-white p-4 font-mono shadow print:shadow-none">
            <h1 class="text-center text-lg font-black">Associação de Santana</h1>
            <div class="text-center font-black">{{ pedido.ponto_bar }}</div>
            <div class="my-4 border-y border-dashed border-slate-400 py-4 text-center">
                <div class="text-xs uppercase">Número da senha</div>
                <div class="ticket-number text-5xl font-black">#{{ pedido.numero_senha || pedido.id }}</div>
            </div>
            <div class="space-y-2 text-lg font-black">
                <div v-for="item in pedido.items" :key="item.id" class="flex justify-between gap-2">
                    <span>{{ item.produto?.nome }}</span>
                    <span>{{ item.quantidade }} un.</span>
                </div>
            </div>
            <div class="mt-4 border-t border-dashed border-slate-400 pt-3 text-sm">
                <div class="flex justify-between"><span>Total</span><strong>{{ euros(pedido.total) }}</strong></div>
                <div class="flex justify-between"><span>Recebido</span><strong>{{ euros(pedido.valor_recebido) }}</strong></div>
                <div class="flex justify-between"><span>Troco</span><strong>{{ euros(pedido.troco) }}</strong></div>
                <div v-if="Number(pedido.doacao || 0) > 0" class="flex justify-between"><span>Doação</span><strong>{{ euros(pedido.doacao) }}</strong></div>
            </div>
            <div class="mt-4 text-center text-xs">{{ data() }}</div>
            <div class="mt-3 text-center font-black">Obrigado!</div>
        </section>
        <div class="no-print mx-auto mt-4 flex max-w-[300px] gap-2 print:hidden">
            <button class="flex-1 rounded-xl bg-slate-900 px-4 py-3 font-black text-white" @click="window.print()">Imprimir</button>
            <Link :href="route('pos.index')" class="flex-1 rounded-xl bg-emerald-600 px-4 py-3 text-center font-black text-white">Nova Senha</Link>
        </div>
    </main>
</template>

<style>
@page {
    size: 80mm auto;
    margin: 0;
}

@media print {
    html,
    body,
    #app {
        width: 80mm;
        min-width: 80mm;
        height: auto !important;
        min-height: auto !important;
        margin: 0;
        padding: 0;
        background: #fff;
        overflow: visible;
    }

    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .thermal-ticket-page {
        width: 80mm;
        height: auto !important;
        min-height: auto !important;
        margin: 0;
        padding: 0;
        break-after: avoid;
        page-break-after: avoid;
    }

    .thermal-ticket {
        box-sizing: border-box;
        width: 80mm;
        max-width: 80mm;
        height: auto !important;
        min-height: auto !important;
        margin: 0;
        padding: 2mm 1mm;
        box-shadow: none;
        break-after: avoid;
        page-break-after: avoid;
    }

    .ticket-number {
        line-height: 1;
    }

    .no-print,
    .no-print *,
    a[href]::after {
        display: none !important;
        content: none !important;
    }
}
</style>
