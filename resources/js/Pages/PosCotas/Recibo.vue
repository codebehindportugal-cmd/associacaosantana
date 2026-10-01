<script setup>
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    cota: Object,
    anos: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    papelUrl: String,
});

const euros = (v) => Number(v ?? 0).toFixed(2).replace('.', ',') + ' €';
const iframe = ref(null);
const blobUrl = ref(null);
const estado = ref('a-preparar'); // a-preparar | pronto | erro

// Carrega o PDF (recibo DL) num iframe (blob) para o poder imprimir diretamente
const carregar = async () => {
    try {
        const res = await fetch(props.papelUrl, { credentials: 'same-origin' });
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
        window.open(props.papelUrl, '_blank');
    }
};

onMounted(carregar);
onBeforeUnmount(() => blobUrl.value && URL.revokeObjectURL(blobUrl.value));
</script>

<template>
    <div class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums lg:h-screen">
        <header class="flex h-16 shrink-0 items-center justify-between gap-3 bg-escuro px-4 text-white sm:px-6">
            <div class="flex items-center gap-4">
                <div class="text-lg font-extrabold tracking-wide">ARDC Santa Ana</div>
                <div class="hidden text-sm text-[#B9C4BE] sm:block">Tesouraria · Cotas</div>
            </div>
            <div class="text-lg font-extrabold">Recibo</div>
        </header>

        <div class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_440px]">
            <aside aria-label="Pagamento" class="order-1 flex min-h-0 flex-col gap-3 border-b border-linha bg-white p-4 sm:px-6 sm:py-5 lg:order-2 lg:border-b-0 lg:border-l">
                <div class="flex flex-col gap-1 rounded-[14px] border border-verde-claro2 bg-verde-claro p-5">
                    <span class="flex items-center gap-2 text-sm font-extrabold uppercase tracking-[.06em] text-verde-escuro">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
                        Pagamento registado
                    </span>
                    <span class="mt-1.5 text-[22px] font-extrabold">{{ cota.socio?.nome }}</span>
                    <span class="text-[15px] font-semibold text-suave">Sócio N.º {{ cota.socio?.numero_socio }}</span>
                    <span class="mt-2 text-base font-semibold">Quota(s): {{ (anos.length ? anos : [cota.ano]).join(', ') }}</span>
                    <span class="text-[44px] font-extrabold leading-tight text-verde">{{ euros(total || cota.valor) }}</span>
                </div>

                <button
                    type="button"
                    class="flex h-[76px] items-center justify-center gap-3 rounded-xl bg-escuro text-[21px] font-extrabold text-white disabled:cursor-not-allowed disabled:opacity-45"
                    :disabled="estado !== 'pronto'"
                    @click="imprimir"
                >
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" /></svg>
                    Imprimir recibo
                </button>
                <p v-if="anos.length > 1" class="-mt-1 text-center text-sm font-semibold text-suave">{{ anos.length }} anos → {{ anos.length }} recibos (um por ano)</p>
                <a :href="papelUrl" target="_blank" class="flex h-14 items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white text-[17px] font-bold text-tinta">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><path d="M14 2v6h6" /></svg>
                    Abrir PDF
                </a>

                <div class="mt-auto flex flex-col gap-2.5 border-t border-linha-fraca pt-4">
                    <span class="text-sm font-bold text-suave">E agora?</span>
                    <Link :href="route('pos.cotas.index')" class="flex h-[72px] items-center justify-center rounded-xl bg-verde text-xl font-extrabold text-white hover:bg-verde-escuro">Outro sócio</Link>
                    <Link :href="route('pos.cotas.socio', cota.socio_id)" class="flex h-[60px] items-center justify-center rounded-xl border border-linha-forte bg-white text-lg font-bold text-tinta">Mesmo sócio</Link>
                </div>
            </aside>

            <section aria-label="Pré-visualização do recibo" class="order-2 flex min-h-0 flex-col gap-2.5 p-4 sm:px-6 sm:py-5 lg:order-1">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-extrabold">Recibo em PDF</h2>
                    <span v-if="estado === 'pronto'" class="flex items-center gap-2 text-sm font-semibold text-verde-escuro"><span class="h-2 w-2 rounded-full bg-verde-ok" />Pronto a imprimir</span>
                    <span v-else-if="estado === 'a-preparar'" class="flex items-center gap-2 text-sm font-semibold text-suave"><span class="h-2 w-2 animate-pulse rounded-full bg-suave-2" />A preparar</span>
                    <span v-else class="flex items-center gap-2 text-sm font-semibold text-perigo-texto"><span class="h-2 w-2 rounded-full bg-perigo" />Erro</span>
                </div>
                <div class="min-h-[50vh] flex-1 overflow-hidden rounded-[14px] border border-linha bg-[#E3E6E1]">
                    <iframe
                        v-if="blobUrl"
                        ref="iframe"
                        :src="blobUrl"
                        title="Recibo em PDF"
                        class="h-full min-h-[50vh] w-full bg-white"
                    />
                    <div v-else class="grid h-full min-h-[50vh] place-items-center p-6 text-center text-base font-semibold text-suave">
                        <span v-if="estado === 'a-preparar'">A preparar o recibo…</span>
                        <span v-else>Não foi possível gerar o PDF. <a :href="papelUrl" target="_blank" class="font-bold text-verde underline hover:text-verde-escuro">Abrir diretamente</a></span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
