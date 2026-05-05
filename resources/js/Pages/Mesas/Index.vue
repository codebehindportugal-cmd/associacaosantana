<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ mesas: Array });

const mesasMapa = ref(props.mesas.map((mesa) => ({ ...mesa })));
const editarMapa = ref(false);
const mesaSelecionadaId = ref(null);
const drag = ref(null);
const lugaresOcupados = ref('');
const lugaresSubmesa = ref({});

const estadoClass = {
    livre: 'border-emerald-300 bg-emerald-50 text-emerald-950',
    ocupada: 'border-red-300 bg-red-50 text-red-950',
    reservada: 'border-amber-300 bg-amber-50 text-amber-950',
};

const estadoDot = {
    livre: 'bg-emerald-500',
    ocupada: 'bg-red-500',
    reservada: 'bg-amber-500',
};

const estadoVisual = (mesa) => {
    if (!mesa?.submesas?.length) {
        return mesa?.estado ?? 'livre';
    }

    if (mesa.submesas.some((submesa) => submesa.estado === 'ocupada')) {
        return 'ocupada';
    }

    if (mesa.estado === 'reservada' || mesa.submesas.some((submesa) => submesa.estado === 'reservada')) {
        return 'reservada';
    }

    return 'livre';
};

const mesaLivre = (mesa) => estadoVisual(mesa) === 'livre';
const podeAbrirPedido = (mesa) => mesa && (mesaLivre(mesa) || estadoVisual(mesa) === 'reservada');
const podeMarcarLivre = (mesa) => mesa && !mesaLivre(mesa);
const mesaPrincipalDividida = (mesa) => mesa && !mesa.mesa_principal_id && mesa.submesas?.length > 0;
const mesaDivididaLivre = (mesa) => mesaPrincipalDividida(mesa) && mesaLivre(mesa);
const podeAbrirPedidoMesaCompleta = (mesa) => podeAbrirPedido(mesa) && (!mesaPrincipalDividida(mesa) || mesaDivididaLivre(mesa));
const pedidosAtivos = (mesa) => mesa?.pedidos ?? [];
const pedidosAtivosDaMesa = (mesa) => [
    ...pedidosAtivos(mesa),
    ...((mesa?.submesas ?? []).flatMap((submesa) => pedidosAtivos(submesa))),
];

const mesaSelecionada = computed(() => mesasMapa.value.find((mesa) => mesa.id === mesaSelecionadaId.value) ?? mesasMapa.value[0]);

const mesaStyle = (mesa) => ({
    left: `${mesa.mapa_x}%`,
    top: `${mesa.mapa_y}%`,
    width: `${mesa.mapa_largura}%`,
    height: `${mesa.mapa_altura}%`,
});

const selecionarMesa = (mesa) => {
    mesaSelecionadaId.value = mesa.id;
    lugaresOcupados.value = '';
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
    const dx = ((event.clientX - drag.value.startX) / drag.value.rect.width) * 100;
    const dy = ((event.clientY - drag.value.startY) / drag.value.rect.height) * 100;

    mesa.mapa_x = limitar(drag.value.mesaX + dx, 0, 100 - mesa.mapa_largura);
    mesa.mapa_y = limitar(drag.value.mesaY + dy, 0, 100 - mesa.mapa_altura);
};

const pararDrag = () => {
    drag.value = null;
    window.removeEventListener('mousemove', moverMesa);
    window.removeEventListener('mouseup', pararDrag);
};

const alterarTamanho = (mesa, campo, valor) => {
    mesa[campo] = limitar(Number(valor), 8, 40);
    mesa.mapa_x = limitar(mesa.mapa_x, 0, 100 - mesa.mapa_largura);
    mesa.mapa_y = limitar(mesa.mapa_y, 0, 100 - mesa.mapa_altura);
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
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editarMapa.value = false;
        },
    });
};

const criarPedido = (mesa, lugares = '') => {
    router.post(route('pedidos.store'), {
        mesa_id: mesa.id,
        lugares_ocupados: lugares || null,
        observacoes: '',
    });
};

const juntarMesa = (mesa) => {
    if (confirm(`Juntar novamente a ${mesa.designacao}?`)) {
        router.delete(route('mesas.juntar', mesa.id), { preserveScroll: true });
    }
};

const abrirPedidoSelecionado = () => {
    criarPedido(mesaSelecionada.value, lugaresOcupados.value);
};

const abrirPedidoSubmesa = (submesa) => {
    criarPedido(submesa, lugaresSubmesa.value[submesa.id] || '');
};

const libertarMesa = (mesa) => {
    router.patch(route('mesas.libertar', mesa.id), {}, { preserveScroll: true });
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
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-red-500"></span>Ocupada</div>
            <div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-amber-500"></span>Reservada</div>
            <div class="text-slate-500">{{ editarMapa ? 'Arrasta as mesas no mapa.' : 'Clica numa mesa para gerir.' }}</div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
            <section class="rounded-lg border border-slate-300 bg-slate-100 p-3">
                <div data-sala-mapa class="relative h-[78vh] min-h-[720px] w-full overflow-hidden rounded-lg bg-slate-200 shadow-inner">
                <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(15,23,42,0.08)_1px,transparent_1px),linear-gradient(to_bottom,rgba(15,23,42,0.08)_1px,transparent_1px)] bg-[size:50px_50px]"></div>
                <div class="absolute left-4 top-4 rounded-md bg-white/90 px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm">Entrada</div>
                <div class="absolute bottom-4 right-4 rounded-md bg-white/90 px-3 py-2 text-xs font-semibold text-slate-600 shadow-sm">Bar</div>

                <button
                    v-for="mesa in mesasMapa"
                    :key="mesa.id"
                    type="button"
                    class="absolute rounded-lg border-2 p-3 text-left shadow-sm transition"
                    :class="[estadoClass[estadoVisual(mesa)], mesaSelecionada?.id === mesa.id ? 'ring-4 ring-slate-900/20' : '', editarMapa ? 'cursor-move' : 'hover:scale-[1.01]']"
                    :style="mesaStyle(mesa)"
                    @mousedown="iniciarDrag($event, mesa)"
                    @click="selecionarMesa(mesa)"
                >
                    <div class="flex h-full flex-col justify-between">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-base font-black">{{ mesa.numero }}</div>
                                <div class="text-[9px] font-semibold uppercase">{{ textoLugaresVaziosCurto(mesa) }}</div>
                            </div>
                            <span class="h-3 w-3 rounded-full" :class="estadoDot[estadoVisual(mesa)]"></span>
                        </div>

                        <div v-if="mesa.submesas.length" class="grid gap-1" :class="mesa.submesas.length > 3 ? 'grid-cols-3' : 'grid-cols-2'">
                            <span v-for="submesa in mesa.submesas" :key="submesa.id" class="rounded bg-white/80 px-1 py-1 text-center text-[10px] font-black">
                                {{ submesa.estado === 'livre' ? `${submesa.capacidade}L` : '0L' }}
                            </span>
                        </div>
                        <div v-else class="grid grid-cols-5 gap-1">
                            <span class="col-span-5 rounded bg-white/70 py-1 text-center text-[10px] font-bold">{{ textoLugaresVaziosCurto(mesa) }}</span>
                        </div>
                    </div>
                </button>
                </div>
            </section>

            <aside v-if="mesaSelecionada" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
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
                        <input :value="mesaSelecionada.mapa_largura" type="number" min="8" max="40" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="alterarTamanho(mesaSelecionada, 'mapa_largura', $event.target.value)">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase text-slate-500">Altura</label>
                        <input :value="mesaSelecionada.mapa_altura" type="number" min="8" max="40" class="mt-1 w-full rounded-md border-slate-300 text-sm" @input="alterarTamanho(mesaSelecionada, 'mapa_altura', $event.target.value)">
                    </div>
                </div>

                <div class="mb-5 grid gap-2">
                    <div v-if="pedidosAtivosDaMesa(mesaSelecionada).length" class="grid gap-2 rounded-md bg-slate-50 p-3">
                        <div class="text-xs font-semibold uppercase text-slate-500">Pedidos ativos</div>
                        <Link v-for="pedido in pedidosAtivosDaMesa(mesaSelecionada)" :key="pedido.id" :href="route('pedidos.show', pedido.id)" class="rounded-md bg-slate-900 px-3 py-2 text-center text-sm font-semibold text-white">
                            Ver pedido #{{ pedido.id }}
                        </Link>
                    </div>
                    <div v-if="podeAbrirPedido(mesaSelecionada) && !mesaSelecionada.submesas.length && !mesaSelecionada.mesa_principal_id" class="rounded-md bg-slate-50 p-4">
                        <label class="text-xs font-semibold uppercase text-slate-500">Lugares ocupados</label>
                        <input v-model="lugaresOcupados" type="number" min="1" :max="mesaSelecionada.capacidade - 1" class="mt-1 w-full rounded-md border-slate-300 text-sm" placeholder="Vazio = mesa completa">
                        <p class="mt-2 text-xs text-slate-500">Ex.: se escreveres 5, cria 1A com 5 lugares e deixa 1B livre com os restantes.</p>
                    </div>
                    <button v-if="podeAbrirPedidoMesaCompleta(mesaSelecionada)" type="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white" @click="abrirPedidoSelecionado">
                        {{ mesaDivididaLivre(mesaSelecionada) ? 'Abrir pedido mesa completa' : 'Abrir pedido' }}
                    </button>
                    <div v-else-if="mesaSelecionada.submesas.length" class="rounded-md bg-amber-50 p-3 text-sm font-semibold text-amber-800">
                        Esta mesa está dividida. Abre o pedido numa das submesas abaixo.
                    </div>
                    <button v-if="editarMapa && mesaLivre(mesaSelecionada) && mesaSelecionada.submesas.length" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="juntarMesa(mesaSelecionada)">Juntar mesa</button>
                    <button v-if="podeMarcarLivre(mesaSelecionada)" type="button" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" @click="libertarMesa(mesaSelecionada)">Marcar livre</button>
                    <Link :href="route('mesas.edit', mesaSelecionada.id)" class="rounded-md border border-slate-300 px-3 py-2 text-center text-sm font-semibold">Editar mesa</Link>
                </div>

                <div v-if="mesaSelecionada.submesas.length" class="space-y-2">
                    <div class="text-xs font-semibold uppercase text-slate-500">Submesas</div>
                    <div v-for="submesa in mesaSelecionada.submesas" :key="submesa.id" class="rounded-md border p-3" :class="estadoClass[submesa.estado]">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-black">{{ letraSubmesa(submesa) }}</div>
                                <div class="text-xs">{{ submesa.capacidade }} pessoas · lugares {{ submesa.lugares }}</div>
                            </div>
                            <button v-if="!mesaLivre(mesaSelecionada) && podeAbrirPedido(submesa)" type="button" class="rounded-md bg-slate-900 px-3 py-2 text-xs font-semibold text-white" @click="abrirPedidoSubmesa(submesa)">Abrir</button>
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

