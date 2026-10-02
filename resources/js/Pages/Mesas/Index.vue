<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({ mesas: Array, zonas: Array });

const mesasMapa = ref(props.mesas.map((mesa) => ({ ...mesa })));
const zonasMapa = ref((props.zonas ?? []).map((zona) => ({ ...zona })));
const editarMapa = ref(false);
const mesaSelecionadaId = ref(null);
const zonaSelecionadaId = ref(null);
const drag = ref(null);
const lugaresOcupados = ref('');
const letraSubmesaNova = ref('');
const mesasGrupo = ref('');
const lugaresSubmesa = ref({});
const submesaLetras = ['A', 'B', 'C', 'D'];
const tiposZona = [
    ['zona', 'Zona'],
    ['texto', 'Texto'],
    ['entrada', 'Entrada'],
    ['porta', 'Porta'],
    ['wc', 'WC'],
    ['palco', 'Palco'],
    ['balcao', 'Balcao'],
    ['cozinha', 'Cozinha'],
];
const zonaForm = useForm({ nome: '', tipo: 'zona', mapa_x: 45, mapa_y: 45, mapa_largura: 10, mapa_altura: 8 });
const zonaEditForm = useForm({ nome: '', tipo: 'zona', mapa_x: 45, mapa_y: 45, mapa_largura: 10, mapa_altura: 8 });

const estadoClass = {
    livre: 'border-emerald-300 bg-emerald-50 text-emerald-950',
    grupo: 'border-violet-300 bg-violet-50 text-violet-950',
    ocupada: 'border-red-300 bg-red-50 text-red-950',
    reservada: 'border-amber-300 bg-amber-50 text-amber-950',
};

const estadoDot = {
    livre: 'bg-emerald-500',
    grupo: 'bg-violet-600',
    ocupada: 'bg-red-500',
    reservada: 'bg-amber-500',
};

const segmentoClass = {
    livre: 'bg-emerald-500/85',
    grupo: 'bg-violet-600/90',
    ocupada: 'bg-red-600/90',
    reservada: 'bg-amber-400/90',
};

const estadoVisual = (mesa) => {
    if (mesa?.pedidos_grupo?.length) {
        return 'grupo';
    }

    if (!mesa?.submesas?.length) {
        return pedidosAtivos(mesa).length && mesaGrande(mesa) ? 'grupo' : (mesa?.estado ?? 'livre');
    }

    if (mesa.submesas.some((submesa) => estadoSubmesa(submesa) === 'grupo')) {
        return 'grupo';
    }

    if (mesa.submesas.some((submesa) => submesa.estado === 'ocupada')) {
        return 'ocupada';
    }

    if (mesa.estado === 'reservada' || mesa.submesas.some((submesa) => submesa.estado === 'reservada')) {
        return 'reservada';
    }

    return 'livre';
};

const mesaGrande = (mesa) => Number(mesa?.capacidade ?? 0) > 10;
const estadoSubmesa = (submesa) => {
    if (submesa?.pedidos_grupo?.length) {
        return 'grupo';
    }

    if (pedidosAtivos(submesa).length && mesaGrande(submesa)) {
        return 'grupo';
    }

    return pedidosAtivos(submesa).length ? 'ocupada' : (submesa?.estado ?? 'livre');
};

const segmentosMesa = (mesa) => {
    if (mesa?.submesas?.length) {
        return mesa.submesas.map((submesa) => ({
            id: submesa.id,
            label: letraSubmesa(submesa),
            estado: estadoSubmesa(submesa),
            capacidade: Number(submesa.capacidade || 1),
        }));
    }

    const estado = mesa?.pedidos_grupo?.length
        ? 'grupo'
        : (pedidosAtivos(mesa).length ? (mesaGrande(mesa) ? 'grupo' : 'ocupada') : (mesa?.estado ?? 'livre'));

    return [{
        id: mesa.id,
        label: mesa.numero,
        estado,
        capacidade: Number(mesa?.capacidade || 1),
    }];
};

const mesaParcial = (mesa) => {
    const estados = new Set(segmentosMesa(mesa).map((segmento) => segmento.estado));

    return estados.size > 1;
};

const mesaLivre = (mesa) => estadoVisual(mesa) === 'livre';
const podeAbrirPedido = (mesa) => mesa && (mesaLivre(mesa) || estadoVisual(mesa) === 'reservada');
const podeMarcarLivre = (mesa) => mesa && !mesaLivre(mesa) && !pedidosAtivosDaMesa(mesa).length;
const mesaPrincipalDividida = (mesa) => mesa && !mesa.mesa_principal_id && mesa.submesas?.length > 0;
const mesaDivididaLivre = (mesa) => mesaPrincipalDividida(mesa) && mesaLivre(mesa);
const podeAbrirPedidoMesaCompleta = (mesa) => podeAbrirPedido(mesa) && (!mesaPrincipalDividida(mesa) || mesaDivididaLivre(mesa));
const lugaresOcupadosNumero = computed(() => Number(lugaresOcupados.value || 0));
const precisaSubmesaSelecionada = computed(() => mesaSelecionada.value
    && !mesaSelecionada.value.mesa_principal_id
    && lugaresOcupadosNumero.value > 0
    && lugaresOcupadosNumero.value < Number(mesaSelecionada.value.capacidade || 0));
const precisaMesasGrupoSelecionada = computed(() => mesaSelecionada.value
    && lugaresOcupadosNumero.value > Number(mesaSelecionada.value.capacidade || 0));
const podeAbrirPedidoSelecionado = computed(() => podeAbrirPedidoMesaCompleta(mesaSelecionada.value)
    && lugaresOcupadosNumero.value > 0
    && (!precisaSubmesaSelecionada.value || letraSubmesaNova.value.trim())
    && (!precisaMesasGrupoSelecionada.value || mesasGrupo.value.trim()));
const pedidosAtivos = (mesa) => [
    ...(mesa?.pedidos ?? []),
    ...(mesa?.pedidos_grupo ?? []),
];
const pedidosAtivosDaMesa = (mesa) => [
    ...pedidosAtivos(mesa),
    ...((mesa?.submesas ?? []).flatMap((submesa) => pedidosAtivos(submesa))),
];
const pedidosAtivosDaMesaDetalhados = (mesa) => [
    ...pedidosAtivos(mesa).map((pedido) => ({ pedido, local: mesa.designacao })),
    ...((mesa?.submesas ?? []).flatMap((submesa) => pedidosAtivos(submesa).map((pedido) => ({ pedido, local: letraSubmesa(submesa) })))),
];

const mesaSelecionada = computed(() => mesasMapa.value.find((mesa) => mesa.id === mesaSelecionadaId.value) ?? (zonaSelecionadaId.value ? null : mesasMapa.value[0]));
const zonaSelecionada = computed(() => zonasMapa.value.find((zona) => zona.id === zonaSelecionadaId.value) ?? null);

watch(() => props.zonas, (zonas) => {
    zonasMapa.value = (zonas ?? []).map((zona) => ({ ...zona }));
}, { deep: true });

watch(zonaSelecionada, (zona) => {
    if (!zona) {
        return;
    }

    zonaEditForm.nome = zona.nome;
    zonaEditForm.tipo = zona.tipo;
    zonaEditForm.mapa_x = zona.mapa_x;
    zonaEditForm.mapa_y = zona.mapa_y;
    zonaEditForm.mapa_largura = zona.mapa_largura;
    zonaEditForm.mapa_altura = zona.mapa_altura;
});

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

const zonaVertical = (zona) => Number(zona.mapa_altura || 0) > Number(zona.mapa_largura || 0);
const zonaClasse = (zona) => ['entrada', 'porta'].includes(zona.tipo)
    ? 'border-transparent bg-white/95 text-slate-700 shadow-sm'
    : zona.tipo === 'wc'
        ? 'border-slate-700/80 bg-sky-50/70 text-slate-900'
    : zona.tipo === 'palco'
        ? 'border-slate-700/80 bg-amber-50/70 text-slate-900'
    : 'border-slate-700/80 bg-white/40 text-slate-900';

const selecionarMesa = (mesa) => {
    mesaSelecionadaId.value = mesa.id;
    zonaSelecionadaId.value = null;
    lugaresOcupados.value = '';
    letraSubmesaNova.value = '';
    mesasGrupo.value = '';
};
const selecionarZona = (zona) => {
    zonaSelecionadaId.value = zona.id;
    mesaSelecionadaId.value = null;
    lugaresOcupados.value = '';
    letraSubmesaNova.value = '';
    mesasGrupo.value = '';
};

const limitar = (valor, minimo, maximo) => Math.min(maximo, Math.max(minimo, Math.round(valor)));

const iniciarDrag = (event, mesa) => {
    selecionarMesa(mesa);

    if (!editarMapa.value) {
        return;
    }

    const mapa = event.currentTarget.closest('[data-sala-mapa]');
    const rect = mapa.getBoundingClientRect();
    drag.value = {
        id: mesa.id,
        tipo: 'mesa',
        rect,
        startX: event.clientX,
        startY: event.clientY,
        mesaX: mesa.mapa_x,
        mesaY: mesa.mapa_y,
    };

    window.addEventListener('mousemove', moverMesa);
    window.addEventListener('mouseup', pararDrag);
};

const moverMesa = (event) => {
    if (!drag.value) {
        return;
    }

    const mesa = mesasMapa.value.find((item) => item.id === drag.value.id);
    const elemento = drag.value.tipo === 'zona'
        ? zonasMapa.value.find((item) => item.id === drag.value.id)
        : mesa;

    if (!elemento) {
        return;
    }

    const dx = ((event.clientX - drag.value.startX) / drag.value.rect.width) * 100;
    const dy = ((event.clientY - drag.value.startY) / drag.value.rect.height) * 100;

    elemento.mapa_x = limitar(drag.value.mesaX + dx, 0, 100 - elemento.mapa_largura);
    elemento.mapa_y = limitar(drag.value.mesaY + dy, 0, 100 - elemento.mapa_altura);
};

const iniciarDragZona = (event, zona) => {
    selecionarZona(zona);

    if (!editarMapa.value) {
        return;
    }

    const mapa = event.currentTarget.closest('[data-sala-mapa]');
    const rect = mapa.getBoundingClientRect();
    drag.value = {
        id: zona.id,
        tipo: 'zona',
        rect,
        startX: event.clientX,
        startY: event.clientY,
        mesaX: zona.mapa_x,
        mesaY: zona.mapa_y,
    };

    window.addEventListener('mousemove', moverMesa);
    window.addEventListener('mouseup', pararDrag);
};

const pararDrag = () => {
    drag.value = null;
    window.removeEventListener('mousemove', moverMesa);
    window.removeEventListener('mouseup', pararDrag);
};

const alterarTamanho = (elemento, campo, valor, minimo = 4, maximo = 40) => {
    elemento[campo] = limitar(Number(valor), minimo, maximo);
    elemento.mapa_x = limitar(elemento.mapa_x, 0, 100 - elemento.mapa_largura);
    elemento.mapa_y = limitar(elemento.mapa_y, 0, 100 - elemento.mapa_altura);
};

const guardarMapa = () => {
    router.patch(route('mesas.mapa.guardar'), {
        mesas: mesasMapa.value.map((mesa) => ({
            id: mesa.id,
            mapa_x: mesa.mapa_x,
            mapa_y: mesa.mapa_y,
            mapa_largura: mesa.mapa_largura,
            mapa_altura: mesa.mapa_altura,
        })),
        zonas: zonasMapa.value.map((zona) => ({
            id: zona.id,
            mapa_x: zona.mapa_x,
            mapa_y: zona.mapa_y,
            mapa_largura: zona.mapa_largura,
            mapa_altura: zona.mapa_altura,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editarMapa.value = false;
        },
    });
};

const criarZona = () => {
    zonaForm.post(route('zonas.store'), {
        preserveScroll: true,
        onSuccess: () => {
            zonaForm.reset();
            zonaForm.tipo = 'zona';
            zonaForm.mapa_x = 45;
            zonaForm.mapa_y = 45;
            zonaForm.mapa_largura = 10;
            zonaForm.mapa_altura = 8;
            router.reload({ only: ['mesas', 'zonas'], preserveScroll: true });
        },
    });
};

const atualizarZona = () => {
    if (!zonaSelecionada.value) {
        return;
    }

    zonaEditForm
        .transform((dados) => ({
            ...dados,
            mapa_x: zonaSelecionada.value.mapa_x,
            mapa_y: zonaSelecionada.value.mapa_y,
            mapa_largura: zonaSelecionada.value.mapa_largura,
            mapa_altura: zonaSelecionada.value.mapa_altura,
        }))
        .patch(route('zonas.update', zonaSelecionada.value.id), {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['mesas', 'zonas'], preserveScroll: true }),
            onFinish: () => zonaEditForm.transform((dados) => dados),
        });
};

const apagarZona = () => {
    if (!zonaSelecionada.value || !confirm(`Apagar ${zonaSelecionada.value.nome}?`)) {
        return;
    }

    router.delete(route('zonas.destroy', zonaSelecionada.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            zonaSelecionadaId.value = null;
            router.reload({ only: ['mesas', 'zonas'], preserveScroll: true });
        },
    });
};

const criarPedido = (mesa, lugares = '', letra = '') => {
    router.post(route('pedidos.store'), {
        mesa_id: mesa.id,
        lugares_ocupados: lugares || null,
        submesa_letra: lugares ? (letra ? letra.toUpperCase() : null) : null,
        mesas_grupo: mesasGrupo.value || null,
        observacoes: '',
    });
};

const juntarMesa = (mesa) => {
    if (confirm(`Juntar novamente a ${mesa.designacao}?`)) {
        router.delete(route('mesas.juntar', mesa.id), { preserveScroll: true });
    }
};

const abrirPedidoSelecionado = () => {
    criarPedido(mesaSelecionada.value, lugaresOcupados.value, letraSubmesaNova.value);
};

const abrirPedidoSubmesa = (submesa) => {
    criarPedido(submesa, lugaresSubmesa.value[submesa.id] || 1);
};

const libertarMesa = (mesa) => {
    router.patch(route('mesas.libertar', mesa.id), {}, { preserveScroll: true });
};

const apagarMesa = (mesa) => {
    if (confirm(`Apagar ${mesa.designacao}? Esta ação remove a mesa do mapa.`)) {
        router.delete(route('mesas.destroy', mesa.id), { preserveScroll: true });
    }
};

const letraSubmesa = (submesa) => submesa.designacao.replace(/^Mesa\s*/i, '');

const lugaresVazios = (mesa) => {
    if (mesa.submesas?.length) {
        return mesa.submesas
            .filter((submesa) => submesa.estado === 'livre')
            .reduce((total, submesa) => total + Number(submesa.capacidade || 0), 0);
    }

    return mesaLivre(mesa) ? Number(mesa.capacidade || 0) : 0;
};

const textoLugaresVazios = (mesa) => {
    const vazios = lugaresVazios(mesa);

    return `${vazios} ${vazios === 1 ? 'lugar vazio' : 'lugares vazios'}`;
};

const textoLugaresVaziosCurto = (mesa) => `${lugaresVazios(mesa)} livres`;

// Apresentação (redesign): cores dos estados e textos curtos
const estadoNome = { livre: 'Livre', grupo: 'Mesa grande / grupo', ocupada: 'Ocupada', reservada: 'Reservada' };
const estadoChip = {
    livre: 'border border-[#8FA39A] bg-white text-tinta',
    grupo: 'bg-roxo text-white',
    ocupada: 'bg-verde text-white',
    reservada: 'bg-azul text-white',
};
const segmentoCor = {
    livre: 'bg-white text-tinta',
    grupo: 'bg-roxo text-white',
    ocupada: 'bg-verde text-white',
    reservada: 'bg-azul text-white',
};
const rodapeMesa = (mesa) => {
    const vazios = lugaresVazios(mesa);
    if (vazios > 0) return `${vazios} livres`;
    return estadoVisual(mesa) === 'grupo' ? 'Grupo' : 'Cheia';
};
const rodapeCurto = (mesa) => {
    const vazios = lugaresVazios(mesa);
    if (vazios > 0) return `${vazios} liv.`;
    return estadoVisual(mesa) === 'grupo' ? 'Grupo' : 'Cheia';
};
const zonaFundo = (zona) => ({
    palco: 'bg-[#FBF3E6]',
    balcao: 'bg-[#EEF2FB]',
    cozinha: 'bg-[#F1F3EF]',
    wc: 'bg-[#EEF5FA]',
    entrada: 'bg-white',
    porta: 'bg-white',
    texto: 'bg-transparent border-transparent',
}[zona.tipo] ?? 'bg-fundo/70');
</script>

<template>
    <AppLayout>
        <div class="flex flex-col gap-[18px] font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex max-w-[620px] flex-col gap-1.5">
                    <div class="flex gap-1.5 text-sm font-bold">
                        <Link :href="route('sala.index')" class="text-verde hover:text-verde-escuro">Sala ao vivo</Link>
                        <span class="text-suave-2">/</span>
                        <span class="text-suave">Gerir mesas</span>
                    </div>
                    <h1 class="text-[30px] font-extrabold leading-tight">Mapa da sala</h1>
                    <p class="text-[15px] text-suave">Salão com 41 mesas. Distribui as mesas no mapa e divide cada mesa em submesas quando precisares.</p>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <button v-if="editarMapa" type="button" class="h-12 rounded-[10px] bg-verde px-[18px] text-[15px] font-bold text-white hover:bg-verde-escuro" @click="guardarMapa">Guardar mapa</button>
                    <button type="button" :aria-pressed="editarMapa" class="flex h-12 items-center gap-2 rounded-[10px] px-[18px] text-[15px] font-bold transition"
                        :class="editarMapa ? 'border-2 border-escuro bg-escuro text-white' : 'border border-linha-forte bg-white text-tinta hover:bg-fundo'"
                        @click="editarMapa = !editarMapa">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16zM14 6l4 4" /></svg>
                        {{ editarMapa ? 'Sair da edição' : 'Editar mapa' }}
                    </button>
                    <Link :href="route('mesas.create')" class="flex h-12 items-center gap-2 rounded-[10px] border border-linha-forte bg-white px-[18px] text-[15px] font-bold text-tinta hover:bg-fundo">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        Nova mesa
                    </Link>
                </div>
            </div>

            <!-- Erros de ações sem campo próprio (abrir pedido, guardar mapa, etc.) -->
            <AvisoErros :excluir="['nome', 'tipo']" />

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm font-semibold text-suave">
                <span class="flex items-center gap-1.5"><span class="h-4 w-4 rounded border-2 border-[#8FA39A] bg-white"></span>Livre</span>
                <span class="flex items-center gap-1.5"><span class="h-4 w-4 rounded bg-roxo"></span>Mesa grande / grupo</span>
                <span class="flex items-center gap-1.5"><span class="h-4 w-4 rounded bg-verde"></span>Ocupada</span>
                <span class="flex items-center gap-1.5"><span class="h-4 w-4 rounded bg-azul"></span>Reservada</span>
                <span class="rounded-full px-3 py-1.5 font-bold sm:ml-auto" :class="editarMapa ? 'bg-laranja-claro text-laranja-texto' : 'bg-verde-claro text-verde-escuro'">
                    {{ editarMapa ? 'Arrasta as mesas no mapa.' : 'Clica numa mesa para gerir.' }}
                </span>
            </div>

            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_330px]">
                <section aria-label="Mapa" class="overflow-x-auto rounded-[14px] border border-linha bg-white p-3">
                    <div
                        data-sala-mapa
                        class="relative h-[78vh] min-h-[640px] w-full min-w-[640px] select-none overflow-hidden rounded-[10px] bg-[#FAFBF8] bg-[length:5%_5%] [background-image:linear-gradient(#ECEEEA_1px,transparent_1px),linear-gradient(90deg,#ECEEEA_1px,transparent_1px)]"
                        :class="editarMapa ? 'border-2 border-dashed border-laranja' : 'border border-linha-fraca'"
                    >
                        <button
                            v-for="zona in zonasMapa"
                            :key="`zona-${zona.id}`"
                            type="button"
                            class="absolute flex items-center justify-center rounded-lg p-1 text-center text-[13px] font-extrabold uppercase tracking-[.06em] text-suave transition"
                            :class="[zonaFundo(zona), editarMapa ? 'cursor-move border-2 border-dashed border-verde' : 'border border-linha-forte', zonaSelecionada?.id === zona.id ? 'ring-4 ring-verde-claro2' : '']"
                            :style="zonaStyle(zona)"
                            @mousedown="iniciarDragZona($event, zona)"
                            @click="selecionarZona(zona)"
                        >
                            <span :class="zonaVertical(zona) ? '[writing-mode:vertical-rl]' : ''">{{ zona.nome }}</span>
                        </button>

                        <button
                            v-for="mesa in mesasMapa"
                            :key="mesa.id"
                            type="button"
                            :aria-label="`${mesa.designacao}, ${estadoNome[estadoVisual(mesa)]}, ${textoLugaresVazios(mesa)}`"
                            :aria-pressed="mesaSelecionada?.id === mesa.id"
                            class="absolute flex flex-col overflow-hidden rounded-lg bg-white p-0 transition"
                            :class="[
                                mesaSelecionada?.id === mesa.id ? 'z-10 border-[3px] border-tinta ring-4 ring-verde-claro2' : (estadoVisual(mesa) === 'livre' ? 'border-2 border-[#8FA39A]' : 'border-2 border-transparent'),
                                editarMapa ? 'cursor-move' : 'hover:scale-[1.02]',
                            ]"
                            :style="mesaStyle(mesa)"
                            @mousedown="iniciarDrag($event, mesa)"
                            @click="selecionarMesa(mesa)"
                        >
                            <span class="flex min-h-0 w-full flex-grow" :class="mesa.mapa_altura > mesa.mapa_largura ? 'flex-col' : 'flex-row'">
                                <span
                                    v-for="segmento in segmentosMesa(mesa)"
                                    :key="segmento.id"
                                    class="flex min-h-0 min-w-0 items-center justify-center text-[15px] font-extrabold"
                                    :class="[segmentoCor[segmento.estado] ?? segmentoCor.livre, mesa.mapa_altura > mesa.mapa_largura ? 'border-b-2 border-white last:border-b-0' : 'border-r-2 border-white last:border-r-0']"
                                    :style="{ flex: segmento.capacidade }"
                                >{{ mesa.submesas?.length ? segmento.label : mesa.numero }}</span>
                            </span>
                            <span class="w-full truncate border-t border-linha-fraca bg-white py-0.5 text-center text-[11px] font-bold text-suave" :title="rodapeMesa(mesa)"><span class="sm:hidden">{{ rodapeCurto(mesa) }}</span><span class="hidden sm:inline">{{ rodapeMesa(mesa) }}</span></span>
                        </button>
                    </div>
                </section>

                <!-- Modo edição -->
                <aside v-if="editarMapa" class="flex flex-col gap-3.5">
                    <form aria-labelledby="novo-elemento" class="flex flex-col gap-3 rounded-[14px] border border-linha bg-white p-[18px]" @submit.prevent="criarZona">
                        <h2 id="novo-elemento" class="text-lg font-extrabold">Novo elemento</h2>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-sm font-bold text-suave">Nome</span>
                            <input v-model="zonaForm.nome" type="text" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde" placeholder="Ex.: Porta lateral">
                            <span v-if="zonaForm.errors.nome" class="text-sm font-semibold text-perigo">{{ zonaForm.errors.nome }}</span>
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-sm font-bold text-suave">Tipo</span>
                            <select v-model="zonaForm.tipo" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde"><option v-for="[valor, label] in tiposZona" :key="valor" :value="valor">{{ label }}</option></select>
                            <span v-if="zonaForm.errors.tipo" class="text-sm font-semibold text-perigo">{{ zonaForm.errors.tipo }}</span>
                        </label>
                        <button type="submit" class="h-11 rounded-[10px] border border-verde bg-verde-claro text-[15px] font-bold text-verde-escuro hover:brightness-95 disabled:opacity-60" :disabled="zonaForm.processing">Criar elemento</button>
                    </form>

                    <div v-if="zonaSelecionada" class="flex flex-col gap-3 rounded-[14px] border border-linha bg-white p-[18px]">
                        <div class="flex flex-col gap-0.5">
                            <h2 class="text-lg font-extrabold">{{ zonaSelecionada.nome }}</h2>
                            <p class="text-sm text-suave-2">Elemento da sala · {{ zonaSelecionada.tipo }}</p>
                        </div>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-sm font-bold text-suave">Nome</span>
                            <input v-model="zonaEditForm.nome" type="text" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde">
                            <span v-if="zonaEditForm.errors.nome" class="text-sm font-semibold text-perigo">{{ zonaEditForm.errors.nome }}</span>
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-sm font-bold text-suave">Tipo</span>
                            <select v-model="zonaEditForm.tipo" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde"><option v-for="[valor, label] in tiposZona" :key="valor" :value="valor">{{ label }}</option></select>
                            <span v-if="zonaEditForm.errors.tipo" class="text-sm font-semibold text-perigo">{{ zonaEditForm.errors.tipo }}</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <label class="flex flex-col gap-1.5">
                                <span class="text-sm font-bold text-suave">X</span>
                                <input :value="zonaSelecionada.mapa_x" type="number" min="0" max="100" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde" @input="zonaSelecionada.mapa_x = limitar(Number($event.target.value), 0, 100 - zonaSelecionada.mapa_largura)">
                            </label>
                            <label class="flex flex-col gap-1.5">
                                <span class="text-sm font-bold text-suave">Y</span>
                                <input :value="zonaSelecionada.mapa_y" type="number" min="0" max="100" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde" @input="zonaSelecionada.mapa_y = limitar(Number($event.target.value), 0, 100 - zonaSelecionada.mapa_altura)">
                            </label>
                            <label class="flex flex-col gap-1.5">
                                <span class="text-sm font-bold text-suave">Largura</span>
                                <input :value="zonaSelecionada.mapa_largura" type="number" min="1" max="60" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde" @input="alterarTamanho(zonaSelecionada, 'mapa_largura', $event.target.value, 1, 60)">
                            </label>
                            <label class="flex flex-col gap-1.5">
                                <span class="text-sm font-bold text-suave">Altura</span>
                                <input :value="zonaSelecionada.mapa_altura" type="number" min="1" max="60" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde" @input="alterarTamanho(zonaSelecionada, 'mapa_altura', $event.target.value, 1, 60)">
                            </label>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="h-11 rounded-[10px] bg-verde text-[15px] font-bold text-white hover:bg-verde-escuro disabled:opacity-60" :disabled="zonaEditForm.processing" @click="atualizarZona">Guardar elemento</button>
                            <button type="button" class="h-11 rounded-[10px] border border-[#F0C9C2] bg-white px-4 text-sm font-bold text-perigo hover:bg-perigo-claro" @click="apagarZona">Apagar</button>
                        </div>
                    </div>

                    <div v-else-if="mesaSelecionada" class="flex flex-col gap-3 rounded-[14px] border border-linha bg-white p-[18px]">
                        <h2 class="text-lg font-extrabold">{{ mesaSelecionada.designacao }}</h2>
                        <p class="text-sm text-suave-2">Arrasta no mapa ou ajusta o tamanho.</p>
                        <div class="grid grid-cols-2 gap-2.5">
                            <label class="flex flex-col gap-1.5">
                                <span class="text-sm font-bold text-suave">Largura</span>
                                <input :value="mesaSelecionada.mapa_largura" type="number" min="4" max="40" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde" @input="alterarTamanho(mesaSelecionada, 'mapa_largura', $event.target.value)">
                            </label>
                            <label class="flex flex-col gap-1.5">
                                <span class="text-sm font-bold text-suave">Altura</span>
                                <input :value="mesaSelecionada.mapa_altura" type="number" min="4" max="40" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde" @input="alterarTamanho(mesaSelecionada, 'mapa_altura', $event.target.value)">
                            </label>
                        </div>
                        <button v-if="mesaLivre(mesaSelecionada) && mesaSelecionada.submesas.length" type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta hover:bg-fundo" @click="juntarMesa(mesaSelecionada)">Juntar mesa</button>
                    </div>
                </aside>

                <!-- Elemento selecionado (fora da edição) -->
                <aside v-if="!editarMapa && zonaSelecionada" class="flex flex-col gap-3 rounded-[14px] border border-linha bg-white p-[18px]">
                    <div class="flex flex-col gap-0.5">
                        <h2 class="text-[22px] font-extrabold">{{ zonaSelecionada.nome }}</h2>
                        <p class="text-[15px] text-suave">Elemento da sala · {{ zonaSelecionada.tipo }}</p>
                    </div>
                    <div class="rounded-[10px] bg-laranja-claro p-3 text-sm font-semibold text-laranja-texto">
                        Ativa “Editar mapa” para mover ou redimensionar este elemento.
                    </div>
                </aside>

                <!-- Mesa selecionada (fora da edição) -->
                <aside v-if="!editarMapa && mesaSelecionada" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                    <div class="flex flex-col gap-1.5 border-b border-linha-fraca p-[18px]">
                        <div class="flex items-center justify-between gap-2.5">
                            <h2 class="text-[22px] font-extrabold">{{ mesaSelecionada.designacao }}</h2>
                            <span class="inline-flex h-7 items-center whitespace-nowrap rounded-full px-3 text-[13px] font-extrabold" :class="estadoChip[estadoVisual(mesaSelecionada)]">{{ estadoNome[estadoVisual(mesaSelecionada)] }}</span>
                        </div>
                        <p class="text-[15px] text-suave">{{ mesaSelecionada.capacidade }} lugares · {{ mesaSelecionada.submesas.length ? `${mesaSelecionada.submesas.length} submesas` : 'mesa inteira' }}</p>
                        <p class="text-[15px] font-bold">{{ textoLugaresVazios(mesaSelecionada) }}</p>
                    </div>

                    <div v-if="pedidosAtivosDaMesa(mesaSelecionada).length" class="flex flex-col gap-2 border-b border-linha-fraca px-[18px] py-3.5">
                        <span class="text-[13px] font-extrabold uppercase tracking-[.06em] text-suave-2">Pedidos ativos</span>
                        <Link v-for="item in pedidosAtivosDaMesaDetalhados(mesaSelecionada)" :key="item.pedido.id" :href="route('pedidos.show', item.pedido.id)"
                            class="flex min-h-11 items-center justify-between gap-2 rounded-[10px] bg-verde-claro px-3.5 py-2 text-base font-bold text-verde-escuro hover:bg-verde-claro2">
                            <span>Ver pedido #{{ item.pedido.id }} · {{ item.local }}</span>
                            <svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </Link>
                    </div>

                    <div v-if="podeAbrirPedidoMesaCompleta(mesaSelecionada)" class="flex flex-col gap-3 border-b border-linha-fraca p-[18px]">
                        <template v-if="podeAbrirPedido(mesaSelecionada) && !mesaSelecionada.submesas.length && !mesaSelecionada.mesa_principal_id">
                            <label class="flex flex-col gap-1.5">
                                <span class="text-sm font-bold text-suave">Lugares ocupados</span>
                                <input v-model="lugaresOcupados" type="number" min="1" max="80" class="h-12 w-full rounded-[10px] border-linha-forte px-3 text-[17px] font-bold focus:border-verde focus:ring-verde" placeholder="Obrigatório">
                            </label>
                            <label v-if="precisaSubmesaSelecionada" class="flex flex-col gap-1.5">
                                <span class="text-sm font-bold text-suave">Letra da submesa</span>
                                <select v-model="letraSubmesaNova" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde uppercase">
                                    <option value="">Escolher letra</option>
                                    <option v-for="letra in submesaLetras" :key="letra" :value="letra">{{ letra }}</option>
                                </select>
                            </label>
                            <label v-if="precisaMesasGrupoSelecionada" class="flex flex-col gap-1.5">
                                <span class="text-sm font-bold text-suave">Mesas do grupo</span>
                                <input v-model="mesasGrupo" type="text" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde" placeholder="Ex.: 32 33 34">
                            </label>
                            <p class="text-[13px] text-suave-2">Ex.: 5 divide a mesa. Acima da capacidade, indica as mesas do grupo.</p>
                        </template>
                        <button type="button" class="h-14 rounded-[10px] bg-verde text-[17px] font-extrabold text-white hover:bg-verde-escuro disabled:opacity-50" :disabled="!podeAbrirPedidoSelecionado" @click="abrirPedidoSelecionado">
                            {{ mesaDivididaLivre(mesaSelecionada) ? 'Abrir pedido mesa completa' : 'Abrir pedido' }}
                        </button>
                    </div>

                    <div v-if="mesaSelecionada.submesas.length" class="flex flex-col gap-2 border-b border-linha-fraca px-[18px] py-3.5">
                        <span class="text-[13px] font-extrabold uppercase tracking-[.06em] text-suave-2">Submesas</span>
                        <p v-if="!podeAbrirPedidoMesaCompleta(mesaSelecionada)" class="text-sm text-suave">Esta mesa está dividida. Abre o pedido numa das submesas abaixo.</p>
                        <div v-for="submesa in mesaSelecionada.submesas" :key="submesa.id" class="flex flex-col gap-2 rounded-[10px] border border-linha px-3 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg font-extrabold"
                                    :class="estadoSubmesa(submesa) === 'livre' ? 'border-2 border-[#8FA39A] bg-white text-tinta' : segmentoCor[estadoSubmesa(submesa)]">{{ letraSubmesa(submesa) }}</span>
                                <span class="min-w-0 flex-grow text-sm text-suave">{{ submesa.capacidade }} pessoas · lugares {{ submesa.lugares }}</span>
                                <button
                                    v-if="!mesaLivre(mesaSelecionada) && podeAbrirPedido(submesa)"
                                    type="button"
                                    class="h-11 rounded-[10px] bg-verde px-4 text-[15px] font-bold text-white hover:bg-verde-escuro disabled:opacity-50"
                                    :disabled="submesa.capacidade > 1 && !lugaresSubmesa[submesa.id]"
                                    @click="abrirPedidoSubmesa(submesa)"
                                >Abrir</button>
                            </div>
                            <label v-if="!mesaLivre(mesaSelecionada) && podeAbrirPedido(submesa) && submesa.capacidade > 1" class="flex flex-col gap-1 text-[13px] font-semibold text-suave">
                                Lugares ocupados
                                <input v-model="lugaresSubmesa[submesa.id]" type="number" min="1" :max="submesa.capacidade - 1" class="h-11 min-w-0 rounded-lg border-linha-forte px-2.5 text-sm focus:border-verde focus:ring-verde" placeholder="Vazio = submesa completa">
                            </label>
                            <div v-if="pedidosAtivos(submesa).length" class="flex flex-wrap gap-x-3 gap-y-1">
                                <Link v-for="pedido in pedidosAtivos(submesa)" :key="pedido.id" :href="route('pedidos.show', pedido.id)" class="inline-flex min-h-11 items-center text-sm font-bold text-verde underline hover:text-verde-escuro">
                                    Ver pedido #{{ pedido.id }}
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 px-[18px] py-3.5">
                        <button v-if="podeMarcarLivre(mesaSelecionada)" type="button" class="flex-[1_1_120px] h-11 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta hover:bg-fundo" @click="libertarMesa(mesaSelecionada)">Marcar livre</button>
                        <Link :href="route('mesas.edit', mesaSelecionada.id)" class="flex flex-[1_1_120px] items-center justify-center h-11 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta hover:bg-fundo">Editar mesa</Link>
                        <button v-if="!pedidosAtivosDaMesa(mesaSelecionada).length" type="button" class="flex-[1_1_120px] h-11 rounded-[10px] border border-[#F0C9C2] bg-white px-4 text-sm font-bold text-perigo hover:bg-perigo-claro" @click="apagarMesa(mesaSelecionada)">Remover mesa</button>
                    </div>
                </aside>
            </div>
        </div>
    </AppLayout>
</template>
