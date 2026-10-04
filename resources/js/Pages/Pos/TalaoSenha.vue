<script setup>
import { Link } from '@inertiajs/vue3';
import { ImpressoraUsb } from '@/escpos';
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps({
    pedido: Object,
    // Modelo de talão em uso (Restaurante > Talão)
    talao: {
        type: Object,
        default: () => ({ titulo: 'Associação de Santana', cabecalho: [], rodape: [], instrucoes: [] }),
    },
    // Um talão por unidade: { produto, secao, indice, total }
    taloesCliente: {
        type: Array,
        default: () => [],
    },
    // Como imprime este posto: agente | webusb | navegador
    modoImpressao: {
        type: String,
        default: 'agente',
    },
    // Talões prontos em formato ESC/POS, para o WebUSB
    taloesEscpos: {
        type: Array,
        default: () => [],
    },
});

const usb = new ImpressoraUsb();
const estadoUsb = ref(null);
const erroUsb = ref(null);
const aImprimir = ref(false);

const data = () => new Date(props.pedido.created_at).toLocaleString('pt-PT');
const operador = computed(() => props.pedido.operador_nome ?? props.pedido.user?.name ?? props.pedido.pos?.nome ?? 'Sem operador');
const euros = (valor) => Number(valor ?? 0).toFixed(2) + '€';
const senha = computed(() => props.pedido.numero_senha || props.pedido.id);
const titulo = computed(() => props.talao?.titulo || 'Associação de Santana');
const cabecalho = computed(() => props.talao?.cabecalho ?? []);
const rodape = computed(() => props.talao?.rodape ?? []);
const instrucoes = computed(() => props.talao?.instrucoes ?? []);
const taloes = computed(() => props.taloesCliente ?? []);

const linhasConta = computed(() => (props.pedido.items || []).map((item) => ({
    id: item.id,
    nome: item.produto?.nome ?? 'Produto',
    quantidade: item.quantidade,
    valor: Number(item.preco_unitario || 0) * Number(item.quantidade || 0),
})));

const imprimirHtml = async () => {
    await nextTick();
    window.print();
};

/**
 * Impressão por WebUSB: os mesmos bytes que o agente enviaria, mandados
 * daqui. É o caminho dos Chromebooks, e o único do browser que corta o papel.
 */
const imprimirUsb = async ({ pedirSeNecessario = false } = {}) => {
    erroUsb.value = null;
    aImprimir.value = true;

    try {
        if (!usb.ligada && !(await usb.reconectar())) {
            if (!pedirSeNecessario) {
                estadoUsb.value = 'por-ligar';

                return;
            }

            await usb.escolher();
        }

        for (const payload of props.taloesEscpos) {
            await usb.imprimir(payload);
        }

        estadoUsb.value = 'impresso';
    } catch (e) {
        if (e?.name === 'NotFoundError') return;
        erroUsb.value = e?.message || String(e);
        estadoUsb.value = 'erro';
    } finally {
        aImprimir.value = false;
    }
};

const imprimir = () => (props.modoImpressao === 'webusb'
    ? imprimirUsb({ pedirSeNecessario: true })
    : imprimirHtml());

onMounted(() => {
    if (props.modoImpressao === 'webusb') {
        imprimirUsb();

        return;
    }

    if (props.modoImpressao === 'navegador') {
        setTimeout(imprimirHtml, 500);
    }

    // 'agente': quem imprime é o agente local, aqui não se faz nada
});
</script>

<template>
    <main class="talao-pagina min-h-screen bg-fundo p-4 text-black tabular-nums print:min-h-0 print:bg-white print:p-0">
        <!-- Um talão por unidade, cortado, para o cliente entregar na tasquinha -->
        <section
            v-for="talaoSeccao in taloes"
            :key="'t' + talaoSeccao.indice"
            class="talao mx-auto mb-4 max-w-[302px] bg-white px-3 pb-4 pt-3.5 text-center font-mono shadow print:mb-0 print:shadow-none"
        >
            <h1 class="text-[15px] font-bold">{{ titulo }}</h1>
            <div v-for="linha in cabecalho" :key="'c' + linha" class="text-[11px]">{{ linha }}</div>
            <div v-if="pedido.ponto_bar" class="mt-2 inline-block border-2 border-black px-2.5 py-0.5 text-[13px] font-bold uppercase">{{ pedido.ponto_bar }}</div>

            <div class="talao-senha my-3 border-y border-dashed border-black py-2.5">
                <div class="text-[11px] uppercase tracking-[.12em]">Número da senha</div>
                <div class="talao-numero font-sans text-[88px] font-extrabold leading-none tracking-[-.02em]">#{{ senha }}</div>
            </div>

            <div class="text-[22px] font-bold leading-tight">1x {{ talaoSeccao.produto }}</div>
            <div v-if="talaoSeccao.secao" class="mt-1.5 inline-block bg-black px-3 py-1 text-base font-bold uppercase tracking-[.08em] text-white">{{ talaoSeccao.secao }}</div>

            <div v-if="talaoSeccao.total > 1" class="mt-2.5 text-xs font-bold">
                Talão {{ talaoSeccao.indice }} de {{ talaoSeccao.total }}
            </div>

            <div v-if="instrucoes.length" class="mt-2.5 border-t border-dashed border-black pt-2 text-[11px] font-bold leading-snug">
                <div v-for="linha in instrucoes" :key="'i' + linha">{{ linha }}</div>
            </div>

            <div class="mt-2 text-[10px]">{{ data() }}</div>
        </section>

        <!-- Conta: fica com quem está na caixa -->
        <section class="talao talao-ultimo mx-auto max-w-[302px] bg-white px-3 pb-[18px] pt-3.5 font-mono shadow print:shadow-none">
            <div class="text-center">
                <h1 class="text-[15px] font-bold">{{ titulo }}</h1>
                <div v-for="linha in cabecalho" :key="'cc' + linha" class="text-[11px]">{{ linha }}</div>
                <div class="mt-2 text-lg font-bold tracking-[.2em]">CONTA</div>
                <div class="mt-1 text-xs font-bold"><template v-if="pedido.ponto_bar">{{ pedido.ponto_bar }} · </template>Senha #{{ senha }}</div>
                <div class="text-[11px]">Operador: {{ operador }}</div>
            </div>

            <div class="talao-itens mt-2.5 border-t border-dashed border-black pt-2 text-[13px] leading-relaxed">
                <div v-for="linha in linhasConta" :key="linha.id" class="flex justify-between gap-2">
                    <span>{{ linha.quantidade }}x {{ linha.nome }}</span>
                    <span>{{ euros(linha.valor) }}</span>
                </div>
            </div>

            <div class="talao-totais mt-2 border-t border-dashed border-black pt-2 text-[13px] leading-relaxed">
                <template v-if="Number(pedido.caucao_cobrada) > 0 || Number(pedido.caucao_descontada) > 0">
                    <div class="flex justify-between"><span>Produtos</span><span>{{ euros(pedido.total) }}</span></div>
                    <div v-if="Number(pedido.caucao_cobrada) > 0" class="flex justify-between"><span>Caução</span><span>{{ euros(pedido.caucao_cobrada) }}</span></div>
                    <div v-if="Number(pedido.caucao_descontada) > 0" class="flex justify-between"><span>Caução devolvida</span><span>-{{ euros(pedido.caucao_descontada) }}</span></div>
                </template>
                <div class="flex items-baseline justify-between text-lg font-bold"><span>TOTAL</span><span>{{ euros(Number(pedido.total) + Number(pedido.caucao_cobrada || 0) - Number(pedido.caucao_descontada || 0)) }}</span></div>
                <div class="flex justify-between"><span>Recebido</span><span>{{ euros(pedido.valor_recebido) }}</span></div>
                <div class="flex justify-between font-bold"><span>Troco</span><span>{{ euros(pedido.troco) }}</span></div>
                <div v-if="Number(pedido.doacao || 0) > 0" class="flex justify-between"><span>Doação</span><span>{{ euros(pedido.doacao) }}</span></div>
            </div>

            <div class="mt-2.5 text-center text-[11px]">{{ data() }}</div>
            <div v-if="rodape.length" class="mt-1.5 text-center text-[10px] leading-snug">
                <div v-for="linha in rodape" :key="'r' + linha">{{ linha }}</div>
            </div>
        </section>

        <div v-if="modoImpressao === 'webusb'" class="no-print mx-auto mt-4 max-w-[302px] font-sans print:hidden">
            <div v-if="estadoUsb === 'impresso'" class="flex items-center justify-center gap-2 rounded-[10px] bg-verde-claro p-3 text-center font-bold text-verde-escuro">
                <span class="h-2 w-2 rounded-full bg-verde-ok" />
                Talões impressos
            </div>
            <div v-else-if="estadoUsb === 'por-ligar'" class="rounded-[10px] bg-laranja-claro p-3 text-center text-sm font-bold text-laranja-texto">
                Carrega em Imprimir para autorizar a impressora deste equipamento.
                É só da primeira vez.
            </div>
            <div v-else-if="erroUsb" class="rounded-[10px] bg-perigo-claro p-3 text-center text-sm font-bold text-perigo-texto">
                {{ erroUsb }}
            </div>
        </div>

        <div class="no-print mx-auto mt-4 flex max-w-[302px] gap-2 font-sans print:hidden">
            <button class="flex h-14 flex-1 items-center justify-center gap-2 rounded-[10px] bg-escuro px-4 font-extrabold text-white disabled:opacity-50" :disabled="aImprimir" @click="imprimir">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" /></svg>
                {{ aImprimir ? 'A imprimir...' : 'Imprimir' }}
            </button>
            <Link :href="route('pos.index')" class="flex h-14 flex-1 items-center justify-center rounded-[10px] bg-verde px-4 text-center font-extrabold text-white hover:bg-verde-escuro">Nova senha</Link>
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
        margin: 0;
        padding: 0;
        background: #fff;
        overflow: visible;
    }

    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .talao-pagina {
        width: 80mm;
        margin: 0;
        padding: 0;
    }

    /* Cada talão é uma página: a impressora corta entre eles */
    .talao {
        box-sizing: border-box;
        width: 80mm;
        max-width: 80mm;
        margin: 0;
        padding: 2mm 1mm;
        box-shadow: none;
        break-after: page;
        page-break-after: always;
    }

    .talao-ultimo {
        break-after: auto;
        page-break-after: auto;
    }

    .talao-numero {
        line-height: 1;
    }

    .talao-senha {
        margin-top: 2mm !important;
        margin-bottom: 2mm !important;
        padding-top: 2mm !important;
        padding-bottom: 2mm !important;
    }

    .talao-itens > :not([hidden]) ~ :not([hidden]) {
        margin-top: 1mm !important;
    }

    .talao-totais {
        margin-top: 2mm !important;
        padding-top: 1.5mm !important;
    }

    .no-print,
    .no-print *,
    a[href]::after {
        display: none !important;
        content: none !important;
    }
}
</style>
