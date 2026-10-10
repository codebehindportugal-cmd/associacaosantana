<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ImpressoraUsb } from '@/escpos';
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps({
    // Mesmo formato dos taloes do agente: { titulo, subtitulo, linhas: [string | { texto, alinhamento, tamanho }] }
    payload: Object,
    // Como imprime o posto deste ponto: rede | usb | webusb | navegador
    modo: { type: String, default: 'navegador' },
    voltar: { type: String, default: null },
});

const linhas = computed(() => (props.payload?.linhas ?? []).map((l) => (typeof l === 'string' ? { texto: l } : l)));
const usb = new ImpressoraUsb();
const estado = ref(null);
const aImprimir = ref(false);

const imprimirBrowser = async () => {
    await nextTick();
    window.print();
};

const imprimirUsb = async () => {
    estado.value = null;
    aImprimir.value = true;
    try {
        if (!usb.ligada && !(await usb.reconectar())) await usb.escolher();
        await usb.imprimir(props.payload);
        estado.value = 'Impresso.';
    } catch (e) {
        if (e?.name !== 'NotFoundError') estado.value = `Não foi possível imprimir por USB: ${e?.message || e}`;
    } finally {
        aImprimir.value = false;
    }
};

onMounted(() => {
    if (props.modo !== 'webusb') setTimeout(imprimirBrowser, 400);
});
</script>

<template>
    <Head :title="payload?.subtitulo || 'Talão de caixa'" />
    <main class="min-h-screen bg-fundo py-6 text-tinta print:bg-white print:py-0">
        <div class="mx-auto mb-4 flex max-w-[340px] flex-wrap gap-2 px-4 print:hidden">
            <Link v-if="voltar" :href="voltar" class="inline-flex h-12 items-center rounded-[10px] border border-linha-forte bg-white px-4 font-bold">Voltar à caixa</Link>
            <button v-if="modo === 'webusb'" type="button" class="h-12 flex-1 rounded-[10px] bg-verde px-4 font-bold text-white disabled:opacity-45" :disabled="aImprimir" @click="imprimirUsb">{{ aImprimir ? 'A imprimir…' : 'Imprimir (USB)' }}</button>
            <button type="button" class="h-12 flex-1 rounded-[10px] px-4 font-bold" :class="modo === 'webusb' ? 'border border-linha-forte bg-white' : 'bg-verde text-white'" @click="imprimirBrowser">Imprimir</button>
            <p v-if="estado" role="status" class="w-full text-sm font-semibold text-suave">{{ estado }}</p>
        </div>

        <article class="talao mx-auto bg-white p-4 font-mono text-[12px] leading-snug shadow-sm print:p-0 print:shadow-none">
            <h1 class="text-center text-base font-bold">{{ payload?.titulo }}</h1>
            <h2 class="mb-2 text-center text-sm font-bold">{{ payload?.subtitulo }}</h2>
            <div
                v-for="(l, i) in linhas"
                :key="i"
                class="whitespace-pre-wrap break-words"
                :class="[l.alinhamento === 'centro' ? 'text-center' : '', l.tamanho === 'grande' ? 'my-1 text-lg font-bold' : '']"
            >{{ l.texto || ' ' }}</div>
        </article>
    </main>
</template>

<style scoped>
.talao { width: 80mm; max-width: 100%; }
@media print {
    @page { size: 80mm auto; margin: 3mm; }
    .talao { width: auto; }
}
</style>
