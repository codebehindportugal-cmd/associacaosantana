<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ImpressoraUsb } from '@/escpos';
import { onMounted, ref } from 'vue';

const props = defineProps({
    talao: {
        type: Object,
        default: () => ({ titulo: 'ARDC Santana', cabecalho: [], rodape: [], instrucoes: [] }),
    },
});

const impressora = new ImpressoraUsb();
const suportado = ref(ImpressoraUsb.suportado());
const ligada = ref(false);
const nome = ref(null);
const erro = ref(null);
const sucesso = ref(null);
const ocupado = ref(false);

const registar = (mensagem) => {
    sucesso.value = mensagem;
    erro.value = null;
};

const falhar = (e) => {
    erro.value = e?.message || String(e);
    sucesso.value = null;
};

onMounted(async () => {
    if (!suportado.value) return;

    try {
        if (await impressora.reconectar()) {
            ligada.value = true;
            nome.value = impressora.nome;
            registar('Impressora já autorizada e ligada.');
        }
    } catch (e) {
        falhar(e);
    }
});

const ligar = async () => {
    erro.value = null;
    sucesso.value = null;

    try {
        await impressora.escolher();
        ligada.value = true;
        nome.value = impressora.nome;
        registar('Impressora ligada. A autorização fica guardada neste equipamento.');
    } catch (e) {
        // Cancelar a janela de escolha não é um erro a reportar
        if (e?.name === 'NotFoundError') return;
        falhar(e);
    }
};

const payloadTeste = () => ({
    titulo: props.talao.titulo,
    subtitulo: 'TESTE / SENHA',
    linhas: [
        ...(props.talao.cabecalho ?? []).map((texto) => ({ texto, alinhamento: 'centro' })),
        'Ponto: Bar',
        'Hora: ' + new Date().toLocaleTimeString('pt-PT').slice(0, 5),
        { texto: 'SENHA #99', alinhamento: 'centro', tamanho: 'grande' },
        '------------------------------',
        { texto: '1x Frango assado', alinhamento: 'centro', tamanho: 'grande' },
        { texto: 'Talao 1 de 2', alinhamento: 'centro' },
        '------------------------------',
        ...(props.talao.instrucoes ?? []).map((texto) => ({ texto, alinhamento: 'centro' })),
        ...(props.talao.rodape ?? []),
    ],
    cortar: true,
});

const imprimir = async (abrirCaixa = false) => {
    ocupado.value = true;

    try {
        await impressora.imprimir({ ...payloadTeste(), abrir_caixa: abrirCaixa });
        registar(abrirCaixa ? 'Talão enviado e gaveta acionada.' : 'Talão enviado. Deve ter cortado no fim.');
    } catch (e) {
        falhar(e);
    } finally {
        ocupado.value = false;
    }
};

const imprimirDois = async () => {
    ocupado.value = true;

    try {
        await impressora.imprimir(payloadTeste());
        await impressora.imprimir({ ...payloadTeste(), subtitulo: 'TESTE / CONTA' });
        registar('Dois talões enviados. Confirma que saíram separados e cortados.');
    } catch (e) {
        falhar(e);
    } finally {
        ocupado.value = false;
    }
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-3xl px-4 py-6">
            <h1 class="text-2xl font-black">Teste de impressora USB (WebUSB)</h1>
            <p class="mt-1 text-sm text-slate-600">
                Envia ESC/POS diretamente do browser para a impressora ligada por USB a este
                equipamento — sem agente. É o caminho possível nos Chromebooks, e o único que
                manda o comando de corte.
            </p>

            <div v-if="!suportado" class="mt-6 rounded-lg bg-red-50 p-5 text-sm text-red-800">
                <strong>Este browser não suporta WebUSB.</strong>
                Abre esta página no Chrome ou num Chromebook. Em Firefox e Safari não funciona.
            </div>

            <template v-else>
                <div class="mt-6 rounded-lg bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-bold text-slate-700">Estado</div>
                            <div v-if="ligada" class="text-sm text-emerald-700">Ligada a {{ nome }}</div>
                            <div v-else class="text-sm text-slate-500">Nenhuma impressora autorizada neste equipamento.</div>
                        </div>
                        <button
                            type="button"
                            class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                            @click="ligar"
                        >
                            {{ ligada ? 'Escolher outra' : 'Ligar impressora' }}
                        </button>
                    </div>

                    <div v-if="ligada" class="mt-4 flex flex-wrap gap-3 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 disabled:opacity-40"
                            :disabled="ocupado"
                            @click="imprimir(false)"
                        >
                            Imprimir um talão
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 disabled:opacity-40"
                            :disabled="ocupado"
                            @click="imprimirDois"
                        >
                            Imprimir dois seguidos
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 disabled:opacity-40"
                            :disabled="ocupado"
                            @click="imprimir(true)"
                        >
                            Talão + abrir gaveta
                        </button>
                    </div>
                </div>

                <div v-if="sucesso" class="mt-4 rounded-md bg-emerald-50 p-4 text-sm text-emerald-800">{{ sucesso }}</div>
                <div v-if="erro" class="mt-4 rounded-md bg-red-50 p-4 text-sm text-red-800">{{ erro }}</div>

                <div class="mt-6 rounded-lg bg-amber-50 p-5 text-sm text-amber-900">
                    <strong class="block">Se a impressora não aparecer na lista, ou der erro ao ligar</strong>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-xs">
                        <li>
                            No ChromeOS, tira a impressora das definições de impressão do sistema. Se o
                            ChromeOS a adotar como impressora, fica com ela e o WebUSB deixa de conseguir abri-la.
                        </li>
                        <li>
                            No Windows, o driver instalado fica com o dispositivo e o WebUSB não o consegue
                            reclamar — nesses computadores usa o agente.
                        </li>
                        <li>Liga o cabo diretamente, sem hub sem alimentação própria.</li>
                        <li>A autorização é por equipamento e por site: cada Chromebook autoriza uma vez.</li>
                    </ul>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
