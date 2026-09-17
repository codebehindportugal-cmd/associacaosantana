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
    <main class="talao-pagina min-h-screen bg-slate-100 p-4 text-slate-950 print:min-h-0 print:bg-white print:p-0">
        <!-- Um talão por unidade, cortado, para o cliente entregar na tasquinha -->
        <section
            v-for="talaoSeccao in taloes"
            :key="'t' + talaoSeccao.indice"
            class="talao mx-auto mb-4 max-w-[300px] bg-white p-4 font-mono shadow print:mb-0 print:shadow-none"
        >
            <h1 class="text-center text-lg font-black">{{ titulo }}</h1>
            <div v-for="linha in cabecalho" :key="'c' + linha" class="text-center text-xs">{{ linha }}</div>
            <div class="mt-1 text-center font-black">{{ pedido.ponto_bar }}</div>

            <div class="talao-senha my-4 border-y border-dashed border-slate-400 py-4 text-center">
                <div class="text-xs uppercase">Número da senha</div>
                <div class="talao-numero text-5xl font-black">#{{ senha }}</div>
            </div>

            <div class="text-center text-xl font-black">1x {{ talaoSeccao.produto }}</div>
            <div class="mt-1 text-center text-lg font-black uppercase">{{ talaoSeccao.secao }}</div>

            <div v-if="talaoSeccao.total > 1" class="mt-3 text-center text-xs font-bold">
                Talão {{ talaoSeccao.indice }} de {{ talaoSeccao.total }}
            </div>

            <div v-if="instrucoes.length" class="mt-4 border-t border-dashed border-slate-400 pt-3 text-center text-xs font-bold">
                <div v-for="linha in instrucoes" :key="'i' + linha">{{ linha }}</div>
            </div>

            <div class="mt-3 text-center text-[10px]">{{ data() }}</div>
        </section>

        <!-- Conta: fica com quem está na caixa -->
        <section class="talao talao-ultimo mx-auto max-w-[300px] bg-white p-4 font-mono shadow print:shadow-none">
            <h1 class="text-center text-lg font-black">{{ titulo }}</h1>
            <div v-for="linha in cabecalho" :key="'cc' + linha" class="text-center text-xs">{{ linha }}</div>
            <div class="mt-1 text-center font-black">CONTA</div>

            <div class="mt-2 text-center text-xs font-bold">{{ pedido.ponto_bar }} · Senha #{{ senha }}</div>
            <div class="text-center text-xs">Operador: {{ operador }}</div>

            <div class="talao-itens mt-4 space-y-1 border-t border-dashed border-slate-400 pt-3 text-sm">
                <div v-for="linha in linhasConta" :key="linha.id" class="flex justify-between gap-2">
                    <span>{{ linha.quantidade }}x {{ linha.nome }}</span>
                    <span>{{ euros(linha.valor) }}</span>
                </div>
            </div>

            <div class="talao-totais mt-3 border-t border-dashed border-slate-400 pt-3 text-sm">
                <div class="flex justify-between"><span>Total</span><strong>{{ euros(pedido.total) }}</strong></div>
                <div class="flex justify-between"><span>Recebido</span><strong>{{ euros(pedido.valor_recebido) }}</strong></div>
                <div class="flex justify-between"><span>Troco</span><strong>{{ euros(pedido.troco) }}</strong></div>
                <div v-if="Number(pedido.doacao || 0) > 0" class="flex justify-between"><span>Doação</span><strong>{{ euros(pedido.doacao) }}</strong></div>
            </div>

            <div class="mt-4 text-center text-xs">{{ data() }}</div>
            <div v-if="rodape.length" class="mt-2 text-center text-[10px]">
                <div v-for="linha in rodape" :key="'r' + linha">{{ linha }}</div>
            </div>
        </section>

        <div v-if="modoImpressao === 'webusb'" class="no-print mx-auto mt-4 max-w-[300px] print:hidden">
            <div v-if="estadoUsb === 'impresso'" class="rounded-xl bg-emerald-600 p-3 text-center font-black text-white">
                Talões impressos
            </div>
            <div v-else-if="estadoUsb === 'por-ligar'" class="rounded-xl bg-amber-500 p-3 text-center text-sm font-black text-white">
                Carrega em Imprimir para autorizar a impressora deste equipamento.
                É só da primeira vez.
            </div>
            <div v-else-if="erroUsb" class="rounded-xl bg-red-600 p-3 text-center text-sm font-black text-white">
                {{ erroUsb }}
            </div>
        </div>

        <div class="no-print mx-auto mt-4 flex max-w-[300px] gap-2 print:hidden">
            <button class="flex-1 rounded-xl bg-slate-900 px-4 py-3 font-black text-white disabled:opacity-50" :disabled="aImprimir" @click="imprimir">
                {{ aImprimir ? 'A imprimir...' : 'Imprimir' }}
            </button>
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
