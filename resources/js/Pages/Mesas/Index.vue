<script setup>
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
        submesa_letra: lugares ? (letra || null) : null,
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
</script>

<template>
    <AppLayout>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Mapa da sala</h1>
                <p class="mt-1 text-sm text-slate-500">Salão com 41 mesas. Distribui as mesas no mapa e divide cada mesa em submesas quando precisares.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="editarMapa = !editarMapa">
                    {{ editarMapa ? 'Sair da edição' : 'Editar mapa' }}
                </button>
                <button v-if="editarMapa" type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white" @click="guardarMapa">Guardar mapa</button>
                <Link :href="route('mesas.create')" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Nova mesa</Link>
            </div>
        </div>

        <div class="mb-4 grid gap-3 rounded-lg bg-white p-4 text-sm shadow-sm md:grid-cols-4">
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-emerald-500"></span>Livre</div>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-violet-600"></span>Mesa grande / grupo</div>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-red-500"></span>Ocupada</div>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-amber-500"></span>Reservada</div>
            <div class="text-slate-500">{{ editarMapa ? 'Arrasta as mesas no mapa.' : 'Clica numa mesa para gerir.' }}</div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
            <section class="rounded-lg border border-slate-300 bg-slate-100 p-3">
                <div data-sala-mapa class="relative h-[78vh] min-h-[720px] w-full overflow-hidden rounded-lg bg-[#f7f5ef] shadow-inner ring-1 ring-slate-300">
                <div class="absolute inset-x-[2%] inset-y-[4%] rounded-md border-[3px] border-slate-800/70"></div>
                <div class="absolute left-[2%] top-[38%] h-[18%] w-[2px] bg-[#f7f5ef]"></div>
                <div class="absolute left-[2%] top-[63%] h-[14%] w-[2px] bg-[#f7f5ef]"></div>
                <div class="absolute right-[2%] top-[12%] h-[16%] w-[2px] bg-[#f7f5ef]"></div>
                <div class="absolute right-[2%] bottom-[8%] h-[12%] w-[2px] bg-[#f7f5ef]"></div>

                <button
                    v-for="zona in zonasMapa"
                    :key="`zona-${zona.id}`"
                    type="button"
                    class="absolute flex items-center justify-center rounded-sm border-[3px] p-1 text-center text-xs font-black uppercase transition"
                    :class="[zonaClasse(zona), zonaSelecionada?.id === zona.id ? 'ring-4 ring-slate-900/25' : '', editarMapa ? 'cursor-move hover:bg-white/70' : '']"
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
                    class="absolute overflow-hidden rounded-md border-2 border-slate-900 bg-white text-left shadow-sm transition"
                    :class="[mesaSelecionada?.id === mesa.id ? 'ring-4 ring-slate-900/25' : '', mesaParcial(mesa) ? 'ring-2 ring-amber-500' : '', editarMapa ? 'cursor-move' : 'hover:scale-[1.01]']"
                    :style="mesaStyle(mesa)"
                    @mousedown="iniciarDrag($event, mesa)"
                    @click="selecionarMesa(mesa)"
                >
                    <div class="absolute inset-0 flex" :class="mesa.mapa_altura > mesa.mapa_largura ? 'flex-col' : 'flex-row'">
                        <div
                            v-for="segmento in segmentosMesa(mesa)"
                            :key="segmento.id"
                            class="min-h-0 min-w-0 border-white/60"
                            :class="[segmentoClass[segmento.estado] ?? segmentoClass.livre, mesa.mapa_altura > mesa.mapa_largura ? 'border-b last:border-b-0' : 'border-r last:border-r-0']"
                            :style="{ flex: segmento.capacidade }"
                        ></div>
                    </div>
                    <div class="relative z-10 flex h-full flex-col justify-between p-1 text-center text-white drop-shadow">
                        <div class="flex items-start justify-between gap-1">
                            <span class="rounded bg-slate-950/55 px-1 text-[10px] font-black">{{ mesa.numero }}</span>
                            <span class="h-2.5 w-2.5 rounded-full ring-1 ring-white" :class="estadoDot[estadoVisual(mesa)]"></span>
                        </div>

                        <div v-if="mesa.submesas.length" class="space-y-0.5">
                            <div class="grid gap-0.5" :class="mesa.submesas.length > 3 ? 'grid-cols-3' : 'grid-cols-2'">
                                <span v-for="segmento in segmentosMesa(mesa)" :key="segmento.id" class="rounded bg-slate-950/45 px-1 py-0.5 text-[9px] font-black">
                                    {{ segmento.label }}
                                </span>
                            </div>
                            <span v-if="lugaresVazios(mesa) > 0" class="inline-block rounded bg-slate-950/55 px-1 py-0.5 text-[9px] font-bold">{{ textoLugaresVaziosCurto(mesa) }}</span>
                        </div>
                        <div v-else>
                            <span class="rounded bg-slate-950/45 px-1 py-0.5 text-[9px] font-bold">{{ textoLugaresVaziosCurto(mesa) }}</span>
                        </div>
                    </div>
                </button>
                </div>
            </section>

            <aside v-if="editarMapa" class="space-y-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <form class="space-y-3 rounded-md border border-dashed border-slate-300 bg-white p-4" @submit.prevent="criarZona">
                    <h2 class="text-lg font-black">Novo elemento</h2>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Nome</label>
                        <input v-model="zonaForm.nome" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: Porta lateral">
                        <p v-if="zonaForm.errors.nome" class="mt-1 text-xs font-semibold text-red-600">{{ zonaForm.errors.nome }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Tipo</label>
                        <select v-model="zonaForm.tipo" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            <option v-for="[valor, label] in tiposZona" :key="valor" :value="valor">{{ label }}</option>
                        </select>
                        <p v-if="zonaForm.errors.tipo" class="mt-1 text-xs font-semibold text-red-600">{{ zonaForm.errors.tipo }}</p>
                    </div>
                    <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="zonaForm.processing">
                        Criar elemento
                    </button>
                </form>

                <div v-if="zonaSelecionada" class="space-y-3 rounded-md bg-slate-50 p-4">
                    <div>
                        <h2 class="text-xl font-bold">{{ zonaSelecionada.nome }}</h2>
                        <p class="text-sm text-slate-500">Elemento da sala · {{ zonaSelecionada.tipo }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Nome</label>
                        <input v-model="zonaEditForm.nome" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <p v-if="zonaEditForm.errors.nome" class="mt-1 text-xs font-semibold text-red-600">{{ zonaEditForm.errors.nome }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Tipo</label>
                        <select v-model="zonaEditForm.tipo" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            <option v-for="[valor, label] in tiposZona" :key="valor" :value="valor">{{ label }}</option>
                        </select>
                        <p v-if="zonaEditForm.errors.tipo" class="mt-1 text-xs font-semibold text-red-600">{{ zonaEditForm.errors.tipo }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label>
                            <span class="text-xs font-semibold uppercase text-slate-500">X</span>
                            <input :value="zonaSelecionada.mapa_x" type="number" min="0" max="100" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="zonaSelecionada.mapa_x = limitar(Number($event.target.value), 0, 100 - zonaSelecionada.mapa_largura)">
                        </label>
                        <label>
                            <span class="text-xs font-semibold uppercase text-slate-500">Y</span>
                            <input :value="zonaSelecionada.mapa_y" type="number" min="0" max="100" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="zonaSelecionada.mapa_y = limitar(Number($event.target.value), 0, 100 - zonaSelecionada.mapa_altura)">
                        </label>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Largura</label>
                        <input :value="zonaSelecionada.mapa_largura" type="number" min="1" max="60" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="alterarTamanho(zonaSelecionada, 'mapa_largura', $event.target.value, 1, 60)">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Altura</label>
                        <input :value="zonaSelecionada.mapa_altura" type="number" min="1" max="60" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="alterarTamanho(zonaSelecionada, 'mapa_altura', $event.target.value, 1, 60)">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="zonaEditForm.processing" @click="atualizarZona">
                            Guardar elemento
                        </button>
                        <button type="button" class="rounded-md border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50" @click="apagarZona">
                            Apagar
                        </button>
                    </div>
                </div>

                <div v-else-if="mesaSelecionada" class="space-y-3 rounded-md bg-slate-50 p-4">
                    <h2 class="text-xl font-bold">{{ mesaSelecionada.designacao }}</h2>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Largura</label>
                        <input :value="mesaSelecionada.mapa_largura" type="number" min="4" max="40" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="alterarTamanho(mesaSelecionada, 'mapa_largura', $event.target.value)">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Altura</label>
                        <input :value="mesaSelecionada.mapa_altura" type="number" min="4" max="40" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="alterarTamanho(mesaSelecionada, 'mapa_altura', $event.target.value)">
                    </div>
                </div>
            </aside>

            <aside v-if="!editarMapa && zonaSelecionada" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-xl font-bold">{{ zonaSelecionada.nome }}</h2>
                    <p class="text-sm text-slate-500">Elemento da sala · {{ zonaSelecionada.tipo }}</p>
                </div>

                <div v-if="editarMapa" class="space-y-3 rounded-md bg-slate-50 p-4">
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Nome</label>
                        <input v-model="zonaEditForm.nome" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        <p v-if="zonaEditForm.errors.nome" class="mt-1 text-xs font-semibold text-red-600">{{ zonaEditForm.errors.nome }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Tipo</label>
                        <select v-model="zonaEditForm.tipo" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            <option v-for="[valor, label] in tiposZona" :key="valor" :value="valor">{{ label }}</option>
                        </select>
                        <p v-if="zonaEditForm.errors.tipo" class="mt-1 text-xs font-semibold text-red-600">{{ zonaEditForm.errors.tipo }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <label>
                            <span class="text-xs font-semibold uppercase text-slate-500">X</span>
                            <input :value="zonaSelecionada.mapa_x" type="number" min="0" max="100" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="zonaSelecionada.mapa_x = limitar(Number($event.target.value), 0, 100 - zonaSelecionada.mapa_largura)">
                        </label>
                        <label>
                            <span class="text-xs font-semibold uppercase text-slate-500">Y</span>
                            <input :value="zonaSelecionada.mapa_y" type="number" min="0" max="100" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="zonaSelecionada.mapa_y = limitar(Number($event.target.value), 0, 100 - zonaSelecionada.mapa_altura)">
                        </label>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Largura</label>
                        <input :value="zonaSelecionada.mapa_largura" type="number" min="1" max="60" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="alterarTamanho(zonaSelecionada, 'mapa_largura', $event.target.value, 1, 60)">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Altura</label>
                        <input :value="zonaSelecionada.mapa_altura" type="number" min="1" max="60" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="alterarTamanho(zonaSelecionada, 'mapa_altura', $event.target.value, 1, 60)">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="zonaEditForm.processing" @click="atualizarZona">
                            Guardar elemento
                        </button>
                        <button type="button" class="rounded-md border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50" @click="apagarZona">
                            Apagar
                        </button>
                    </div>
                </div>

                <div v-else class="rounded-md bg-amber-50 p-3 text-sm font-semibold text-amber-800">
                    Ativa “Editar mapa” para mover ou redimensionar este elemento.
                </div>

                <form v-if="editarMapa" class="mt-4 space-y-3 rounded-md border border-dashed border-slate-300 bg-white p-4" @submit.prevent="criarZona">
                    <h3 class="text-sm font-black uppercase text-slate-600">Novo elemento</h3>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Nome</label>
                        <input v-model="zonaForm.nome" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: Porta lateral">
                        <p v-if="zonaForm.errors.nome" class="mt-1 text-xs font-semibold text-red-600">{{ zonaForm.errors.nome }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Tipo</label>
                        <select v-model="zonaForm.tipo" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                            <option v-for="[valor, label] in tiposZona" :key="valor" :value="valor">{{ label }}</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="zonaForm.processing">
                        Criar elemento
                    </button>
                </form>
            </aside>

            <aside v-if="!editarMapa && mesaSelecionada" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-bold">{{ mesaSelecionada.designacao }}</h2>
                        <p class="text-sm text-slate-500">{{ mesaSelecionada.capacidade }} lugares · {{ mesaSelecionada.submesas.length ? `${mesaSelecionada.submesas.length} submesas` : 'mesa inteira' }}</p>
                        <p class="mt-1 text-sm font-bold text-emerald-700">{{ textoLugaresVazios(mesaSelecionada) }}</p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-bold uppercase" :class="estadoClass[estadoVisual(mesaSelecionada)]">{{ estadoVisual(mesaSelecionada) }}</span>
                </div>

                <div v-if="editarMapa" class="mb-5 space-y-3 rounded-md bg-slate-50 p-4">
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Largura</label>
                        <input :value="mesaSelecionada.mapa_largura" type="number" min="4" max="40" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="alterarTamanho(mesaSelecionada, 'mapa_largura', $event.target.value)">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Altura</label>
                        <input :value="mesaSelecionada.mapa_altura" type="number" min="4" max="40" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="alterarTamanho(mesaSelecionada, 'mapa_altura', $event.target.value)">
                    </div>
                    <form class="space-y-3 rounded-md border border-dashed border-slate-300 bg-white p-4" @submit.prevent="criarZona">
                        <h3 class="text-sm font-black uppercase text-slate-600">Novo elemento da sala</h3>
                        <div>
                            <label class="text-xs font-semibold uppercase text-slate-500">Nome</label>
                            <input v-model="zonaForm.nome" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: Porta, bar, palco">
                            <p v-if="zonaForm.errors.nome" class="mt-1 text-xs font-semibold text-red-600">{{ zonaForm.errors.nome }}</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-slate-500">Tipo</label>
                            <select v-model="zonaForm.tipo" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option v-for="[valor, label] in tiposZona" :key="valor" :value="valor">{{ label }}</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="zonaForm.processing">
                            Criar elemento
                        </button>
                    </form>
                </div>

                <div class="mb-5 grid gap-2">
                    <div v-if="pedidosAtivosDaMesa(mesaSelecionada).length" class="grid gap-2 rounded-md bg-slate-50 p-3">
                        <div class="text-xs font-semibold uppercase text-slate-500">Pedidos ativos</div>
                        <Link v-for="item in pedidosAtivosDaMesaDetalhados(mesaSelecionada)" :key="item.pedido.id" :href="route('pedidos.show', item.pedido.id)" class="rounded-md bg-slate-900 px-3 py-2 text-center text-sm font-semibold text-white">
                            Ver pedido #{{ item.pedido.id }} · {{ item.local }}
                        </Link>
                    </div>
                    <div v-if="podeAbrirPedido(mesaSelecionada) && !mesaSelecionada.submesas.length && !mesaSelecionada.mesa_principal_id" class="rounded-md bg-slate-50 p-4">
                        <label class="text-xs font-semibold uppercase text-slate-500">Lugares ocupados</label>
                        <input v-model="lugaresOcupados" type="number" min="1" max="80" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Obrigatorio">
                        <label v-if="precisaSubmesaSelecionada" class="mt-3 block text-xs font-semibold uppercase text-slate-500">
                            Letra da submesa
                            <input v-model="letraSubmesaNova" type="text" maxlength="1" class="mt-1 w-full rounded-md border-slate-300 text-sm uppercase" placeholder="Ex.: A">
                        </label>
                        <label v-if="precisaMesasGrupoSelecionada" class="mt-3 block text-xs font-semibold uppercase text-slate-500">
                            Mesas do grupo
                            <input v-model="mesasGrupo" type="text" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Ex.: 32 33 34">
                        </label>
                        <p class="mt-2 text-xs text-slate-500">Ex.: 5 divide a mesa. Acima da capacidade, indica as mesas do grupo.</p>
                    </div>
                    <button v-if="podeAbrirPedidoMesaCompleta(mesaSelecionada)" type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="!podeAbrirPedidoSelecionado" @click="abrirPedidoSelecionado">
                        {{ mesaDivididaLivre(mesaSelecionada) ? 'Abrir pedido mesa completa' : 'Abrir pedido' }}
                    </button>
                    <div v-else-if="mesaSelecionada.submesas.length" class="rounded-md bg-amber-50 p-3 text-sm font-semibold text-amber-800">
                        Esta mesa está dividida. Abre o pedido numa das submesas abaixo.
                    </div>
                    <button v-if="editarMapa && mesaLivre(mesaSelecionada) && mesaSelecionada.submesas.length" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="juntarMesa(mesaSelecionada)">Juntar mesa</button>
                    <button v-if="podeMarcarLivre(mesaSelecionada)" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="libertarMesa(mesaSelecionada)">Marcar livre</button>
                    <Link :href="route('mesas.edit', mesaSelecionada.id)" class="rounded-md border border-slate-300 px-3 py-2 text-center text-sm font-semibold">Editar mesa</Link>
                    <button
                        v-if="!pedidosAtivosDaMesa(mesaSelecionada).length"
                        type="button"
                        class="rounded-md border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50"
                        @click="apagarMesa(mesaSelecionada)"
                    >
                        Remover mesa
                    </button>
                </div>

                <div v-if="mesaSelecionada.submesas.length" class="space-y-2">
                    <div class="text-xs font-semibold uppercase text-slate-500">Submesas</div>
                    <div v-for="submesa in mesaSelecionada.submesas" :key="submesa.id" class="rounded-md border p-3" :class="estadoClass[estadoSubmesa(submesa)]">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-black">{{ letraSubmesa(submesa) }}</div>
                                <div class="text-xs">{{ submesa.capacidade }} pessoas · lugares {{ submesa.lugares }}</div>
                            </div>
                            <button
                                v-if="!mesaLivre(mesaSelecionada) && podeAbrirPedido(submesa)"
                                type="button"
                                class="rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                :disabled="submesa.capacidade > 1 && !lugaresSubmesa[submesa.id]"
                                @click="abrirPedidoSubmesa(submesa)"
                            >
                                Abrir
                            </button>
                        </div>
                        <label v-if="!mesaLivre(mesaSelecionada) && podeAbrirPedido(submesa) && submesa.capacidade > 1" class="mt-3 block text-xs font-semibold uppercase text-slate-600">
                            Lugares ocupados
                            <input v-model="lugaresSubmesa[submesa.id]" type="number" min="1" :max="submesa.capacidade - 1" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Vazio = submesa completa">
                        </label>
                        <div v-if="pedidosAtivos(submesa).length" class="mt-3 grid gap-2">
                            <Link v-for="pedido in pedidosAtivos(submesa)" :key="pedido.id" :href="route('pedidos.show', pedido.id)" class="rounded-md bg-slate-900 px-3 py-2 text-center text-xs font-semibold text-white">
                                Ver pedido #{{ pedido.id }}
                            </Link>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </AppLayout>
</template>

