<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    posNome: String,
    operadorNome: String,
    hoje: String,
    reservasHoje: Array,
    proximasReservas: Array,
});

const agora = ref(new Date());
const reservaEmEdicao = ref(null);
const pesquisa = ref('');
const filtroEstado = ref('por-sentar');
const pesquisaProximas = ref('');
const filtroProximas = ref('todas');
let relogio = null;
let refresh = null;

const form = useForm({
    nome: '',
    data_reserva: props.hoje,
    hora: '20:00',
    pessoas: 2,
    observacoes: '',
});

const reservasPendentes = computed(() => (props.reservasHoje ?? []).filter((reserva) => reserva.estado !== 'sentada'));
const reservasChamadas = computed(() => reservasPendentes.value.filter((reserva) => reserva.chamada_em));
const reservasSentadas = computed(() => (props.reservasHoje ?? []).filter((reserva) => reserva.estado === 'sentada'));

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
    hora: '',
    pessoas: 1,
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
    editForm.hora = horaReserva(reserva);
    editForm.pessoas = reserva.pessoas;
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

const sentar = (reserva) => {
    router.patch(route('pos.reservas.sentar', reserva.id), {}, { preserveScroll: true });
};

const cancelar = (reserva) => {
    if (confirm(`Cancelar a reserva de ${reserva.nome}?`)) {
        router.patch(route('pos.reservas.cancelar', reserva.id), {}, { preserveScroll: true });
    }
};

const logout = () => router.post(route('pos.logout'));
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
});
</script>

<template>
    <main class="pos-reservas flex h-screen overflow-hidden bg-gray-900 p-3 text-white sm:p-4">
        <div class="flex min-h-0 w-full flex-col">
            <header class="mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black sm:text-3xl">POS RESERVAS</h1>
                    <p class="font-bold text-gray-300">{{ operadorNome || posNome }} - {{ agora.toLocaleTimeString('pt-PT') }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="rounded-lg bg-red-600 px-3 py-1.5 text-sm font-black" @click="logout">LOGOUT</button>
                </div>
            </header>

            <section class="mb-3 grid shrink-0 gap-2 md:grid-cols-4">
                <button
                    type="button"
                    class="rounded-lg bg-blue-700 p-2.5 text-left transition"
                    :class="filtroEstado === 'todas' ? 'ring-2 ring-white' : 'opacity-90 hover:opacity-100'"
                    @click="filtroEstado = 'todas'"
                >
                    <div class="text-sm font-bold">Hoje</div>
                    <div class="text-2xl font-black">{{ reservasHoje.length }}</div>
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-amber-600 p-2.5 text-left transition"
                    :class="filtroEstado === 'chamadas' ? 'ring-2 ring-white' : 'opacity-90 hover:opacity-100'"
                    @click="filtroEstado = 'chamadas'"
                >
                    <div class="text-sm font-bold">Chamadas</div>
                    <div class="text-2xl font-black">{{ reservasChamadas.length }}</div>
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-emerald-700 p-2.5 text-left transition"
                    :class="filtroEstado === 'sentadas' ? 'ring-2 ring-white' : 'opacity-90 hover:opacity-100'"
                    @click="filtroEstado = 'sentadas'"
                >
                    <div class="text-sm font-bold">Sentadas</div>
                    <div class="text-2xl font-black">{{ reservasSentadas.length }}</div>
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-gray-800 p-2.5 text-left transition"
                    :class="filtroEstado === 'por-sentar' ? 'ring-2 ring-white' : 'opacity-90 hover:opacity-100'"
                    @click="filtroEstado = 'por-sentar'"
                >
                    <div class="text-sm font-bold">Por sentar</div>
                    <div class="text-2xl font-black">{{ reservasPendentes.length }}</div>
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
                            placeholder="Pesquisar por nome, hora ou nº de pessoas..."
                        >
                    </div>

                    <div class="mb-3 flex shrink-0 flex-wrap gap-1.5">
                        <button
                            v-for="opcao in [
                                { valor: 'por-sentar', rotulo: 'Por sentar' },
                                { valor: 'chamadas', rotulo: 'Chamadas' },
                                { valor: 'sentadas', rotulo: 'Sentadas' },
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
                                <div class="mt-1 rounded bg-gray-800 px-2 py-0.5 text-center text-xs font-black">
                                    {{ reserva.pessoas }} PAX
                                </div>
                            </div>

                            <div class="min-w-0">
                                <div class="truncate text-lg font-black">{{ reserva.nome }}</div>
                                <div class="mt-1 flex flex-wrap gap-1.5 text-xs font-bold text-gray-300">
                                    <span v-if="reserva.chamada_em" class="rounded bg-amber-500 px-1.5 py-0.5 text-gray-950">CHAMADA {{ horaData(reserva.chamada_em) }}</span>
                                    <span v-if="reserva.sentada_em" class="rounded bg-emerald-500 px-1.5 py-0.5 text-gray-950">SENTADA {{ horaData(reserva.sentada_em) }}</span>
                                </div>
                                <p v-if="reserva.observacoes" class="mt-1.5 rounded bg-gray-800 p-1.5 text-xs font-bold text-gray-200">{{ reserva.observacoes }}</p>
                                <div v-if="reservaEmEdicao === reserva.id" class="mt-2 grid max-w-md grid-cols-2 gap-2">
                                    <select v-model="editForm.hora" class="rounded-lg border-gray-700 bg-gray-950 p-2 font-black text-white">
                                        <option v-for="hora in horasDisponiveis" :key="hora" :value="hora">
                                            {{ hora }}
                                        </option>
                                    </select>
                                    <input
                                        v-model="editForm.pessoas"
                                        type="number"
                                        min="1"
                                        class="rounded-lg border-gray-700 bg-gray-950 p-2 font-black text-white"
                                    >
                                </div>
                                <div v-if="reservaEmEdicao === reserva.id && Object.keys(editForm.errors).length" class="mt-2 rounded bg-red-700 p-2 text-sm font-bold">
                                    <div v-for="erro in editForm.errors" :key="erro">{{ erro }}</div>
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
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-black disabled:opacity-40"
                                    :disabled="reserva.estado === 'sentada'"
                                    @click="sentar(reserva)"
                                >
                                    SENTADA
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
                                <div class="mt-1 text-sm font-bold text-gray-300">{{ horaReserva(reserva) }} - {{ reserva.pessoas }} PAX</div>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
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
