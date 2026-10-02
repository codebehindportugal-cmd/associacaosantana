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
    <!-- Ecrã de parede: ocupa sempre exatamente o ecrã, sem scroll -->
    <main class="flex h-[100dvh] flex-col gap-[1.4vh] overflow-hidden bg-escuro px-[1.6vw] py-[1.6vh] font-sans tabular-nums text-white">
        <header class="flex shrink-0 items-center justify-between gap-[2vw]">
            <div class="flex min-w-0 flex-col">
                <h1 class="text-[min(6.5vh,4.5vw)] font-extrabold leading-none">SALA</h1>
                <p class="truncate text-[min(2.2vh,1.4vw)] font-semibold text-escuro-inativo">Visão de entrega · atualização automática</p>
            </div>
            <div class="flex shrink-0 gap-[0.8vw]">
                <div
                    v-for="cartao in resumoCartoes"
                    :key="cartao.label"
                    class="flex min-w-[9vw] flex-col justify-center rounded-[1.4vh] border-l-[0.8vh] bg-[#233029] px-[1vw] py-[0.8vh]"
                    :class="cartao.borda"
                >
                    <span class="text-[min(5vh,3.2vw)] font-extrabold leading-none">{{ cartao.n }}</span>
                    <span class="whitespace-nowrap text-[min(2vh,1.25vw)] font-bold text-escuro-inativo">{{ cartao.label }}</span>
                </div>
            </div>
        </header>

        <section aria-label="Mapa da sala" class="relative min-h-0 flex-1 overflow-hidden rounded-[2vh] bg-fundo">
            <div data-sala-mapa class="relative h-full w-full">
                <div class="absolute inset-x-[2%] inset-y-[4%] rounded-[10px] border-4 border-suave"></div>
                <div class="absolute left-[2%] top-[38%] h-[18%] w-1 bg-fundo"></div>
                <div class="absolute left-[2%] top-[63%] h-[14%] w-1 bg-fundo"></div>
                <div class="absolute right-[2%] top-[12%] h-[16%] w-1 bg-fundo"></div>
                <div class="absolute bottom-[8%] right-[2%] h-[12%] w-1 bg-fundo"></div>

                <div class="absolute left-[3%] top-[46%] -rotate-90 text-[min(1.8vh,1.1vw)] font-extrabold uppercase text-suave-2">WC H.</div>
                <div class="absolute left-[3%] top-[60%] -rotate-90 text-[min(1.8vh,1.1vw)] font-extrabold uppercase text-suave-2">WC M.</div>
                <div class="absolute left-[11%] top-[14%] flex h-[18%] w-[10%] items-center justify-center overflow-hidden rounded-lg border-[3px] border-suave-2 bg-white p-[0.5vh] text-center text-[min(2vh,1.2vw)] font-extrabold uppercase text-suave [writing-mode:vertical-rl]">Sobremesas</div>
                <div class="absolute bottom-[8%] left-[9%] flex h-[17%] w-[8%] items-center justify-center overflow-hidden rounded-lg border-[3px] border-suave-2 bg-white p-[0.5vh] text-center text-[min(2vh,1.2vw)] font-extrabold uppercase text-suave [writing-mode:vertical-rl]">Caixa / Bebidas</div>
                <div class="absolute bottom-[1%] left-[19%] rounded-lg bg-escuro px-[0.8vw] py-[0.4vh] text-[min(2vh,1.2vw)] font-extrabold uppercase text-white">Entrada</div>
                <div class="absolute right-[5%] top-[31%] rounded-lg border-[3px] border-suave-2 bg-white px-[0.4vw] py-[1vh] text-[min(2vh,1.2vw)] font-extrabold uppercase text-suave [writing-mode:vertical-rl]">Palco</div>

                <div
                    v-for="mesa in mesasMapa"
                    :key="mesa.id"
                    class="mesa absolute overflow-hidden rounded-xl border-[3px] text-left shadow-[0_4px_12px_rgba(22,32,28,.18)]"
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

                    <!-- Tamanhos relativos à própria mesa (cqw/cqh): o texto cabe sempre dentro dela -->
                    <div class="relative z-10 flex h-full flex-col justify-between overflow-hidden px-[6cqw] py-[5cqh]" :class="textoEscuro(mesa) ? 'text-tinta' : 'text-white'">
                        <div class="flex min-w-0 items-start justify-between gap-[3cqw]">
                            <span class="flex min-w-0 items-baseline gap-[3cqw] whitespace-nowrap leading-none">
                                <span class="text-[min(15cqh,12cqw)] font-bold uppercase opacity-80">Mesa</span>
                                <span class="text-[min(36cqh,30cqw)] font-extrabold">{{ mesa.numero }}</span>
                            </span>
                            <span v-if="mesa.submesas.length" class="mt-[2cqh] h-[min(12cqh,10cqw)] w-[min(12cqh,10cqw)] shrink-0 rounded-full ring-2 ring-white" :class="estadoDot[estadoMesa(mesa)]"></span>
                        </div>

                        <div v-if="mesa.submesas.length" class="grid min-w-0 gap-[2cqh]" :class="mesa.submesas.length > 3 ? 'grid-cols-3' : 'grid-cols-2'">
                            <span v-for="segmento in segmentosMesa(mesa)" :key="segmento.id" class="truncate rounded bg-escuro/70 px-[2cqw] py-[1cqh] text-center text-[min(13cqh,8.5cqw)] font-extrabold leading-tight text-white">
                                {{ segmento.label }} · {{ estadoLabel[segmento.estado] }}
                            </span>
                        </div>
                        <div v-else class="flex min-w-0 flex-col gap-[2cqh] leading-tight">
                            <span class="truncate text-[min(17cqh,10.5cqw)] font-extrabold uppercase">{{ estadoLabel[estadoMesa(mesa)] }}</span>
                            <span v-if="pedidosAtivos(mesa)[0]" class="truncate text-[min(15cqh,10cqw)] font-semibold">
                                #{{ pedidosAtivos(mesa)[0].id }} · {{ horaPedido(pedidosAtivos(mesa)[0]) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="flex shrink-0 items-center justify-between gap-[2vw]">
            <div class="flex min-w-0 flex-wrap gap-x-[1.6vw] gap-y-[0.6vh] text-[min(2.2vh,1.35vw)] font-bold text-escuro-inativo">
                <span v-for="item in legenda" :key="item.estado" class="flex items-center gap-[0.5vw] whitespace-nowrap">
                    <span class="h-[min(2.2vh,1.35vw)] w-[min(2.2vh,1.35vw)] rounded-md" :class="item.estado === 'livre' ? 'border-2 border-[#8FA39A] bg-white' : segmentoClass[item.estado]"></span>
                    {{ item.label }}
                </span>
            </div>
            <span class="shrink-0 whitespace-nowrap text-[min(2vh,1.25vw)] font-bold text-[#8FA39A]">{{ aAtualizar ? 'A atualizar' : 'Último refresh' }}: {{ hora }}</span>
        </footer>
    </main>
</template>

<style scoped>
.mesa { container-type: size; }
</style>
