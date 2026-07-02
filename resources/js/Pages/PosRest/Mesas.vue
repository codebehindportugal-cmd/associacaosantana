<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({ mesas: Array });
let refresh = null;
const qrAberto = ref(false);
const qrDataUrl = ref('');
const grupos = computed(() => Object.groupBy(props.mesas ?? [], (m) => m.localizacao || 'Sala'));
const precarioUrl = computed(() => route('precario'));
const pedidosAtivos = (mesa) => [
    ...(mesa.pedidos ?? []),
    ...(mesa.pedidos_grupo ?? []),
    ...((mesa.submesas ?? []).flatMap((submesa) => submesa.pedidos ?? [])),
    ...((mesa.submesas ?? []).flatMap((submesa) => submesa.pedidos_grupo ?? [])),
];
const coresGrupo = [
    'bg-violet-600',
    'bg-cyan-600',
    'bg-fuchsia-600',
    'bg-lime-500 text-gray-950',
    'bg-amber-500 text-gray-950',
    'bg-blue-600',
    'bg-rose-600',
    'bg-teal-600',
];
const pedidoGrupo = (mesa) => (mesa.pedidos_grupo ?? [])[0] ?? ((mesa.submesas ?? []).flatMap((submesa) => submesa.pedidos_grupo ?? []))[0] ?? null;
const estadoVisual = (mesa) => pedidoGrupo(mesa) ? 'grupo' : (pedidosAtivos(mesa).length ? 'ocupada' : mesa.estado);
const total = (mesa) => Number(pedidosAtivos(mesa).reduce((soma, pedido) => soma + Number(pedido.total_calculado ?? pedido.total ?? 0), 0)).toFixed(2) + '€';
const minutos = (mesa) => Math.max(0, Math.floor((Date.now() - new Date(pedidosAtivos(mesa)[0]?.created_at || Date.now())) / 60000)) + 'min';
const mesaLivre = (mesa) => !pedidosAtivos(mesa).length && (mesa?.estado ?? 'livre') === 'livre';
const lugaresLivres = (mesa) => {
    if (mesa.submesas?.length) {
        return mesa.submesas
            .filter((submesa) => mesaLivre(submesa))
            .reduce((total, submesa) => total + Number(submesa.capacidade || 0), 0);
    }

    return mesaLivre(mesa) ? Number(mesa.capacidade || 0) : 0;
};
const textoLugaresLivres = (mesa) => {
    const livres = lugaresLivres(mesa);

    return `${livres} ${livres === 1 ? 'lugar livre' : 'lugares livres'}`;
};
const cor = (mesa) => {
    const grupo = pedidoGrupo(mesa);

    if (grupo) {
        return coresGrupo[Number(grupo.id ?? 0) % coresGrupo.length];
    }

    return estadoVisual(mesa) === 'ocupada' ? 'bg-red-600' : estadoVisual(mesa) === 'reservada' ? 'bg-yellow-500 text-gray-950' : 'bg-emerald-600';
};
const mostrarQrPrecario = async () => {
    qrDataUrl.value = await QRCode.toDataURL(precarioUrl.value, { width: 420, margin: 2 });
    qrAberto.value = true;
};
const copiarPrecario = async () => {
    if (navigator?.clipboard) {
        await navigator.clipboard.writeText(precarioUrl.value);
    }
};
onMounted(() => { refresh = setInterval(() => router.reload({ only: ['mesas'], preserveScroll: true }), 20000); });
onBeforeUnmount(() => clearInterval(refresh));
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-5 flex items-center gap-4"><Link :href="route('pos.rest.index')" class="rounded-lg bg-gray-800 px-4 py-3 font-black">←</Link><h1 class="text-4xl font-black">MESAS</h1></header>
        <div class="mb-5 flex justify-end">
            <button type="button" class="rounded-lg bg-emerald-600 px-4 py-3 font-black" @click="mostrarQrPrecario">QR PREÇÁRIO</button>
        </div>
        <section v-for="(lista, local) in grupos" :key="local" class="mb-7">
            <h2 class="mb-3 text-xl font-black uppercase text-gray-300">{{ local }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
                <Link v-for="mesa in lista" :key="mesa.id" :href="route('pos.rest.mesa', mesa.id)" class="flex min-h-[116px] flex-col items-center justify-center rounded-lg p-3 text-center font-black shadow" :class="cor(mesa)">
                    <span class="text-2xl sm:text-3xl">{{ mesa.numero }}</span>
                    <span class="text-xs">Cap. {{ mesa.capacidade }}</span>
                    <span class="mt-1 rounded bg-black/15 px-2 py-0.5 text-xs">{{ textoLugaresLivres(mesa) }}</span>
                    <span v-if="mesa.submesas?.length" class="mt-1 text-xs">{{ mesa.submesas.length }} submesas</span>
                    <span v-if="estadoVisual(mesa) === 'grupo'" class="mt-1 text-xs uppercase">Grupo</span>
                    <span v-if="['grupo', 'ocupada'].includes(estadoVisual(mesa))" class="mt-1 text-sm">{{ total(mesa) }} · {{ minutos(mesa) }}</span>
                </Link>
            </div>
        </section>
        <div v-if="qrAberto" class="fixed inset-0 z-50 overflow-auto bg-gray-950 p-5">
            <div class="mx-auto max-w-md rounded-2xl bg-white p-5 text-center text-slate-950">
                <h2 class="text-2xl font-black">Preçário</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Produtos e preços disponíveis</p>
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR code do preçário" class="mx-auto my-5 h-72 w-72 rounded-xl border p-3">
                <input :value="precarioUrl" readonly class="w-full rounded-lg border-slate-300 text-xs">
                <button class="mt-3 w-full rounded-lg bg-slate-900 p-3 font-black text-white" @click="copiarPrecario">COPIAR LINK</button>
                <button class="mt-3 w-full rounded-lg bg-gray-200 p-3 font-black text-slate-950" @click="qrAberto = false">FECHAR</button>
            </div>
        </div>
    </main>
</template>
