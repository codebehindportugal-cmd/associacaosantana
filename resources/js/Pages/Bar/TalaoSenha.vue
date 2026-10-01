<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
const props = defineProps({ pedido: Object });
const ticketRef = ref(null);
const data = () => new Date(props.pedido.created_at).toLocaleString('pt-PT');
const euros = (valor) => Number(valor ?? 0).toFixed(2) + '€';
const operador = computed(() => props.pedido.operador_nome ?? props.pedido.user?.name ?? props.pedido.pos?.nome ?? 'Sem operador');
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
    <main class="thermal-ticket-page min-h-screen bg-fundo p-4 text-black tabular-nums print:min-h-0 print:bg-white print:p-0">
        <section ref="ticketRef" class="thermal-ticket mx-auto max-w-[302px] bg-white px-3 pb-[18px] pt-3.5 font-mono shadow print:shadow-none">
            <div class="text-center">
                <h1 class="text-[15px] font-bold">Associação de Santana</h1>
                <div class="mt-1.5 inline-block bg-black px-3.5 py-0.5 text-[15px] font-bold tracking-[.2em] text-white">BAR</div>
                <div class="mt-1 text-[11px] font-bold">Operador: {{ operador }}</div>
            </div>
            <div class="ticket-token my-3 border-y border-dashed border-black py-2.5 text-center">
                <div class="text-[11px] uppercase tracking-[.12em]">Número da senha</div>
                <div class="ticket-number font-sans text-[88px] font-extrabold leading-none tracking-[-.02em]">#{{ pedido.numero_senha || pedido.id }}</div>
            </div>
            <div class="ticket-items text-lg font-bold leading-relaxed">
                <div v-for="item in itemsImpressao" :key="item.printKey" class="flex justify-between gap-2">
                    <span>{{ item.produto?.nome }}</span>
                    <span>1 un.</span>
                </div>
            </div>
            <div class="ticket-totals mt-2.5 border-t border-dashed border-black pt-2 text-[13px] leading-relaxed">
                <div class="flex items-baseline justify-between text-lg font-bold"><span>TOTAL</span><span>{{ euros(pedido.total) }}</span></div>
                <div class="flex justify-between"><span>Recebido</span><span>{{ euros(pedido.valor_recebido) }}</span></div>
                <div class="flex justify-between font-bold"><span>Troco</span><span>{{ euros(pedido.troco) }}</span></div>
                <div v-if="Number(pedido.doacao || 0) > 0" class="flex justify-between"><span>Doação</span><span>{{ euros(pedido.doacao) }}</span></div>
            </div>
            <div class="ticket-date mt-2.5 text-center text-[11px]">{{ data() }}</div>
            <div class="ticket-thanks mt-1.5 text-center text-[15px] font-bold">Obrigado!</div>
        </section>
        <div class="no-print mx-auto mt-4 flex max-w-[302px] gap-2 font-sans print:hidden">
            <button class="flex h-14 flex-1 items-center justify-center gap-2 rounded-[10px] bg-escuro px-4 font-extrabold text-white" @click="printTicket">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" /></svg>
                Imprimir
            </button>
            <Link :href="route('bar.prepago')" class="flex h-14 flex-1 items-center justify-center rounded-[10px] bg-verde px-4 text-center font-extrabold text-white hover:bg-verde-escuro">Novo pedido</Link>
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
