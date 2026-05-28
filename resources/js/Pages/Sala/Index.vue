<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ mesas: Array });

const mesasMapa = ref([...(props.mesas ?? [])]);
let polling = null;

const estadoDot = {
    livre: 'bg-emerald-500',
    por_receber: 'bg-orange-500',
    grupo: 'bg-violet-600',
    ocupada: 'bg-red-600',
    reservada: 'bg-sky-500',
};

const segmentoClass = {
    livre: 'bg-emerald-500/90',
    por_receber: 'bg-orange-500/90',
    grupo: 'bg-violet-600/95',
    ocupada: 'bg-red-600/95',
    reservada: 'bg-sky-500/90',
};

const estadoLabel = {
    livre: 'Livre',
    por_receber: 'Pedido por receber',
    grupo: 'Mesa grande',
    ocupada: 'Ocupada',
    reservada: 'Reservada',
};

const pedidosAtivos = (mesa) => [
    ...(mesa?.pedidos ?? []),
    ...(mesa?.pedidos_grupo ?? []),
];
const mesaGrande = (mesa) => Number(mesa?.capacidade ?? 0) > 10;

const estadoOperacional = (mesa) => {
    const pedidos = pedidosAtivos(mesa);

    if (mesa?.pedidos_grupo?.length) {
        return 'grupo';
    }

    if (pedidos.some((pedido) => pedido.estado === 'pendente')) {
        return 'por_receber';
    }

    if (pedidos.length && mesaGrande(mesa)) {
        return 'grupo';
    }

    if (pedidos.length || mesa?.estado === 'ocupada') {
        return 'ocupada';
    }

    if (mesa?.estado === 'reservada') {
        return 'reservada';
    }

    return 'livre';
};

const segmentosMesa = (mesa) => {
    if (mesa?.submesas?.length) {
        return mesa.submesas.map((submesa) => ({
            id: submesa.id,
            label: letraSubmesa(submesa),
            estado: estadoOperacional(submesa),
            capacidade: Number(submesa.capacidade || 1),
            pedidos: pedidosAtivos(submesa),
        }));
    }

    return [{
        id: mesa.id,
        label: mesa.numero,
        estado: estadoOperacional(mesa),
        capacidade: Number(mesa?.capacidade || 1),
        pedidos: pedidosAtivos(mesa),
    }];
};

const estadoMesa = (mesa) => {
    const estados = segmentosMesa(mesa).map((segmento) => segmento.estado);

    if (estados.includes('por_receber')) {
        return 'por_receber';
    }

    if (estados.includes('grupo')) {
        return 'grupo';
    }

    if (estados.includes('ocupada')) {
        return 'ocupada';
    }

    if (estados.includes('reservada')) {
        return 'reservada';
    }

    return 'livre';
};

const resumo = computed(() => mesasMapa.value.reduce((total, mesa) => {
    total[estadoMesa(mesa)] += 1;
    return total;
}, {
    livre: 0,
    por_receber: 0,
    grupo: 0,
    ocupada: 0,
    reservada: 0,
}));

const mesaStyle = (mesa) => ({
    left: `${mesa.mapa_x}%`,
    top: `${mesa.mapa_y}%`,
    width: `${mesa.mapa_largura}%`,
    height: `${mesa.mapa_altura}%`,
});

const letraSubmesa = (submesa) => submesa.designacao.replace(/^Mesa\s*/i, '');

const pedidosMesa = (mesa) => [
    ...pedidosAtivos(mesa),
    ...((mesa?.submesas ?? []).flatMap((submesa) => pedidosAtivos(submesa))),
];

const textoPedidos = (mesa) => {
    const pedidos = pedidosMesa(mesa);

    if (!pedidos.length) {
        return 'Sem pedidos ativos';
    }

    return pedidos
        .map((pedido) => `#${pedido.id}${pedido.operador_nome ? ` - ${pedido.operador_nome}` : ''}`)
        .join(' | ');
};

const horaPedido = (pedido) => new Date(pedido.created_at).toLocaleTimeString('pt-PT', {
    hour: '2-digit',
    minute: '2-digit',
});

watch(() => props.mesas, (mesas) => {
    mesasMapa.value = [...(mesas ?? [])];
});

onMounted(() => {
    polling = setInterval(() => {
        router.reload({ only: ['mesas'], preserveScroll: true });
    }, 10000);
});

onBeforeUnmount(() => {
    clearInterval(polling);
});
</script>

<template>
    <AppLayout>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Sala</h1>
                <p class="mt-1 text-sm text-slate-500">Estado atual das mesas e submesas.</p>
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 font-bold text-emerald-800">{{ resumo.livre }} livres</div>
                <div class="rounded-md border border-orange-200 bg-orange-50 px-3 py-2 font-bold text-orange-800">{{ resumo.por_receber }} por receber</div>
                <div class="rounded-md border border-violet-200 bg-violet-50 px-3 py-2 font-bold text-violet-800">{{ resumo.grupo }} grupos</div>
                <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 font-bold text-red-800">{{ resumo.ocupada }} ocupadas</div>
                <div class="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 font-bold text-sky-800">{{ resumo.reservada }} reservadas</div>
            </div>
        </div>

        <div class="mb-4 grid gap-3 rounded-md bg-white p-4 text-sm shadow-sm sm:grid-cols-5">
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-emerald-500"></span>Livre</div>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-orange-500"></span>Pedido por receber</div>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-violet-600"></span>Mesa grande / grupo</div>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-red-600"></span>Ocupada</div>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-sky-500"></span>Reservada</div>
        </div>

        <section class="rounded-lg border border-slate-300 bg-slate-100 p-3">
            <div data-sala-mapa class="relative h-[78vh] min-h-[720px] w-full overflow-hidden rounded-lg bg-[#f7f5ef] shadow-inner ring-1 ring-slate-300">
                <div class="absolute inset-x-[2%] inset-y-[4%] rounded-md border-[3px] border-slate-800/70"></div>
                <div class="absolute left-[2%] top-[38%] h-[18%] w-[2px] bg-[#f7f5ef]"></div>
                <div class="absolute left-[2%] top-[63%] h-[14%] w-[2px] bg-[#f7f5ef]"></div>
                <div class="absolute right-[2%] top-[12%] h-[16%] w-[2px] bg-[#f7f5ef]"></div>
                <div class="absolute right-[2%] bottom-[8%] h-[12%] w-[2px] bg-[#f7f5ef]"></div>

                <div class="absolute left-[3%] top-[46%] -rotate-90 text-xs font-black uppercase text-slate-700">WC H.</div>
                <div class="absolute left-[3%] top-[60%] -rotate-90 text-xs font-black uppercase text-slate-700">WC M.</div>
                <div class="absolute left-[11%] top-[14%] h-[18%] w-[10%] rounded-sm border-[3px] border-slate-800/70 bg-white/40 p-2 text-center text-xs font-black uppercase [writing-mode:vertical-rl]">Sobremesas</div>
                <div class="absolute bottom-[8%] left-[9%] h-[17%] w-[8%] rounded-sm border-[3px] border-slate-800/70 bg-white/40 p-2 text-center text-xs font-black uppercase [writing-mode:vertical-rl]">Caixa / Bebidas</div>
                <div class="absolute bottom-[2%] left-[19%] rounded-md bg-white/90 px-3 py-2 text-xs font-black uppercase text-slate-700 shadow-sm">Entrada</div>
                <div class="absolute right-[5%] top-[31%] rounded-md bg-white/90 px-3 py-2 text-xs font-black uppercase text-slate-700 shadow-sm [writing-mode:vertical-rl]">Palco</div>

                <div
                    v-for="mesa in mesasMapa"
                    :key="mesa.id"
                    class="absolute overflow-hidden rounded-md border-2 border-slate-900 bg-white text-left shadow-sm"
                    :style="mesaStyle(mesa)"
                    :title="textoPedidos(mesa)"
                >
                    <div class="absolute inset-0 flex" :class="mesa.mapa_altura > mesa.mapa_largura ? 'flex-col' : 'flex-row'">
                        <div
                            v-for="segmento in segmentosMesa(mesa)"
                            :key="segmento.id"
                            class="min-h-0 min-w-0 border-white/70"
                            :class="[segmentoClass[segmento.estado] ?? segmentoClass.livre, mesa.mapa_altura > mesa.mapa_largura ? 'border-b last:border-b-0' : 'border-r last:border-r-0']"
                            :style="{ flex: segmento.capacidade }"
                        ></div>
                    </div>

                    <div class="relative z-10 flex h-full flex-col justify-between p-1 text-center text-white drop-shadow">
                        <div class="flex items-start justify-between gap-1">
                            <span class="rounded bg-slate-950/60 px-1 text-[10px] font-black">{{ mesa.numero }}</span>
                            <span class="h-2.5 w-2.5 rounded-full ring-1 ring-white" :class="estadoDot[estadoMesa(mesa)]"></span>
                        </div>

                        <div v-if="mesa.submesas.length" class="grid gap-0.5" :class="mesa.submesas.length > 3 ? 'grid-cols-3' : 'grid-cols-2'">
                            <span v-for="segmento in segmentosMesa(mesa)" :key="segmento.id" class="rounded bg-slate-950/50 px-1 py-0.5 text-[9px] font-black">
                                {{ segmento.label }}
                            </span>
                        </div>
                        <div v-else class="grid gap-0.5">
                            <span class="rounded bg-slate-950/50 px-1 py-0.5 text-[9px] font-black">{{ estadoLabel[estadoMesa(mesa)] }}</span>
                            <span v-if="pedidosAtivos(mesa)[0]" class="rounded bg-slate-950/50 px-1 py-0.5 text-[9px] font-bold">
                                #{{ pedidosAtivos(mesa)[0].id }} · {{ horaPedido(pedidosAtivos(mesa)[0]) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
