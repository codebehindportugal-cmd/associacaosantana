<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({ posNome: String, vendasHoje: [Number, String], mesasLivres: Number, mesasOcupadas: Number, mesas: Array, zonas: Array });
const agora = ref(new Date());
let relogio = null;
let refresh = null;
const modoEdicao = ref(false);
const zonaEmEdicao = ref(null);
const zonaForm = useForm({ zonas: [] });

const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const logout = () => router.post(route('pos.logout'));

const grupos = computed(() => Object.groupBy(props.mesas ?? [], (m) => m.localizacao || 'Sala'));
const pedidosAtivos = (mesa) => [
    ...(mesa.pedidos ?? []),
    ...(mesa.pedidos_grupo ?? []),
    ...((mesa.submesas ?? []).flatMap((submesa) => submesa.pedidos ?? [])),
    ...((mesa.submesas ?? []).flatMap((submesa) => submesa.pedidos_grupo ?? [])),
];
const estadoVisual = (mesa) => (mesa.pedidos_grupo ?? []).length ? 'grupo' : (pedidosAtivos(mesa).length ? 'ocupada' : mesa.estado);
const cor = (mesa) => {
    const grupo = (mesa.pedidos_grupo ?? [])[0];
    if (grupo) return 'border-blue-500 bg-blue-900/50';
    return estadoVisual(mesa) === 'ocupada' ? 'border-red-500 bg-red-900/50' : estadoVisual(mesa) === 'reservada' ? 'border-yellow-500 bg-yellow-900/50' : 'border-emerald-500 bg-emerald-900/50';
};

const iniciarEdicao = () => {
    modoEdicao.value = true;
    zonaForm.zonas = props.zonas.map(z => ({ 
        id: z.id, 
        mapa_x: z.mapa_x, 
        mapa_y: z.mapa_y, 
        mapa_largura: z.mapa_largura, 
        mapa_altura: z.mapa_altura 
    }));
};

const guardarMapa = () => {
    zonaForm.post(route('zonas.mapa.guardar'), {
        onSuccess: () => {
            modoEdicao.value = false;
            zonaEmEdicao.value = null;
        }
    });
};

const alterarPosicao = (zona, campo, delta) => {
    const zonaIndex = zonaForm.zonas.findIndex(z => z.id === zona.id);
    if (zonaIndex >= 0) {
        zonaForm.zonas[zonaIndex][campo] = Math.max(0, Math.min(100, zonaForm.zonas[zonaIndex][campo] + delta));
    }
};

onMounted(() => {
    relogio = setInterval(() => (agora.value = new Date()), 1000);
    refresh = setInterval(() => router.reload({ preserveScroll: true }), 30000);
});
onBeforeUnmount(() => { clearInterval(relogio); clearInterval(refresh); });
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-6 flex items-center justify-between gap-3">
            <div><h1 class="text-3xl font-black">POS RESTAURANTE</h1><p class="font-bold text-gray-300">{{ posNome }} · {{ agora.toLocaleTimeString('pt-PT') }}</p></div>
            <div class="flex gap-2">
                <button v-if="!modoEdicao" class="rounded-lg bg-blue-600 px-5 py-3 font-black" @click="iniciarEdicao">✏️ EDITAR MAPA</button>
                <button v-else class="rounded-lg bg-green-600 px-5 py-3 font-black disabled:opacity-50" :disabled="zonaForm.processing" @click="guardarMapa">✅ GUARDAR</button>
                <button v-if="modoEdicao" class="rounded-lg bg-gray-600 px-5 py-3 font-black" @click="modoEdicao = false">✕ CANCELAR</button>
                <button class="rounded-lg bg-red-600 px-5 py-3 font-black" @click="logout">LOGOUT</button>
            </div>
        </header>
        
        <!-- ESTATÍSTICAS -->
        <section class="mb-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-lg bg-emerald-700 p-5"><div class="font-bold">Mesas Livres</div><div class="text-5xl font-black">{{ mesasLivres }}</div></div>
            <div class="rounded-lg bg-red-700 p-5"><div class="font-bold">Mesas Ocupadas</div><div class="text-5xl font-black">{{ mesasOcupadas }}</div></div>
            <div class="rounded-lg bg-blue-700 p-5"><div class="font-bold">Vendas Hoje</div><div class="text-4xl font-black">{{ euros(vendasHoje) }}</div></div>
        </section>

        <!-- MAPA DA SALA -->
        <div class="rounded-lg border-2 border-gray-700 bg-gray-800 p-8">
            <h2 class="mb-6 text-2xl font-black">MAPA DA SALA</h2>
            
            <!-- GRID DO MAPA -->
            <div class="overflow-x-auto rounded-lg bg-gray-900 p-4">
                <div class="relative mx-auto w-full min-w-fit" style="width: 1000px; height: 600px;">
                    <div class="relative h-full w-full" style="aspect-ratio: 16/9;">
                        
                        <!-- ZONAS DO MAPA -->
                        <div 
                            v-for="zona in (modoEdicao ? zonaForm.zonas : zonas)" 
                            :key="zona.id"
                            class="absolute transform -translate-x-1/2 -translate-y-1/2 cursor-pointer rounded-lg border-2 p-2 text-center text-xs font-black transition-all"
                            :class="modoEdicao ? 'border-yellow-500 bg-yellow-900/50' : 'border-gray-600 bg-gray-700/50'"
                            :style="{
                                left: (zona.mapa_x || 50) + '%',
                                top: (zona.mapa_y || 50) + '%',
                                width: (zona.mapa_largura || 40) + 'px',
                                height: (zona.mapa_altura || 40) + 'px',
                            }"
                            @click="modoEdicao && (zonaEmEdicao = zonaEmEdicao === zona.id ? null : zona.id)"
                        >
                            {{ (props.zonas.find(z => z.id === zona.id)?.nome || 'Zona') }}
                            <div v-if="modoEdicao && zonaEmEdicao === zona.id" class="mt-2 space-y-1">
                                <div class="flex gap-1">
                                    <button @click.stop="alterarPosicao(zona, 'mapa_x', -5)" class="rounded bg-red-600 px-1 py-0.5 text-xs">←</button>
                                    <button @click.stop="alterarPosicao(zona, 'mapa_x', 5)" class="rounded bg-green-600 px-1 py-0.5 text-xs">→</button>
                                </div>
                                <div class="flex gap-1">
                                    <button @click.stop="alterarPosicao(zona, 'mapa_y', -5)" class="rounded bg-red-600 px-1 py-0.5 text-xs">↑</button>
                                    <button @click.stop="alterarPosicao(zona, 'mapa_y', 5)" class="rounded bg-green-600 px-1 py-0.5 text-xs">↓</button>
                                </div>
                                <div class="flex gap-1">
                                    <button @click.stop="alterarPosicao(zona, 'mapa_largura', -5)" class="rounded bg-blue-600 px-1 py-0.5 text-xs">-w</button>
                                    <button @click.stop="alterarPosicao(zona, 'mapa_largura', 5)" class="rounded bg-blue-600 px-1 py-0.5 text-xs">+w</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- MESAS -->
                        <div v-for="mesa in mesas" :key="mesa.id" class="absolute transform -translate-x-1/2 -translate-y-1/2 cursor-pointer">
                            <Link 
                                v-if="!modoEdicao"
                                :href="route('pos.rest.mesa', mesa.id)"
                                class="block rounded-lg border-2 p-2 text-center font-black transition-all hover:scale-110"
                                :class="cor(mesa)"
                                :style="{
                                    left: (mesa.mapa_x || 50) + '%',
                                    top: (mesa.mapa_y || 50) + '%',
                                    width: (mesa.mapa_largura || 40) + 'px',
                                    height: (mesa.mapa_altura || 40) + 'px',
                                }"
                            >
                                <span class="text-lg">{{ mesa.numero }}</span>
                                <span class="block text-xs">{{ mesa.capacidade }}p</span>
                            </Link>
                            <div 
                                v-else
                                class="block rounded-lg border-2 border-gray-600 bg-gray-700 p-2 text-center font-black opacity-50"
                                :style="{
                                    left: (mesa.mapa_x || 50) + '%',
                                    top: (mesa.mapa_y || 50) + '%',
                                    width: (mesa.mapa_largura || 40) + 'px',
                                    height: (mesa.mapa_altura || 40) + 'px',
                                }"
                            >
                                <span class="text-lg">{{ mesa.numero }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODO LISTA ALTERNATIVO -->
        <div class="mt-6">
            <Link :href="route('pos.rest.mesas')" class="block rounded-lg bg-emerald-600 p-8 text-center text-3xl font-black">📋 VER MESAS EM LISTA</Link>
        </div>
    </main>
</template>
