<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ mesas: Array });

const mesasMapa = ref([...(props.mesas ?? [])]);
const aAtualizar = ref(false);
const ultimaAtualizacao = ref(new Date());
let polling = null;

const estadoDot = {
    livre: 'bg-white border-2 border-[#8FA39A]',
    por_receber: 'bg-laranja',
    grupo: 'bg-roxo',
    ocupada: 'bg-verde',
    reservada: 'bg-azul',
};

const segmentoClass = {
    livre: 'bg-white',
    por_receber: 'bg-laranja',
    grupo: 'bg-roxo',
    ocupada: 'bg-verde',
    reservada: 'bg-azul',
};
// Vários grupos ao mesmo tempo: tons de roxo diferentes para os distinguir (o texto "Grupo" fica sempre escrito)
const coresGrupo = [
    'bg-roxo',
    'bg-[#5A2B78]',
    'bg-[#9B5CC4]',
    'bg-[#4A2266]',
    'bg-[#8A4FB0]',
    'bg-[#6A3590]',
];
const bordaMesa = {
    livre: 'border-[#8FA39A]',
    por_receber: 'border-laranja-texto',
    grupo: 'border-[#5A2B78]',
    ocupada: 'border-verde-escuro',
    reservada: 'border-[#1D428C]',
};
const legenda = [
    { estado: 'livre', label: 'Livre' },
    { estado: 'por_receber', label: 'Pedido por receber' },
    { estado: 'grupo', label: 'Grupo' },
    { estado: 'ocupada', label: 'Ocupada' },
    { estado: 'reservada', label: 'Reservada' },
];
const resumoCartoes = computed(() => [
    { n: resumo.value.livre, label: 'livres', borda: 'border-white' },
    { n: resumo.value.por_receber, label: 'por receber', borda: 'border-laranja' },
    { n: resumo.value.grupo, label: 'grupos', borda: 'border-roxo' },
    { n: resumo.value.ocupada, label: 'ocupadas', borda: 'border-verde-ok' },
    { n: resumo.value.reservada, label: 'reservadas', borda: 'border-azul' },
]);
// Mesa livre e sem submesas: fundo branco → texto escuro
const textoEscuro = (mesa) => !mesa?.submesas?.length && estadoMesa(mesa) === 'livre';

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

const letraSubmesa = (submesa) => submesa.designacao.replace(/^Mesa\s*/i, '');

const segmentosMesa = (mesa) => {
    if (mesa?.submesas?.length) {
        return mesa.submesas.map((submesa) => ({
            id: submesa.id,
            label: letraSubmesa(submesa),
            estado: estadoOperacional(submesa),
            grupoId: pedidoGrupo(submesa)?.id ?? null,
            capacidade: Number(submesa.capacidade || 1),
        }));
    }

    return [{
        id: mesa.id,
        label: mesa.numero,
        estado: estadoOperacional(mesa),
        grupoId: pedidoGrupo(mesa)?.id ?? null,
        capacidade: Number(mesa?.capacidade || 1),
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
    <main class="flex min-h-screen flex-col gap-5 bg-escuro px-6 py-6 font-sans tabular-nums text-white xl:px-10 xl:py-7">
        <header class="flex shrink-0 flex-wrap items-center justify-between gap-6">
            <div class="flex flex-col gap-1">
                <h1 class="text-5xl font-extrabold leading-none xl:text-[72px]">SALA</h1>
                <p class="text-lg font-semibold text-escuro-inativo xl:text-2xl">Visão de entrega · atualização automática</p>
            </div>
            <div class="flex flex-wrap gap-3.5">
                <div
                    v-for="cartao in resumoCartoes"
                    :key="cartao.label"
                    class="flex min-w-[150px] flex-col justify-center rounded-2xl border-l-[12px] bg-[#233029] px-5 py-2.5 xl:h-[104px] xl:min-w-[190px]"
                    :class="cartao.borda"
                >
                    <span class="text-4xl font-extrabold leading-none xl:text-[52px]">{{ cartao.n }}</span>
                    <span class="text-lg font-bold text-escuro-inativo xl:text-2xl">{{ cartao.label }}</span>
                </div>
            </div>
        </header>

        <section aria-label="Mapa da sala" class="relative min-h-[720px] flex-1 overflow-hidden rounded-[22px] bg-fundo">
            <div data-sala-mapa class="relative h-full min-h-[720px] w-full">
                <div class="absolute inset-x-[2%] inset-y-[4%] rounded-[10px] border-4 border-suave"></div>
                <div class="absolute left-[2%] top-[38%] h-[18%] w-1 bg-fundo"></div>
                <div class="absolute left-[2%] top-[63%] h-[14%] w-1 bg-fundo"></div>
                <div class="absolute right-[2%] top-[12%] h-[16%] w-1 bg-fundo"></div>
                <div class="absolute bottom-[8%] right-[2%] h-[12%] w-1 bg-fundo"></div>

                <div class="absolute left-[3%] top-[46%] -rotate-90 text-sm font-extrabold uppercase text-suave-2 xl:text-lg">WC H.</div>
                <div class="absolute left-[3%] top-[60%] -rotate-90 text-sm font-extrabold uppercase text-suave-2 xl:text-lg">WC M.</div>
                <div class="absolute left-[11%] top-[14%] flex h-[18%] w-[10%] items-center justify-center rounded-lg border-[3px] border-suave-2 bg-white p-2 text-center text-sm font-extrabold uppercase text-suave [writing-mode:vertical-rl] xl:text-[22px]">Sobremesas</div>
                <div class="absolute bottom-[8%] left-[9%] flex h-[17%] w-[8%] items-center justify-center rounded-lg border-[3px] border-suave-2 bg-white p-2 text-center text-sm font-extrabold uppercase text-suave [writing-mode:vertical-rl] xl:text-[22px]">Caixa / Bebidas</div>
                <div class="absolute bottom-[1%] left-[19%] rounded-lg bg-escuro px-4 py-1.5 text-base font-extrabold uppercase text-white xl:text-xl">Entrada</div>
                <div class="absolute right-[5%] top-[31%] rounded-lg border-[3px] border-suave-2 bg-white px-2 py-4 text-base font-extrabold uppercase text-suave [writing-mode:vertical-rl] xl:text-[22px]">Palco</div>

                <div
                    v-for="mesa in mesasMapa"
                    :key="mesa.id"
                    class="absolute overflow-hidden rounded-xl border-[3px] text-left shadow-[0_4px_12px_rgba(22,32,28,.18)]"
                    :class="bordaMesa[estadoMesa(mesa)]"
                    :style="mesaStyle(mesa)"
                    :title="textoPedidos(mesa)"
                >
                    <div class="absolute inset-0 flex" :class="mesa.mapa_altura > mesa.mapa_largura ? 'flex-col' : 'flex-row'">
                        <div
                            v-for="segmento in segmentosMesa(mesa)"
                            :key="segmento.id"
                            class="min-h-0 min-w-0 border-white/70"
                            :class="[segmentoClasse(segmento), mesa.mapa_altura > mesa.mapa_largura ? 'border-b-2 last:border-b-0' : 'border-r-2 last:border-r-0']"
                            :style="{ flex: segmento.capacidade }"
                        ></div>
                    </div>

                    <div class="relative z-10 flex h-full flex-col justify-between overflow-hidden px-[0.6vw] py-[0.4vw]" :class="textoEscuro(mesa) ? 'text-tinta' : 'text-white'">
                        <div class="flex items-start justify-between gap-1">
                            <span class="flex items-baseline gap-[0.4vw] whitespace-nowrap leading-none">
                                <span class="text-[clamp(10px,1.1vw,24px)] font-bold">Mesa</span>
                                <span class="text-[clamp(16px,2.6vw,60px)] font-extrabold">{{ mesa.numero }}</span>
                            </span>
                            <span v-if="mesa.submesas.length" class="h-3 w-3 shrink-0 rounded-full ring-2 ring-white" :class="estadoDot[estadoMesa(mesa)]"></span>
                        </div>

                        <div v-if="mesa.submesas.length" class="grid gap-0.5" :class="mesa.submesas.length > 3 ? 'grid-cols-3' : 'grid-cols-2'">
                            <span v-for="segmento in segmentosMesa(mesa)" :key="segmento.id" class="truncate rounded bg-escuro/70 px-1 py-0.5 text-center text-[clamp(9px,0.8vw,18px)] font-extrabold text-white">
                                {{ segmento.label }} · {{ estadoLabel[segmento.estado] }}
                            </span>
                        </div>
                        <div v-else class="flex flex-col gap-0.5">
                            <span class="whitespace-nowrap text-[clamp(9px,1vw,22px)] font-extrabold uppercase">{{ estadoLabel[estadoMesa(mesa)] }}</span>
                            <span v-if="pedidosAtivos(mesa)[0]" class="whitespace-nowrap text-[clamp(9px,0.9vw,20px)] font-semibold">
                                #{{ pedidosAtivos(mesa)[0].id }} · {{ horaPedido(pedidosAtivos(mesa)[0]) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="flex shrink-0 flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap gap-7 text-lg font-bold text-escuro-inativo xl:text-2xl">
                <span v-for="item in legenda" :key="item.estado" class="flex items-center gap-2.5">
                    <span class="h-[22px] w-[22px] rounded-md" :class="item.estado === 'livre' ? 'border-2 border-[#8FA39A] bg-white' : segmentoClass[item.estado]"></span>
                    {{ item.label }}
                </span>
            </div>
            <span class="text-base font-bold text-[#8FA39A] xl:text-[22px]">{{ aAtualizar ? 'A atualizar' : 'Último refresh' }}: {{ hora }}</span>
        </footer>
    </main>
</template>
