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
    <main class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums">
        <header class="flex shrink-0 flex-wrap items-center justify-between gap-2 bg-escuro px-4 py-2.5 text-white sm:px-6 lg:h-16 lg:py-0">
            <div class="flex min-w-0 items-baseline gap-4">
                <h1 class="text-xl font-extrabold">POS Restaurante</h1>
                <span class="truncate text-sm text-escuro-inativo">{{ posNome }} · {{ agora.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' }) }}</span>
            </div>
            <div class="flex gap-2.5">
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-laranja px-4 text-[15px] font-bold text-white" @click="chamandoComissao = true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
                    Chamar comissão
                </button>
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-bold text-white" @click="logout">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></svg>
                    Sair
                </button>
            </div>
        </header>

        <div class="flex flex-1 flex-col gap-5 p-4 sm:p-10 sm:pt-7">
            <section class="grid gap-5 md:grid-cols-2">
                <div class="flex items-center gap-5 rounded-[14px] border border-linha bg-white px-7 py-5">
                    <span class="h-6 w-6 shrink-0 rounded-md border-2 border-suave bg-white" aria-hidden="true"></span>
                    <div>
                        <span class="block text-lg font-semibold text-suave">Mesas livres</span>
                        <span class="block text-6xl font-extrabold leading-none">{{ mesasLivres }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-5 rounded-[14px] border border-linha bg-white px-7 py-5">
                    <span class="h-6 w-6 shrink-0 rounded-md bg-verde" aria-hidden="true"></span>
                    <div>
                        <span class="block text-lg font-semibold text-suave">Mesas ocupadas</span>
                        <span class="block text-6xl font-extrabold leading-none">{{ mesasOcupadas }}</span>
                    </div>
                </div>
            </section>

            <Link :href="route('pos.rest.mesas')" class="flex min-h-[200px] flex-1 items-center justify-center gap-7 rounded-[14px] bg-verde p-8 text-white hover:bg-verde-escuro hover:text-white">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0"><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></svg>
                <span>
                    <span class="block text-4xl font-extrabold sm:text-5xl">Ver mesas</span>
                    <span class="mt-1 block text-lg text-verde-claro">Abrir uma mesa, lançar pedidos e fechar a conta</span>
                </span>
            </Link>

            <Link :href="route('pos.rest.historico')" class="flex min-h-[76px] items-center justify-between rounded-[14px] border border-linha bg-white px-6 text-tinta hover:text-tinta">
                <span class="flex items-center gap-3 text-xl font-bold">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                    Histórico do dia
                </span>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6" /></svg>
            </Link>
        </div>

        <ChamarComissaoModal
            v-if="chamandoComissao"
            :operador-nome="posNome"
            @fechar="chamandoComissao = false"
        />
    </main>
</template>
