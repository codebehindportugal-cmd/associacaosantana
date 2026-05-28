<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ mesas: Array });

const mesasMapa = ref([...(props.mesas ?? [])]);
const aAtualizar = ref(false);
const ultimaAtualizacao = ref(new Date());
let polling = null;

const estadoDot = {
    livre: 'bg-emerald-400',
    por_receber: 'bg-orange-400',
    grupo: 'bg-violet-500',
    ocupada: 'bg-red-500',
    reservada: 'bg-sky-400',
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
    por_receber: 'Por receber',
    grupo: 'Grupo',
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

const letraSubmesa = (submesa) => submesa.designacao.replace(/^Mesa\s*/i, '');

const segmentosMesa = (mesa) => {
    if (mesa?.submesas?.length) {
        return mesa.submesas.map((submesa) => ({
            id: submesa.id,
            label: letraSubmesa(submesa),
            estado: estadoOperacional(submesa),
            capacidade: Number(submesa.capacidade || 1),
        }));
    }

    return [{
        id: mesa.id,
        label: mesa.numero,
        estado: estadoOperacional(mesa),
        capacidade: Number(mesa?.capacidade || 1),
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

const hora = computed(() => ultimaAtualizacao.value.toLocaleTimeString('pt-PT', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
}));

const atualizar = () => {
    aAtualizar.value = true;
    router.reload({
        only: ['mesas'],
        preserveScroll: true,
        onFinish: () => {
            aAtualizar.value = false;
            ultimaAtualizacao.value = new Date();
        },
    });
};

watch(() => props.mesas, (mesas) => {
    mesasMapa.value = [...(mesas ?? [])];
});

onMounted(() => {
    polling = setInterval(atualizar, 5000);
});

onBeforeUnmount(() => {
    clearInterval(polling);
});
</script>

<template>
    <main class="min-h-screen bg-[#161922] p-4 text-white sm:p-6">
        <header class="mb-4 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-4xl font-black tracking-normal sm:text-5xl">SALA</h1>
                <p class="mt-1 text-sm font-semibold text-white/60">Visão de entrega · atualização automática</p>
            </div>
            <div class="grid grid-cols-2 gap-2 text-sm font-black sm:grid-cols-5">
                <div class="rounded-md bg-emerald-500/20 px-3 py-2 text-emerald-100">{{ resumo.livre }} livres</div>
                <div class="rounded-md bg-orange-500/20 px-3 py-2 text-orange-100">{{ resumo.por_receber }} por receber</div>
                <div class="rounded-md bg-violet-500/20 px-3 py-2 text-violet-100">{{ resumo.grupo }} grupos</div>
                <div class="rounded-md bg-red-500/20 px-3 py-2 text-red-100">{{ resumo.ocupada }} ocupadas</div>
                <div class="rounded-md bg-sky-500/20 px-3 py-2 text-sky-100">{{ resumo.reservada }} reservadas</div>
            </div>
        </header>

        <div class="mb-4 grid gap-2 text-sm font-bold sm:grid-cols-5">
            <div class="flex items-center gap-2 rounded-md bg-white/10 px-3 py-2"><span class="h-3 w-3 rounded-full bg-emerald-400"></span>Livre</div>
            <div class="flex items-center gap-2 rounded-md bg-white/10 px-3 py-2"><span class="h-3 w-3 rounded-full bg-orange-400"></span>Pedido por receber</div>
            <div class="flex items-center gap-2 rounded-md bg-white/10 px-3 py-2"><span class="h-3 w-3 rounded-full bg-violet-500"></span>Mesa grande / grupo</div>
            <div class="flex items-center gap-2 rounded-md bg-white/10 px-3 py-2"><span class="h-3 w-3 rounded-full bg-red-500"></span>Ocupada</div>
            <div class="flex items-center gap-2 rounded-md bg-white/10 px-3 py-2"><span class="h-3 w-3 rounded-full bg-sky-400"></span>Reservada</div>
        </div>

        <section class="rounded-lg border border-white/10 bg-white/5 p-3">
            <div data-sala-mapa class="relative h-[78vh] min-h-[720px] w-full overflow-hidden rounded-lg bg-[#f7f5ef] shadow-2xl ring-1 ring-white/10">
                <div class="absolute inset-x-[2%] inset-y-[4%] rounded-md border-[3px] border-slate-900/75"></div>
                <div class="absolute left-[2%] top-[38%] h-[18%] w-[2px] bg-[#f7f5ef]"></div>
                <div class="absolute left-[2%] top-[63%] h-[14%] w-[2px] bg-[#f7f5ef]"></div>
                <div class="absolute right-[2%] top-[12%] h-[16%] w-[2px] bg-[#f7f5ef]"></div>
                <div class="absolute right-[2%] bottom-[8%] h-[12%] w-[2px] bg-[#f7f5ef]"></div>

                <div class="absolute left-[3%] top-[46%] -rotate-90 text-xs font-black uppercase text-slate-700">WC H.</div>
                <div class="absolute left-[3%] top-[60%] -rotate-90 text-xs font-black uppercase text-slate-700">WC M.</div>
                <div class="absolute left-[11%] top-[14%] h-[18%] w-[10%] rounded-sm border-[3px] border-slate-800/70 bg-white/40 p-2 text-center text-xs font-black uppercase text-slate-900 [writing-mode:vertical-rl]">Sobremesas</div>
                <div class="absolute bottom-[8%] left-[9%] h-[17%] w-[8%] rounded-sm border-[3px] border-slate-800/70 bg-white/40 p-2 text-center text-xs font-black uppercase text-slate-900 [writing-mode:vertical-rl]">Caixa / Bebidas</div>
                <div class="absolute bottom-[2%] left-[19%] rounded-md bg-white/90 px-3 py-2 text-xs font-black uppercase text-slate-700 shadow-sm">Entrada</div>
                <div class="absolute right-[5%] top-[31%] rounded-md bg-white/90 px-3 py-2 text-xs font-black uppercase text-slate-700 shadow-sm [writing-mode:vertical-rl]">Palco</div>

                <div
                    v-for="mesa in mesasMapa"
                    :key="mesa.id"
                    class="absolute overflow-hidden rounded-md border-2 border-slate-950 bg-white text-left shadow-lg"
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
                            <span class="rounded bg-slate-950/65 px-1 text-[10px] font-black">{{ mesa.numero }}</span>
                            <span class="h-2.5 w-2.5 rounded-full ring-1 ring-white" :class="estadoDot[estadoMesa(mesa)]"></span>
                        </div>

                        <div v-if="mesa.submesas.length" class="grid gap-0.5" :class="mesa.submesas.length > 3 ? 'grid-cols-3' : 'grid-cols-2'">
                            <span v-for="segmento in segmentosMesa(mesa)" :key="segmento.id" class="rounded bg-slate-950/55 px-1 py-0.5 text-[9px] font-black">
                                {{ segmento.label }}
                            </span>
                        </div>
                        <div v-else class="grid gap-0.5">
                            <span class="rounded bg-slate-950/55 px-1 py-0.5 text-[9px] font-black">{{ estadoLabel[estadoMesa(mesa)] }}</span>
                            <span v-if="pedidosAtivos(mesa)[0]" class="rounded bg-slate-950/55 px-1 py-0.5 text-[9px] font-bold">
                                #{{ pedidosAtivos(mesa)[0].id }} · {{ horaPedido(pedidosAtivos(mesa)[0]) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="fixed bottom-3 right-4 rounded-md bg-black/40 px-3 py-2 text-xs font-bold text-white/70">
            {{ aAtualizar ? 'A atualizar' : 'Último refresh' }}: {{ hora }}
        </div>
    </main>
</template>
