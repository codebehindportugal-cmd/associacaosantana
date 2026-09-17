<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    cota: Object,
    anos: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    pdfUrl: String,
});

const euros = (v) => Number(v ?? 0).toFixed(2).replace('.', ',') + ' €';
const iframe = ref(null);
const blobUrl = ref(null);
const estado = ref('a-preparar'); // a-preparar | pronto | erro

// Carrega o PDF num iframe (blob) para o poder imprimir diretamente
const carregar = async () => {
    try {
        const res = await fetch(props.pdfUrl, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(res.status);
        blobUrl.value = URL.createObjectURL(await res.blob());
        estado.value = 'pronto';
    } catch {
        estado.value = 'erro';
    }
};

const imprimir = () => {
    try {
        iframe.value.contentWindow.focus();
        iframe.value.contentWindow.print();
    } catch {
        window.open(props.pdfUrl, '_blank');
    }
};

let impressoAuto = false;
const aoCarregarIframe = () => {
    if (impressoAuto || !blobUrl.value) return;
    impressoAuto = true;
    setTimeout(imprimir, 400);
};

onMounted(carregar);
onBeforeUnmount(() => blobUrl.value && URL.revokeObjectURL(blobUrl.value));
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <div class="mx-auto grid max-w-5xl gap-5 lg:grid-cols-[1fr_320px]">
            <section class="overflow-hidden rounded-lg bg-gray-800">
                <iframe
                    v-if="blobUrl"
                    ref="iframe"
                    :src="blobUrl"
                    title="Recibo em PDF"
                    class="h-[75vh] w-full bg-white"
                    @load="aoCarregarIframe"
                />
                <div v-else class="grid h-[75vh] place-items-center p-6 text-center font-bold text-gray-300">
                    <span v-if="estado === 'a-preparar'">A preparar o recibo…</span>
                    <span v-else>Não foi possível gerar o PDF. <a :href="pdfUrl" target="_blank" class="underline">Abrir diretamente</a></span>
                </div>
            </section>

            <aside class="space-y-3">
                <div class="rounded-lg bg-emerald-700 p-4">
                    <p class="text-sm font-bold uppercase text-emerald-200">Pagamento registado</p>
                    <p class="mt-1 text-xl font-black">{{ cota.socio?.nome }}</p>
                    <p class="font-bold text-emerald-100">Sócio N.º {{ cota.socio?.numero_socio }}</p>
                    <p class="mt-2 font-bold">Quota(s): {{ (anos.length ? anos : [cota.ano]).join(', ') }}</p>
                    <p class="text-3xl font-black">{{ euros(total || cota.valor) }}</p>
                </div>

                <button type="button" class="w-full rounded-lg bg-white p-4 text-lg font-black text-gray-900 disabled:opacity-40" :disabled="estado !== 'pronto'" @click="imprimir">🖨️ IMPRIMIR RECIBO</button>
                <a :href="pdfUrl" target="_blank" class="block rounded-lg bg-gray-700 p-3 text-center font-black">📄 ABRIR PDF</a>
                <Link :href="route('pos.cotas.socio', cota.socio_id)" class="block rounded-lg bg-emerald-600 p-4 text-center font-black">MESMO SÓCIO</Link>
                <Link :href="route('pos.cotas.index')" class="block rounded-lg bg-blue-600 p-4 text-center font-black">OUTRO SÓCIO</Link>
            </aside>
        </div>
    </main>
</template>
