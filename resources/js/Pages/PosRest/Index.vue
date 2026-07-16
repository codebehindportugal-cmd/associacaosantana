<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import ChamarComissaoModal from '@/Components/ChamarComissaoModal.vue';
import ChamadaFuncionarioAlert from '@/Components/ChamadaFuncionarioAlert.vue';
import ComissaoChamadasAlert from '@/Components/ComissaoChamadasAlert.vue';
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
const chamandoComissao = ref(false);

const grupos = computed(() => (props.mesas ?? []).reduce((acc, m) => { const k = m.localizacao || 'Sala'; if (!acc[k]) acc[k] = []; acc[k].push(m); return acc; }, {}));
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
    <ChamadaFuncionarioAlert />
    <ComissaoChamadasAlert />
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0"><h1 class="text-xl font-black sm:text-3xl">POS RESTAURANTE</h1><p class="truncate text-sm font-bold text-gray-300 sm:text-base">{{ posNome }} · {{ agora.toLocaleTimeString('pt-PT') }}</p></div>
            <div class="flex w-full gap-2 sm:w-auto">
                <button class="flex-1 rounded-lg bg-amber-500 px-3 py-2 text-sm font-black text-black sm:flex-none sm:px-4 sm:py-3" @click="chamandoComissao = true">🎉 COMISSÃO</button>
                <button class="flex-1 rounded-lg bg-red-600 px-3 py-2 text-sm font-black sm:flex-none sm:px-5 sm:py-3" @click="logout">LOGOUT</button>
            </div>
        </header>
        
        <!-- ESTATÍSTICAS -->
        <section class="mb-6 grid gap-4 md:grid-cols-2">
            <div class="rounded-lg bg-emerald-700 p-5"><div class="font-bold">Mesas Livres</div><div class="text-5xl font-black">{{ mesasLivres }}</div></div>
            <div class="rounded-lg bg-red-700 p-5"><div class="font-bold">Mesas Ocupadas</div><div class="text-5xl font-black">{{ mesasOcupadas }}</div></div>
        </section>

        <!-- MODO LISTA ALTERNATIVO -->
        <div class="mt-6 grid gap-3">
            <Link :href="route('pos.rest.mesas')" class="block rounded-lg bg-emerald-600 p-8 text-center text-3xl font-black">VER MESAS EM LISTA</Link>
        </div>

        <ChamarComissaoModal
            v-if="chamandoComissao"
            :operador-nome="posNome"
            @fechar="chamandoComissao = false"
        />
    </main>
</template>
