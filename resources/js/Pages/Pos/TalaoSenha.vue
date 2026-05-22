<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({ pedido: Object });
const ticketRef = ref(null);
const data = () => new Date(props.pedido.created_at).toLocaleString('pt-PT');
const euros = (valor) => Number(valor ?? 0).toFixed(2) + '€';
const itemsImpressao = computed(() =>
    (props.pedido.items || []).flatMap((item) => {
        const quantidade = Math.max(1, Math.floor(Number(item.quantidade || 1)));

        return Array.from({ length: quantidade }, (_, index) => ({
            ...item,
            printKey: `${item.id}-${index}`,
        }));
    }),
);

const updatePrintPageSize = () => {
    if (!ticketRef.value) return;

    const heightPx = ticketRef.value.getBoundingClientRect().height;
    const heightMm = Math.max(35, Math.ceil((heightPx * 25.4) / 96) + 2);
    let style = document.getElementById('thermal-ticket-page-size');

    if (!style) {
        style = document.createElement('style');
        style.id = 'thermal-ticket-page-size';
        document.head.appendChild(style);
    }

    style.textContent = `
        @page { size: 80mm ${heightMm}mm; margin: 0; }
        @media print {
            html, body, #app, .thermal-ticket-page {
                height: ${heightMm}mm !important;
                min-height: 0 !important;
                max-height: ${heightMm}mm !important;
            }
        }
    `;
};

const printTicket = async () => {
    await nextTick();
    updatePrintPageSize();
    window.print();
};

onMounted(() => {
    window.addEventListener('beforeprint', updatePrintPageSize);
    setTimeout(printTicket, 500);
});

onBeforeUnmount(() => window.removeEventListener('beforeprint', updatePrintPageSize));
</script>

<template>
    <main class="thermal-ticket-page min-h-screen bg-slate-100 p-4 text-slate-950 print:min-h-0 print:bg-white print:p-0">
        <section ref="ticketRef" class="thermal-ticket mx-auto max-w-[300px] bg-white p-4 font-mono shadow print:shadow-none">
            <h1 class="text-center text-lg font-black">Associação de Santana</h1>
            <div class="text-center font-black">{{ pedido.ponto_bar }}</div>
            <div class="ticket-token my-4 border-y border-dashed border-slate-400 py-4 text-center">
                <div class="text-xs uppercase">Número da senha</div>
                <div class="ticket-number text-5xl font-black">#{{ pedido.numero_senha || pedido.id }}</div>
            </div>
            <div class="ticket-items space-y-2 text-lg font-black">
                <div v-for="item in itemsImpressao" :key="item.printKey" class="flex justify-between gap-2">
                    <span>{{ item.produto?.nome }}</span>
                    <span>1 un.</span>
                </div>
            </div>
            <div class="ticket-totals mt-4 border-t border-dashed border-slate-400 pt-3 text-sm">
                <div class="flex justify-between"><span>Total</span><strong>{{ euros(pedido.total) }}</strong></div>
                <div class="flex justify-between"><span>Recebido</span><strong>{{ euros(pedido.valor_recebido) }}</strong></div>
                <div class="flex justify-between"><span>Troco</span><strong>{{ euros(pedido.troco) }}</strong></div>
                <div v-if="Number(pedido.doacao || 0) > 0" class="flex justify-between"><span>Doação</span><strong>{{ euros(pedido.doacao) }}</strong></div>
            </div>
            <div class="ticket-date mt-4 text-center text-xs">{{ data() }}</div>
            <div class="ticket-thanks mt-3 text-center font-black">Obrigado!</div>
        </section>
        <div class="no-print mx-auto mt-4 flex max-w-[300px] gap-2 print:hidden">
            <button class="flex-1 rounded-xl bg-slate-900 px-4 py-3 font-black text-white" @click="printTicket">Imprimir</button>
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
        height: fit-content !important;
        min-height: auto !important;
        margin: 0;
        padding: 0;
        background: #fff;
        overflow: visible;
    }

    body {
        display: inline-block;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    #app {
        display: inline-block;
    }

    .thermal-ticket-page {
        display: inline-block;
        width: 80mm;
        height: fit-content !important;
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
        height: fit-content !important;
        min-height: auto !important;
        margin: 0;
        padding: 1mm;
        box-shadow: none;
        break-after: avoid;
        page-break-after: avoid;
    }

    .ticket-number {
        line-height: 1;
    }

    .ticket-token {
        margin-top: 2mm !important;
        margin-bottom: 2mm !important;
        padding-top: 2mm !important;
        padding-bottom: 2mm !important;
    }

    .ticket-items > :not([hidden]) ~ :not([hidden]) {
        margin-top: 1mm !important;
    }

    .ticket-totals {
        margin-top: 2mm !important;
        padding-top: 1.5mm !important;
    }

    .ticket-date {
        margin-top: 2mm !important;
    }

    .ticket-thanks {
        margin-top: 1.5mm !important;
        margin-bottom: 0 !important;
    }

    .no-print,
    .no-print *,
    a[href]::after {
        display: none !important;
        content: none !important;
    }
}
</style>
