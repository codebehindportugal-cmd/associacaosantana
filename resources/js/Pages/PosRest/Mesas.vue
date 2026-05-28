<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted } from 'vue';

const props = defineProps({ mesas: Array });
let refresh = null;
const grupos = computed(() => Object.groupBy(props.mesas ?? [], (m) => m.localizacao || 'Sala'));
const pedidosAtivos = (mesa) => [
    ...(mesa.pedidos ?? []),
    ...(mesa.pedidos_grupo ?? []),
    ...((mesa.submesas ?? []).flatMap((submesa) => submesa.pedidos ?? [])),
];
const estadoVisual = (mesa) => (mesa.pedidos_grupo ?? []).length ? 'grupo' : (pedidosAtivos(mesa).length ? 'ocupada' : mesa.estado);
const total = (mesa) => Number(pedidosAtivos(mesa).reduce((soma, pedido) => soma + Number(pedido.total_calculado ?? pedido.total ?? 0), 0)).toFixed(2) + '€';
const minutos = (mesa) => Math.max(0, Math.floor((Date.now() - new Date(pedidosAtivos(mesa)[0]?.created_at || Date.now())) / 60000)) + 'min';
const cor = (mesa) => estadoVisual(mesa) === 'grupo' ? 'bg-violet-600' : estadoVisual(mesa) === 'ocupada' ? 'bg-red-600' : estadoVisual(mesa) === 'reservada' ? 'bg-yellow-500 text-gray-950' : 'bg-emerald-600';
onMounted(() => { refresh = setInterval(() => router.reload({ only: ['mesas'], preserveScroll: true }), 20000); });
onBeforeUnmount(() => clearInterval(refresh));
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-5 flex items-center gap-4"><Link :href="route('pos.rest.index')" class="rounded-lg bg-gray-800 px-4 py-3 font-black">←</Link><h1 class="text-4xl font-black">MESAS</h1></header>
        <section v-for="(lista, local) in grupos" :key="local" class="mb-7">
            <h2 class="mb-3 text-xl font-black uppercase text-gray-300">{{ local }}</h2>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-9">
                <Link v-for="mesa in lista" :key="mesa.id" :href="route('pos.rest.mesa', mesa.id)" class="flex min-h-[116px] flex-col items-center justify-center rounded-lg p-3 text-center font-black shadow" :class="cor(mesa)">
                    <span class="text-3xl">{{ mesa.numero }}</span>
                    <span class="text-xs">Cap. {{ mesa.capacidade }}</span>
                    <span v-if="mesa.submesas?.length" class="mt-1 text-xs">{{ mesa.submesas.length }} submesas · {{ mesa.submesas.filter((s) => s.estado === 'livre').length }} livres</span>
                    <span v-if="estadoVisual(mesa) === 'grupo'" class="mt-1 text-xs uppercase">Grupo</span>
                    <span v-if="['grupo', 'ocupada'].includes(estadoVisual(mesa))" class="mt-1 text-sm">{{ total(mesa) }} · {{ minutos(mesa) }}</span>
                </Link>
            </div>
        </section>
    </main>
</template>
