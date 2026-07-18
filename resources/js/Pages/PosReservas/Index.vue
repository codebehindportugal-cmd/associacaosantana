<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import QRCode from 'qrcode';
import ChamarComissaoModal from '@/Components/ChamarComissaoModal.vue';

const props = defineProps({
    posNome: String,
    operadorNome: String,
    hoje: String,
    reservasHoje: Array,
    proximasReservas: Array,
});

const agora = ref(new Date());
const reservaEmEdicao = ref(null);
const sentarReservaId = ref(null);
const pesquisa = ref('');
const filtroEstado = ref('por-sentar');
const pesquisaProximas = ref('');
const filtroProximas = ref('todas');
let relogio = null;
let refresh = null;

// QR code da reserva
const qrReserva = ref(null);   // { nome, url }
const qrDataUrl = ref('');
let qrTimer = null;

const mostrarQrReserva = async (reserva) => {
    const url = `${window.location.origin}/reserva/${reserva.token}`;
    qrDataUrl.value = await QRCode.toDataURL(url, { width: 320, margin: 2 });
    qrReserva.value = { nome: reserva.nome, url };
    // Fechar automaticamente após 3 minutos
    clearTimeout(qrTimer);
    qrTimer = setTimeout(fecharQrReserva, 3 * 60 * 1000);
};
const fecharQrReserva = () => {
    clearTimeout(qrTimer);
    qrReserva.value = null;
    qrDataUrl.value = '';
};

const form = useForm({
    nome: '',
    data_reserva: props.hoje,
    hora: '20:00',
    pessoas: 2,
    observacoes: '',
});

const reservasPendentes = computed(() => (props.reservasHoje ?? []).filter((reserva) => reserva.estado !== 'sentada'));
const pessoasPorSentar = computed(() => reservasPendentes.value.reduce((soma, r) => soma + (Number(r.pessoas) || 0), 0));
const gruposPorSentar = computed(() => reservasPendentes.value.length);
const gruposGrandes = computed(() => (props.reservasHoje ?? []).filter((r) => Number(r.pessoas) > 10));
const totalPessoasGrandes = computed(() => gruposGrandes.value.reduce((soma, r) => soma + (Number(r.pessoas) || 0), 0));
const totalPessoasHoje = computed(() => (props.reservasHoje ?? []).reduce((soma, r) => soma + (Number(r.pessoas) || 0), 0));
const reservasSentadas = computed(() => (props.reservasHoje ?? []).filter((r) => r.estado === 'sentada'));
const pessoasSentadas = computed(() => reservasSentadas.value.reduce((soma, r) => soma + (Number(r.pessoas) || 0), 0));

const normalizar = (valor) => String(valor ?? '')
    .normalize('NFD')
    .replace(new RegExp('[\\u0300-\\u036f]', 'g'), '')
    .toLowerCase();

const reservasPorEstado = computed(() => {
    const lista = props.reservasHoje ?? [];

    switch (filtroEstado.value) {
        case 'chamadas':
            return lista.filter((reserva) => reserva.estado !== 'sentada' && reserva.chamada_em);
        case 'sentadas':
            return lista.filter((reserva) => reserva.estado === 'sentada');
        case 'por-sentar':
            return lista.filter((reserva) => reserva.estado !== 'sentada');
        case 'grupos-grandes':
            return lista.filter((r) => Number(r.pessoas) > 10);
        default:
            return lista;
    }
});

const reservasFiltradas = computed(() => {
    const termo = normalizar(pesquisa.value.trim());

    if (!termo) {
        return reservasPorEstado.value;
    }

    return reservasPorEstado.value.filter((reserva) => (
        normalizar(reserva.nome).includes(termo)
        || normalizar(horaReserva(reserva)).includes(termo)
        || normalizar(reserva.pessoas).includes(termo)
        || normalizar(reserva.observacoes).includes(termo)
    ));
});

const hojeData = computed(() => new Date(`${props.hoje}T00:00:00`));
const diasAtePartirDeHoje = (data) => {
    const alvo = new Date(`${String(data).split('T')[0]}T00:00:00`);

    return Math.round((alvo - hojeData.value) / 86400000);
};

const proximasFiltradas = computed(() => {
    let lista = props.proximasReservas ?? [];

    if (filtroProximas.value === 'amanha') {
        lista = lista.filter((reserva) => diasAtePartirDeHoje(reserva.data) === 1);
    } else if (filtroProximas.value === 'semana') {
        lista = lista.filter((reserva) => diasAtePartirDeHoje(reserva.data) <= 7);
    }

    const termo = normalizar(pesquisaProximas.value.trim());

    if (termo) {
        lista = lista.filter((reserva) => (
            normalizar(reserva.nome).includes(termo)
            || normalizar(horaReserva(reserva)).includes(termo)
            || normalizar(reserva.pessoas).includes(termo)
        ));
    }

    return lista;
});

const horasDisponiveis = Array.from({ length: 24 * 4 }, (_, index) => {
    const hora = String(Math.floor(index / 4)).padStart(2, '0');
    const minuto = String((index % 4) * 15).padStart(2, '0');

    return `${hora}:${minuto}`;
});

const editForm = useForm({
    nome: '',
    hora: '',
    pessoas: 1,
    observacoes: '',
});

const sentarForm = useForm({
    mesa_numero: '',
    mesa_letra: '',
});

const criarReserva = () => {
    form
        .transform((dados) => ({
            ...dados,
            data: dados.data_reserva,
        }))
        .post(route('pos.reservas.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('nome', 'observacoes');
                form.data_reserva = props.hoje;
                form.hora = '20:00';
                form.pessoas = 2;
            },
            onFinish: () => form.transform((dados) => dados),
        });
};

const editar = (reserva) => {
    reservaEmEdicao.value = reserva.id;
    editForm.clearErrors();
    editForm.nome = reserva.nome;
    editForm.hora = horaReserva(reserva);
    editForm.pessoas = reserva.pessoas;
    editForm.observacoes = reserva.observacoes || '';
};

const cancelarEdicao = () => {
    reservaEmEdicao.value = null;
    editForm.clearErrors();
};

const guardarEdicao = (reserva) => {
    editForm.patch(route('pos.reservas.update', reserva.id), {
        preserveScroll: true,
        onSuccess: () => cancelarEdicao(),
    });
};

const chamar = (reserva) => {
    router.patch(route('pos.reservas.chamar', reserva.id), {}, { preserveScroll: true });
};

const abrirSentar = (reserva) => {
    sentarReservaId.value = reserva.id;
    sentarForm.reset();
};

const confirmarSentar = (reserva) => {
    sentarForm.patch(route('pos.reservas.sentar', reserva.id), {
        preserveScroll: true,
        onSuccess: () => { sentarReservaId.value = null; },
    });
};

const cancelar = (reserva) => {
    if (confirm(`Cancelar a reserva de ${reserva.nome}?`)) {
        router.patch(route('pos.reservas.cancelar', reserva.id), {}, { preserveScroll: true });
    }
};

const logout = () => router.post(route('pos.logout'));
const chamandoComissao = ref(false);
const horaReserva = (reserva) => reserva.hora?.slice(0, 5) ?? '--:--';

const horaData = (data) => {
    if (!data) {
        return '';
    }

    return new Date(data).toLocaleTimeString('pt-PT', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const dia = (data) => new Date(`${String(data).split('T')[0]}T00:00:00`).toLocaleDateString('pt-PT', {
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
});

onMounted(() => {
    relogio = setInterval(() => (agora.value = new Date()), 1000);
    refresh = setInterval(() => router.reload({ preserveScroll: true }), 15000);
});

onBeforeUnmount(() => {
    clearInterval(relogio);
    clearInterval(refresh);
    clearTimeout(qrTimer);
});
</script>

<template>
    <main class="pos-reservas flex min-h-screen bg-gray-900 p-3 text-white sm:p-4 lg:h-screen lg:overflow-hidden">
        <div class="flex min-h-0 w-full flex-col">
            <header class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black sm:text-3xl">POS RESERVAS</h1>
                    <p class="font-bold text-gray-300">{{ operadorNome || posNome }} - {{ agora.toLocaleTimeString('pt-PT') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-black text-black" @click="chamandoComissao = true">🎉 COMISSÃO</button>
                    <button class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-black" @click="logout">LOGOUT</button>
                </div>
            </header>

            <section class="mb-3 grid shrink-0 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                <button
                    type="button"
                    class="rounded-lg bg-blue-700 p-2.5 text-left transition"
                    :class="filtroEstado === 'todas' ? 'ring-2 ring-white' : 'opacity-90 hover:opacity-100'"
                    @click="filtroEstado = 'todas'"
                >
                    <div class="text-sm font-bold">Total hoje</div>
                    <div class="text-2xl font-black">{{ reservasHoje.length }} reservas</div>
                    <div class="text-sm font-bold text-blue-200">{{ totalPessoasHoje }} pessoas</div>
                </button>
                <div class="rounded-lg bg-gray-800 p-2.5">
                    <div class="mb-1.5 text-sm font-bold">Pessoas hoje</div>
                    <div class="flex items-end justify-between gap-2">
                        <button
                            type="button"
                            class="flex-1 rounded-lg p-1.5 text-left transition"
                            :class="filtroEstado === 'sentadas' ? 'bg-emerald-600 ring-2 ring-white' : 'bg-emerald-900/60 hover:bg-emerald-800/60'"
                            @click="filtroEstado = 'sentadas'"
                        >
                            <div class="text-2xl font-black text-emerald-300">{{ pessoasSentadas }}</div>
                            <div class="text-xs font-bold text-emerald-400">sentadas</div>
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg p-1.5 text-left transition"
                            :class="filtroEstado === 'por-sentar' ? 'bg-gray-600 ring-2 ring-white' : 'bg-gray-900/60 hover:bg-gray-700/60'"
                            @click="filtroEstado = 'por-sentar'"
                        >
                            <div class="text-2xl font-black text-gray-200">{{ pessoasPorSentar }}</div>
                            <div class="text-xs font-bold text-gray-400">por sentar</div>
                        </button>
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-gray-800 p-2.5 text-left transition"
                    :class="filtroEstado === 'por-sentar' ? 'ring-2 ring-white' : 'opacity-90 hover:opacity-100'"
                    @click="filtroEstado = 'por-sentar'"
                >
                    <div class="text-sm font-bold">Por sentar</div>
                    <div class="text-2xl font-black">{{ gruposPorSentar }} grupos</div>
                    <div class="text-sm font-bold text-gray-400">{{ pessoasPorSentar }} pessoas</div>
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-orange-700/80 p-2.5 text-left transition"
                    :class="filtroEstado === 'grupos-grandes' ? 'ring-2 ring-white' : 'opacity-90 hover:opacity-100'"
                    @click="filtroEstado = 'grupos-grandes'"
                >
                    <div class="text-sm font-bold">Grupos grandes (+10)</div>
                    <div class="text-2xl font-black">{{ gruposGrandes.length }} grupos</div>
                    <div class="text-sm font-bold text-orange-200">{{ totalPessoasGrandes }} pessoas</div>
                </button>
            </section>

            <div class="grid min-h-0 flex-1 gap-3 xl:grid-cols-[minmax(0,1fr)_420px]">
                <section class="flex min-h-0 flex-col rounded-lg bg-gray-800 p-3 sm:p-4">
                    <div class="mb-2 flex shrink-0 flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-black sm:text-xl">RESERVAS DE HOJE</h2>
                        <span class="text-sm font-bold text-gray-400">{{ reservasFiltradas.length }} de {{ reservasHoje.length }}</span>
                    </div>

                    <div class="mb-2 shrink-0">
                        <input
                            v-model="pesquisa"
                            type="search"
                            class="w-full rounded-lg border-gray-700 bg-gray-900 p-2.5 font-bold text-white placeholder:text-gray-500"
                            placeholder="Pesquisar por nome, hora ou º de pessoas..."
                        >
                    </div>

                    <div class="mb-3 flex shrink-0 flex-wrap gap-1.5">
                        <button
                            v-for="opcao in [
                                { valor: 'por-sentar', rotulo: 'Por sentar' },
                                { valor: 'sentadas', rotulo: 'Sentadas' },
                                { valor: 'grupos-grandes', rotulo: 'Grupos +10' },
                                { valor: 'todas', rotulo: 'Todas' },
                            ]"
                            :key="opcao.valor"
                            class="rounded-full px-3 py-1 text-sm font-bold transition"
                            :class="filtroEstado === opcao.valor ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                            @click="filtroEstado = opcao.valor"
                        >
                            {{ opcao.rotulo }}
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                        <div v-if="!reservasFiltradas.length" class="rounded-lg bg-gray-900 p-6 text-center text-lg font-black text-gray-300">
                            {{ pesquisa.trim() ? 'Nenhuma reserva encontrada.' : 'Sem reservas nesta categoria.' }}
                        </div>

                        <article
                            v-for="reserva in reservasFiltradas"
                            :key="reserva.id"
                            class="mb-2 grid gap-2 rounded-lg border-2 p-2.5 md:grid-cols-[80px_minmax(0,1fr)_auto]"
                            :class="reserva.estado === 'sentada' ? 'border-emerald-500 bg-emerald-900/40' : reserva.chamada_em ? 'border-amber-500 bg-amber-900/30' : 'border-gray-700 bg-gray-900'"
                        >
                            <div>
                                <div class="text-2xl font-black">{{ horaReserva(reserva) }}</div>
                                <div class="mt-1 text-center">
                                    <span class="text-2xl font-black">{{ reserva.pessoas }}</span>
                                    <span class="block text-xs font-bold text-gray-400">pessoas</span>
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="truncate text-lg font-black">{{ reserva.nome }}</span>
                                    <button
                                        v-if="reserva.token"
                                        type="button"
                                        class="shrink-0 rounded bg-gray-700 px-1.5 py-0.5 text-xs font-bold text-gray-300 hover:bg-gray-600"
                                        :title="reserva.tem_push ? 'Notificações ativas ✓ — clica para QR' : 'Mostrar QR para notificações'"
                                        @click.stop="mostrarQrReserva(reserva)"
                                    >
                                        {{ reserva.tem_push ? '🔔' : '📲' }}
                                    </button>
                                </div>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs font-bold text-gray-300">
                                    <span v-if="reserva.chamada_em" class="rounded bg-amber-500 px-1.5 py-0.5 text-gray-950">CHAMADA {{ horaData(reserva.chamada_em) }}</span>
                                    <span v-if="reserva.sentada_em" class="rounded bg-emerald-500 px-1.5 py-0.5 text-gray-950">SENTADA {{ horaData(reserva.sentada_em) }}</span>
                                    <span v-if="reserva.mesa_atribuida" class="rounded bg-emerald-400 px-2 py-0.5 text-sm font-black text-gray-950">🪑 MESA {{ reserva.mesa_atribuida }}</span>
                                    <span v-else-if="reserva.estado === 'sentada'" class="rounded bg-gray-600 px-1.5 py-0.5 text-gray-300">sem mesa</span>
                                </div>
                                <p v-if="reserva.observacoes" class="mt-1.5 rounded bg-gray-800 p-1.5 text-xs font-bold text-gray-200">{{ reserva.observacoes }}</p>
                                <div v-if="reservaEmEdicao === reserva.id" class="mt-2 space-y-2 max-w-md">
                                    <input
                                        v-model="editForm.nome"
                                        type="text"
                                        placeholder="Nome"
                                        class="w-full rounded-lg border-gray-700 bg-gray-950 p-2 font-black text-white"
                                    >
                                    <div class="grid grid-cols-2 gap-2">
                                        <input
                                            v-model="editForm.hora"
                                            type="time"
                                            class="rounded-lg border-gray-700 bg-gray-950 p-2 font-black text-white"
                                        >
                                        <input
                                            v-model="editForm.pessoas"
                                            type="number"
                                            min="1"
                                            class="rounded-lg border-gray-700 bg-gray-950 p-2 font-black text-white"
                                        >
                                    </div>
                                    <input
                                        v-model="editForm.observacoes"
                                        type="text"
                                        placeholder="Observações"
                                        class="w-full rounded-lg border-gray-700 bg-gray-950 p-2 font-bold text-white"
                                    >
                                </div>
                                <div v-if="reservaEmEdicao === reserva.id && Object.keys(editForm.errors).length" class="mt-2 rounded bg-red-700 p-2 text-sm font-bold">
                                    <div v-for="erro in editForm.errors" :key="erro">{{ erro }}</div>
                                </div>

                                <!-- Formulário de sentar / mudar mesa -->
                                <div v-if="sentarReservaId === reserva.id" class="mt-2">
                                    <p class="mb-1 text-xs font-bold uppercase text-emerald-400">
                                        {{ reserva.estado === 'sentada' ? `Mudar mesa (atual: ${reserva.mesa_atribuida || '—'})` : 'Nº da mesa' }}
                                    </p>
                                    <input
                                        v-model="sentarForm.mesa_numero"
                                        type="number"
                                        min="1"
                                        placeholder="Nº Mesa (opcional)"
                                        class="w-full rounded-lg border-gray-700 bg-gray-950 p-2.5 text-lg font-black text-white"
                                    >
                                </div>
                            </div>

                            <div v-if="reservaEmEdicao === reserva.id" class="grid min-w-40 grid-cols-2 gap-1.5 self-start">
                                <button
                                    class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-black disabled:opacity-40"
                                    :disabled="editForm.processing"
                                    @click="guardarEdicao(reserva)"
                                >
                                    GRAVAR
                                </button>
                                <button
                                    class="rounded-lg bg-gray-700 px-3 py-1.5 text-sm font-black disabled:opacity-40"
                                    :disabled="editForm.processing"
                                    @click="cancelarEdicao"
                                >
                                    FECHAR
                                </button>
                            </div>

                            <div v-else-if="sentarReservaId === reserva.id" class="grid min-w-40 gap-1.5 self-start">
                                <button
                                    class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-black disabled:opacity-40"
                                    :disabled="sentarForm.processing"
                                    @click="confirmarSentar(reserva)"
                                >
                                    CONFIRMAR
                                </button>
                                <button
                                    class="rounded-lg bg-gray-700 px-3 py-2 text-sm font-black"
                                    @click="sentarReservaId = null"
                                >
                                    CANCELAR
                                </button>
                            </div>

                            <div v-else class="grid min-w-40 grid-cols-2 gap-1.5 self-start">
                                <button
                                    class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-black disabled:opacity-40"
                                    :disabled="reserva.estado === 'sentada'"
                                    @click="editar(reserva)"
                                >
                                    EDITAR
                                </button>
                                <button
                                    class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-black text-gray-950 disabled:opacity-40"
                                    :disabled="reserva.estado === 'sentada'"
                                    @click="chamar(reserva)"
                                >
                                    CHAMAR
                                </button>
                                <button
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-black"
                                    @click="abrirSentar(reserva)"
                                >
                                    {{ reserva.estado === 'sentada' ? 'MUDAR MESA' : 'SENTADA' }}
                                </button>
                                <button
                                    class="rounded-lg bg-gray-700 px-3 py-1.5 text-sm font-black disabled:opacity-40"
                                    :disabled="reserva.estado === 'sentada'"
                                    @click="cancelar(reserva)"
                                >
                                    CANCELAR
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

                <aside class="flex min-h-0 flex-col gap-3">

                    <!-- Mesas ocupadas -->
                    <section v-if="reservasSentadas.length" class="shrink-0 rounded-lg border border-emerald-600 bg-emerald-950/60 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <h2 class="text-base font-black text-emerald-300 uppercase tracking-wide">Mesas ocupadas</h2>
                            <span class="rounded-full bg-emerald-700 px-2 py-0.5 text-xs font-black text-emerald-100">{{ reservasSentadas.length }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                            <div
                                v-for="r in reservasSentadas"
                                :key="r.id"
                                class="rounded-lg p-2 text-center"
                                :class="r.mesa_atribuida ? 'bg-emerald-800' : 'bg-gray-800 border border-gray-600'"
                            >
                                <div class="text-xl font-black" :class="r.mesa_atribuida ? 'text-emerald-200' : 'text-gray-500'">
                                    {{ r.mesa_atribuida ? r.mesa_atribuida : '?' }}
                                </div>
                                <div class="truncate text-xs font-bold text-white leading-tight mt-0.5">{{ r.nome }}</div>
                                <div class="text-[10px] font-bold text-emerald-400 mt-0.5">{{ r.pessoas }} pess · {{ horaReserva(r) }}</div>
                            </div>
                        </div>
                    </section>

                    <section class="shrink-0 rounded-lg bg-gray-800 p-3 sm:p-4">
                        <h2 class="mb-3 text-xl font-black">NOVA RESERVA</h2>
                        <form class="grid gap-2" @submit.prevent="criarReserva">
                            <input v-model="form.nome" class="rounded-lg border-gray-700 bg-gray-900 p-3 text-lg font-black text-white" placeholder="Nome">
                            <div class="grid grid-cols-3 gap-2">
                                <input v-model="form.data_reserva" type="date" class="rounded-lg border-gray-700 bg-gray-900 p-3 font-black text-white">
                                <input v-model="form.hora" type="time" class="rounded-lg border-gray-700 bg-gray-900 p-3 font-black text-white">
                                <input v-model="form.pessoas" type="number" min="1" class="rounded-lg border-gray-700 bg-gray-900 p-3 font-black text-white">
                            </div>
                            <textarea v-model="form.observacoes" rows="2" class="rounded-lg border-gray-700 bg-gray-900 p-3 font-bold text-white" placeholder="Observacoes"></textarea>
                            <div v-if="Object.keys(form.errors).length" class="rounded bg-red-700 p-2 font-bold">
                                <div v-for="erro in form.errors" :key="erro">{{ erro }}</div>
                            </div>
                            <button class="rounded-lg bg-blue-600 p-4 text-lg font-black disabled:opacity-50" :disabled="form.processing">
                                CRIAR RESERVA
                            </button>
                        </form>
                    </section>

                    <section class="flex min-h-0 flex-1 flex-col rounded-lg bg-gray-800 p-3 sm:p-4">
                        <h2 class="mb-2 shrink-0 text-lg font-black">PROXIMAS</h2>

                        <div class="mb-2 shrink-0">
                            <input
                                v-model="pesquisaProximas"
                                type="search"
                                class="w-full rounded-lg border-gray-700 bg-gray-900 p-2 text-sm font-bold text-white placeholder:text-gray-500"
                                placeholder="Pesquisar..."
                            >
                        </div>

                        <div class="mb-2 flex shrink-0 flex-wrap gap-1.5">
                            <button
                                v-for="opcao in [
                                    { valor: 'todas', rotulo: 'Todas' },
                                    { valor: 'amanha', rotulo: 'Amanha' },
                                    { valor: 'semana', rotulo: 'Esta semana' },
                                ]"
                                :key="opcao.valor"
                                class="rounded-full px-2.5 py-1 text-xs font-bold transition"
                                :class="filtroProximas === opcao.valor ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                                @click="filtroProximas = opcao.valor"
                            >
                                {{ opcao.rotulo }}
                            </button>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                            <div v-if="!proximasFiltradas.length" class="rounded-lg bg-gray-900 p-4 text-center text-sm font-bold text-gray-300">
                                Nenhuma reserva encontrada.
                            </div>
                            <div v-for="reserva in proximasFiltradas" :key="reserva.id" class="mb-2 rounded-lg bg-gray-900 p-2.5">
                                <div class="flex items-center justify-between gap-3">
                                    <strong class="truncate text-base">{{ reserva.nome }}</strong>
                                    <span class="font-black text-blue-300">{{ dia(reserva.data) }}</span>
                                </div>
                                <div class="mt-1 text-sm font-bold text-gray-300">{{ horaReserva(reserva) }} · {{ reserva.pessoas }} pessoas</div>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>

        <!-- Modal QR de reserva -->
        <div v-if="qrReserva" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="fecharQrReserva">
            <div class="w-full max-w-xs rounded-2xl bg-white p-5 text-center text-slate-950 shadow-2xl">
                <h2 class="text-xl font-black">📲 Notificações</h2>
                <p class="mt-1 text-sm font-bold text-slate-500">{{ qrReserva.nome }}</p>
                <p class="mt-2 text-xs font-bold text-slate-400">O cliente escaneia com o telemóvel e ativa as notificações</p>
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR da reserva" class="mx-auto my-4 h-56 w-56 rounded-xl border p-2">
                <a :href="qrReserva.url" target="_blank" class="block truncate text-xs text-blue-600 underline">{{ qrReserva.url }}</a>
                <button class="mt-4 w-full rounded-xl bg-gray-900 p-3 font-black text-white" @click="fecharQrReserva">FECHAR</button>
            </div>
        </div>

        <ChamarComissaoModal
            v-if="chamandoComissao"
            :operador-nome="operadorNome || posNome"
            @fechar="chamandoComissao = false"
        />
    </main>
</template>

<style scoped>
.pos-reservas {
    height: 100dvh;
}

@media (max-width: 1279px) {
    .pos-reservas {
        height: auto;
        min-height: 100dvh;
        overflow: auto;
    }
}
</style>
