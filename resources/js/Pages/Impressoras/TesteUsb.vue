<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ImpressoraUsb } from '@/escpos';
import { computed, onMounted, ref } from 'vue';

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

// Pré-visualização do talão de teste (os mesmos dados que vão para a impressora)
const previsao = computed(() => {
    const p = payloadTeste();
    return (p.linhas ?? []).map((l) => (typeof l === 'string'
        ? { texto: l, alinhamento: 'esquerda', tamanho: 'normal' }
        : { texto: l?.texto ?? '', alinhamento: l?.alinhamento ?? 'esquerda', tamanho: l?.tamanho ?? 'normal' }));
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
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta">
            <div class="flex flex-col gap-1">
                <a :href="route('impressoras.index')" class="inline-flex w-fit items-center gap-1 text-sm font-bold text-verde hover:text-verde-escuro">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                    Impressoras
                </a>
                <h1 class="text-[26px] font-extrabold leading-tight sm:text-[30px]">Teste de impressora USB (WebUSB)</h1>
                <p class="max-w-3xl text-[15px] text-suave">
                    Envia ESC/POS diretamente do browser para a impressora ligada por USB a este
                    equipamento — sem agente. É o caminho possível nos Chromebooks, e o único que
                    manda o comando de corte.
                </p>
            </div>

            <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="flex min-w-0 flex-col gap-4">
                    <div v-if="!suportado" class="rounded-[14px] border border-perigo-claro bg-perigo-claro p-5 text-sm text-perigo-texto">
                        <strong class="font-extrabold">Este browser não suporta WebUSB.</strong>
                        Abre esta página no Chrome ou num Chromebook. Em Firefox e Safari não funciona.
                    </div>

                    <template v-else>
                        <section class="cartao flex flex-col gap-4 p-4 sm:p-5">
                            <h2 class="flex items-center gap-3 text-lg font-extrabold">
                                <span class="grid h-8 w-8 place-items-center rounded-full bg-tinta text-sm text-white" aria-hidden="true">1</span>
                                Ligar a impressora
                            </h2>
                            <div class="flex flex-wrap items-center gap-3 rounded-[10px] p-3" :class="ligada ? 'bg-verde-claro' : 'bg-fundo'">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-[10px] bg-white" :class="ligada ? 'text-verde' : 'text-suave'" aria-hidden="true">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 9V3h10v6M7 17H4v-7h16v7h-3" /><path d="M7 14h10v7H7z" /></svg>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[13px] font-semibold text-suave">Estado</div>
                                    <div v-if="ligada" class="text-lg font-extrabold text-verde-escuro">Ligada a {{ nome }}</div>
                                    <div v-else class="text-[15px] font-semibold text-suave">Nenhuma impressora autorizada neste equipamento.</div>
                                </div>
                                <button type="button" class="h-12 shrink-0 rounded-[10px] px-5 text-[15px] font-bold transition" :class="ligada ? 'border border-linha-forte bg-white text-tinta hover:bg-fundo' : 'bg-verde text-white hover:bg-verde-escuro'" @click="ligar">
                                    {{ ligada ? 'Escolher outra' : 'Ligar impressora' }}
                                </button>
                            </div>
                        </section>

                        <section class="cartao flex flex-col gap-4 p-4 sm:p-5" :class="ligada ? '' : 'opacity-60'">
                            <h2 class="flex items-center gap-3 text-lg font-extrabold">
                                <span class="grid h-8 w-8 place-items-center rounded-full text-sm" :class="ligada ? 'bg-tinta text-white' : 'bg-linha text-suave'" aria-hidden="true">2</span>
                                Imprimir um teste
                            </h2>
                            <p v-if="!ligada" class="text-sm text-suave">Liga primeiro a impressora no passo 1.</p>
                            <div v-else class="grid gap-2.5 sm:grid-cols-3">
                                <button type="button" class="opcao border-2 border-verde bg-verde-claro" :disabled="ocupado" @click="imprimir(false)">
                                    <span class="text-base font-extrabold text-verde-escuro">Imprimir um talão</span>
                                    <span class="text-[13px] text-suave">Deve cortar no fim.</span>
                                </button>
                                <button type="button" class="opcao border border-linha-forte bg-white hover:bg-fundo" :disabled="ocupado" @click="imprimirDois">
                                    <span class="text-base font-extrabold">Imprimir dois seguidos</span>
                                    <span class="text-[13px] text-suave">Senha e conta, separados.</span>
                                </button>
                                <button type="button" class="opcao border border-linha-forte bg-white hover:bg-fundo" :disabled="ocupado" @click="imprimir(true)">
                                    <span class="text-base font-extrabold">Talão + abrir gaveta</span>
                                    <span class="text-[13px] text-suave">Testa a gaveta de dinheiro.</span>
                                </button>
                            </div>

                            <div v-if="sucesso" class="flex items-center gap-2.5 rounded-[10px] bg-verde-claro px-4 py-3 text-[15px] font-semibold text-verde-escuro" role="status">
                                <svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg>
                                {{ sucesso }}
                            </div>
                            <div v-if="erro" class="flex items-start gap-2.5 rounded-[10px] bg-perigo-claro px-4 py-3 text-[15px] font-semibold text-perigo-texto" role="alert">
                                <svg class="mt-0.5 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                                {{ erro }}
                            </div>
                        </section>

                        <section class="rounded-[14px] border border-laranja-claro bg-laranja-claro p-4 text-laranja-texto sm:p-5">
                            <strong class="block text-base font-extrabold">Se a impressora não aparecer na lista, ou der erro ao ligar</strong>
                            <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm">
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
                        </section>
                    </template>
                </div>

                <!-- Pré-visualização do talão -->
                <aside class="flex flex-col gap-2 lg:sticky lg:top-4">
                    <h2 class="text-[13px] font-extrabold uppercase tracking-[0.06em] text-suave-2">O que vai sair</h2>
                    <div class="cartao p-4">
                        <div class="mx-auto w-full max-w-[302px] bg-white px-3 py-4 font-mono text-[12px] leading-[1.5] text-tinta shadow-[0_1px_4px_rgba(22,32,28,0.15)]">
                            <p class="text-center text-[14px] font-bold">{{ talao.titulo }}</p>
                            <p class="text-center">TESTE / SENHA</p>
                            <p
                                v-for="(linha, i) in previsao"
                                :key="i"
                                class="min-h-[1.5em] whitespace-pre-wrap break-words"
                                :class="[
                                    linha.alinhamento === 'centro' ? 'text-center' : linha.alinhamento === 'direita' ? 'text-right' : 'text-left',
                                    linha.tamanho === 'grande' ? 'text-[15px] font-bold' : '',
                                ]"
                            >{{ linha.texto }}</p>
                            <p class="mt-2 border-t border-dashed border-suave-2 pt-1 text-center text-[11px] text-suave">corte</p>
                        </div>
                    </div>
                    <p class="text-[13px] text-suave">Usa o modelo de <a :href="route('talao.index')" class="font-bold text-verde underline">Talão</a> que estiver em uso.</p>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.cartao { @apply rounded-[14px] border border-linha bg-white; }
.opcao { @apply flex min-h-[68px] flex-col items-start justify-center gap-0.5 rounded-[10px] px-4 py-3 text-left transition disabled:opacity-40; }
</style>
