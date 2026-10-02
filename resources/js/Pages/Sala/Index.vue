<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ mesas: Array, zonas: Array });

const mesasMapa = ref([...(props.mesas ?? [])]);
const zonasMapa = ref([...(props.zonas ?? [])]);
let polling = null;

const segmentoClass = {
    livre: 'bg-white text-tinta',
    por_receber: 'bg-laranja text-white',
    grupo: 'bg-roxo text-white',
    ocupada: 'bg-verde text-white',
    reservada: 'bg-azul text-white',
};
// Cada grupo (pedido de várias mesas) tem uma cor própria para se distinguir no mapa
const coresGrupo = [
    'bg-roxo text-white',
    'bg-[#0E7490] text-white',
    'bg-[#A21C7A] text-white',
    'bg-[#4D7C0F] text-white',
    'bg-[#9A6B00] text-white',
    'bg-[#1E4592] text-white',
    'bg-[#B4233C] text-white',
    'bg-[#0F766E] text-white',
];

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
const pedidoGrupo = (mesa) => (mesa?.pedidos_grupo ?? [])[0] ?? null;
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
            grupoId: pedidoGrupo(submesa)?.id ?? null,
            capacidade: Number(submesa.capacidade || 1),
            pedidos: pedidosAtivos(submesa),
        }));
    }

    return [{
        id: mesa.id,
        label: mesa.numero,
        estado: estadoOperacional(mesa),
        grupoId: pedidoGrupo(mesa)?.id ?? null,
        capacidade: Number(mesa?.capacidade || 1),
        pedidos: pedidosAtivos(mesa),
    }];
};
const segmentoClasse = (segmento) => {
    if (segmento.estado === 'grupo' && segmento.grupoId) {
        return coresGrupo[Number(segmento.grupoId) % coresGrupo.length];
    }

    return segmentoClass[segmento.estado] ?? segmentoClass.livre;
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
const zonaStyle = (zona) => ({
    left: `${zona.mapa_x}%`,
    top: `${zona.mapa_y}%`,
    width: `${zona.mapa_largura}%`,
    height: `${zona.mapa_altura}%`,
});
const zonaFundo = (zona) => ({
    palco: 'bg-[#FBF3E6]',
    balcao: 'bg-[#EEF2FB]',
    cozinha: 'bg-[#F1F3EF]',
    wc: 'bg-[#EEF5FA]',
    entrada: 'bg-white',
    porta: 'bg-white',
    texto: 'bg-transparent border-transparent',
}[zona.tipo] ?? 'bg-fundo/70');
const cartoesResumo = [
    ['livre', 'border-2 border-[#8FA39A] bg-white text-tinta', 'text-suave'],
    ['por_receber', 'bg-laranja text-white', ''],
    ['ocupada', 'bg-verde text-white', ''],
    ['grupo', 'bg-roxo text-white', ''],
    ['reservada', 'bg-azul text-white', ''],
];
const rodapeMesa = (mesa) => {
    if (mesa.submesas?.length) {
        return `Mesa ${mesa.numero} · ${segmentosMesa(mesa).map((segmento) => segmento.label).join(' ')}`;
    }
    const pedido = pedidosAtivos(mesa)[0];
    return pedido ? `#${pedido.id} - ${horaPedido(pedido)}` : estadoLabel[estadoMesa(mesa)];
};
const zonaVertical = (zona) => Number(zona.mapa_altura || 0) > Number(zona.mapa_largura || 0);
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
watch(() => props.zonas, (zonas) => {
    zonasMapa.value = [...(zonas ?? [])];
});

onMounted(() => {
    polling = setInterval(() => {
        router.reload({ only: ['mesas', 'zonas'], preserveScroll: true });
    }, 3000);
});

onBeforeUnmount(() => {
    clearInterval(polling);
});
</script>

<template>
    <AppLayout>
    <div class="font-sans text-tinta tabular-nums">
        <div class="flex flex-col gap-[18px]">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-[30px] font-extrabold leading-tight">Sala ao vivo</h1>
                    <p class="flex items-center gap-2 text-[15px] text-suave">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-verde-ok"></span>
                        Atualiza sozinho a cada 3 segundos. Passa por cima de uma mesa para ver os pedidos.
                    </p>
                </div>
                <Link :href="route('mesas.index')" class="inline-flex h-12 items-center gap-2 rounded-[10px] border border-linha-forte bg-white px-[18px] text-[15px] font-bold text-tinta hover:bg-fundo">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16zM14 6l4 4" /></svg>
                    Gerir mesas e mapa
                </Link>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div v-for="[chave, classe, rotulo] in cartoesResumo" :key="chave" class="flex flex-col gap-0.5 rounded-[14px] px-4 py-3.5" :class="classe">
                    <span class="text-sm font-bold" :class="rotulo">{{ estadoLabel[chave] }}</span>
                    <span class="text-[30px] font-extrabold leading-tight">{{ resumo[chave] }}</span>
                </div>
            </div>

            <p class="text-sm text-suave-2 md:hidden">Desliza o mapa para o lado para ver a sala toda.</p>
            <section aria-label="Mapa da sala" class="overflow-x-auto rounded-[14px] border border-linha bg-white p-3">
                <div data-sala-mapa class="relative h-[72vh] min-h-[620px] w-full min-w-[760px] overflow-hidden rounded-[10px] border border-linha-fraca bg-[#FAFBF8]">
                    <div
                        v-for="zona in zonasMapa"
                        :key="`zona-${zona.id}`"
                        class="absolute flex items-center justify-center rounded-lg border border-[#C9CEC7] p-1 text-center text-[13px] font-extrabold uppercase tracking-[.06em] text-suave"
                        :class="zonaFundo(zona)"
                        :style="zonaStyle(zona)"
                    >
                        <span :class="zonaVertical(zona) ? '[writing-mode:vertical-rl]' : ''">{{ zona.nome }}</span>
                    </div>

                    <div
                        v-for="mesa in mesasMapa"
                        :key="mesa.id"
                        class="absolute flex flex-col overflow-hidden rounded-lg bg-white"
                        :class="estadoMesa(mesa) === 'livre' ? 'border-2 border-[#8FA39A]' : 'border-2 border-transparent'"
                        :style="mesaStyle(mesa)"
                        :title="textoPedidos(mesa)"
                    >
                        <div class="flex min-h-0 flex-grow" :class="mesa.mapa_altura > mesa.mapa_largura ? 'flex-col' : 'flex-row'">
                            <span
                                v-for="segmento in segmentosMesa(mesa)"
                                :key="segmento.id"
                                class="flex min-h-0 min-w-0 items-center justify-center text-base font-extrabold"
                                :class="[segmentoClasse(segmento), mesa.mapa_altura > mesa.mapa_largura ? 'border-b-2 border-white last:border-b-0' : 'border-r-2 border-white last:border-r-0']"
                                :style="{ flex: segmento.capacidade }"
                            >{{ segmento.label }}</span>
                        </div>
                        <span class="truncate border-t border-linha-fraca bg-white px-1 py-0.5 text-center text-[11px] font-bold text-tinta">{{ rodapeMesa(mesa) }}</span>
                    </div>
                </div>
            </section>
        </div>
    </div>
    </AppLayout>
</template>
