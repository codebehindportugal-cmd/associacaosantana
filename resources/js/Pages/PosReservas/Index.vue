<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
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
const menuAtivo = ref('ver');
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
                menuAtivo.value = 'ver';
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

const eliminar = (reserva) => {
    if (confirm(`Eliminar definitivamente a reserva de ${reserva.nome}?`)) {
        router.delete(route('pos.reservas.destroy', reserva.id), { preserveScroll: true });
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

// ---------------------------------------------------------------------------
// Apresentação (redesign)
// ---------------------------------------------------------------------------
const cartoesFiltro = computed(() => [
    { valor: 'todas', label: 'Total hoje', cor: 'text-azul', valorTxt: `${(props.reservasHoje ?? []).length} reservas`, sub: `${totalPessoasHoje.value} pessoas` },
    { valor: 'por-sentar', label: 'Por sentar', cor: 'text-suave', valorTxt: `${gruposPorSentar.value} grupos`, sub: `${pessoasPorSentar.value} pessoas` },
    { valor: 'sentadas', label: 'Sentadas', cor: 'text-verde', valorTxt: `${pessoasSentadas.value} pessoas`, sub: `${reservasSentadas.value.length} ${reservasSentadas.value.length === 1 ? 'grupo' : 'grupos'}` },
    { valor: 'grupos-grandes', label: 'Grupos grandes (+10)', cor: 'text-laranja', valorTxt: `${gruposGrandes.value.length} grupos`, sub: `${totalPessoasGrandes.value} pessoas` },
]);
const filtrosLista = [
    { valor: 'por-sentar', rotulo: 'Por sentar' },
    { valor: 'sentadas', rotulo: 'Sentadas' },
    { valor: 'chamadas', rotulo: 'Chamadas' },
    { valor: 'grupos-grandes', rotulo: 'Grupos +10' },
    { valor: 'todas', rotulo: 'Todas' },
];
const periodos = [
    { valor: 'todas', rotulo: 'Todas' },
    { valor: 'amanha', rotulo: 'Amanhã' },
    { valor: 'semana', rotulo: 'Esta semana' },
];
const mudarPessoas = (delta) => { form.pessoas = Math.max(1, Number(form.pessoas || 0) + delta); };

</script>

<template>
    <main class="pos-reservas flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums xl:h-[100dvh] xl:overflow-hidden">
        <header class="flex shrink-0 flex-wrap items-center justify-between gap-2 bg-escuro px-4 py-2.5 text-white sm:px-6 xl:h-16 xl:py-0">
            <div class="flex min-w-0 items-baseline gap-4">
                <h1 class="text-xl font-extrabold">POS Reservas</h1>
                <span class="truncate text-sm text-escuro-inativo">{{ operadorNome || posNome }} · {{ agora.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' }) }}</span>
            </div>
            <div role="tablist" aria-label="Menu" class="order-3 flex w-full gap-1 rounded-xl bg-escuro-2 p-1 sm:order-none sm:w-auto">
                <button type="button" role="tab" :aria-selected="menuAtivo === 'ver'" class="flex h-11 flex-1 items-center justify-center gap-2 rounded-[10px] px-4 text-[15px] font-bold sm:flex-none" :class="menuAtivo === 'ver' ? 'bg-white text-tinta' : 'text-escuro-inativo'" @click="menuAtivo = 'ver'">
                    Ver reservas
                    <span v-if="gruposPorSentar" class="flex h-6 min-w-6 items-center justify-center rounded-full bg-laranja px-1.5 text-xs text-white">{{ gruposPorSentar }}</span>
                </button>
                <button type="button" role="tab" :aria-selected="menuAtivo === 'nova'" class="h-11 flex-1 rounded-[10px] px-4 text-[15px] font-bold sm:flex-none" :class="menuAtivo === 'nova' ? 'bg-white text-tinta' : 'text-escuro-inativo'" @click="menuAtivo = 'nova'">+ Nova reserva</button>
            </div>
            <div class="flex gap-2.5">
                <button type="button" class="h-11 rounded-[10px] bg-laranja px-4 text-[15px] font-bold text-white" @click="chamandoComissao = true">Chamar comissão</button>
                <button type="button" class="h-11 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-bold text-white" @click="logout">Sair</button>
            </div>
        </header>

        <!-- VER RESERVAS -->
        <div v-if="menuAtivo === 'ver'" class="flex min-h-0 flex-1 flex-col gap-4 p-4 sm:p-6 sm:pt-4">
            <div role="group" aria-label="Filtrar reservas" class="grid shrink-0 grid-cols-2 gap-3 xl:grid-cols-4">
                <button
                    v-for="f in cartoesFiltro"
                    :key="f.valor"
                    type="button"
                    class="rounded-[14px] bg-white px-4 py-2.5 text-left"
                    :class="filtroEstado === f.valor ? 'border-[3px] border-suave' : 'border border-linha'"
                    :aria-pressed="filtroEstado === f.valor"
                    @click="filtroEstado = f.valor"
                >
                    <span class="block text-sm font-bold" :class="f.cor">{{ f.label }}</span>
                    <span class="block text-2xl font-extrabold leading-tight">{{ f.valorTxt }}</span>
                    <span class="block text-xs text-suave">{{ f.sub }}</span>
                </button>
            </div>

            <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(0,1fr)_330px]">
                <section aria-label="Reservas de hoje" class="flex min-h-0 flex-col rounded-[14px] border border-linha bg-white">
                    <div class="shrink-0 border-b border-linha px-4 py-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <h2 class="text-lg font-extrabold">Reservas de hoje</h2>
                            <label class="min-w-[200px] flex-1">
                                <span class="sr-only">Pesquisar reservas</span>
                                <input v-model="pesquisa" type="search" class="h-12 w-full rounded-[10px] border-linha-forte bg-fundo text-base text-tinta placeholder:text-suave-2 focus:border-verde focus:ring-verde" placeholder="Pesquisar por nome, hora ou nº de pessoas…">
                            </label>
                            <span class="text-sm text-suave">{{ reservasFiltradas.length }} de {{ reservasHoje.length }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <button
                                v-for="opcao in filtrosLista"
                                :key="opcao.valor"
                                type="button"
                                class="h-11 rounded-full px-3.5 text-sm font-bold"
                                :class="filtroEstado === opcao.valor ? 'bg-azul text-white' : 'border border-linha-forte bg-white text-tinta'"
                                :aria-pressed="filtroEstado === opcao.valor"
                                @click="filtroEstado = opcao.valor"
                            >{{ opcao.rotulo }}</button>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 space-y-2.5 overflow-y-auto p-3">
                        <p v-if="!reservasFiltradas.length" class="py-8 text-center text-[17px] font-semibold text-suave">
                            {{ pesquisa.trim() ? 'Nenhuma reserva encontrada.' : 'Sem reservas nesta categoria.' }}
                        </p>

                        <article
                            v-for="reserva in reservasFiltradas"
                            :key="reserva.id"
                            class="grid gap-3 rounded-[14px] p-3 md:grid-cols-[90px_minmax(0,1fr)_300px]"
                            :class="reserva.estado === 'sentada' ? 'border-2 border-verde bg-verde-claro' : reserva.chamada_em ? 'border-2 border-laranja bg-laranja-claro/60' : 'border border-linha bg-white'"
                        >
                            <div class="flex items-center gap-3 md:flex-col md:items-center md:gap-0 md:text-center">
                                <span class="text-2xl font-extrabold">{{ horaReserva(reserva) }}</span>
                                <span class="text-2xl font-extrabold leading-tight">{{ reserva.pessoas }}</span>
                                <span class="text-xs text-suave">pessoas</span>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-xl font-bold">{{ reserva.nome }}</span>
                                    <button
                                        v-if="reserva.token"
                                        type="button"
                                        class="h-11 shrink-0 rounded-[10px] border border-linha-forte bg-white px-3 text-xs font-bold text-suave"
                                        :title="reserva.tem_push ? 'Notificações ativas — toca para ver o QR' : 'Mostrar QR para notificações'"
                                        @click.stop="mostrarQrReserva(reserva)"
                                    >{{ reserva.tem_push ? 'Notificações ativas' : 'QR notificações' }}</button>
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs font-bold">
                                    <span v-if="reserva.estado !== 'sentada' && !reserva.chamada_em" class="rounded-full bg-fundo px-2.5 py-1 text-suave">Por sentar</span>
                                    <span v-if="reserva.chamada_em" class="rounded-full bg-laranja-claro px-2.5 py-1 text-laranja-texto">Chamada {{ horaData(reserva.chamada_em) }}</span>
                                    <span v-if="reserva.sentada_em" class="rounded-full bg-verde px-2.5 py-1 text-white">Sentada {{ horaData(reserva.sentada_em) }}</span>
                                    <span v-if="reserva.mesa_atribuida" class="rounded-full bg-verde-claro2 px-2.5 py-1 text-sm text-verde-escuro">Mesa {{ reserva.mesa_atribuida }}</span>
                                    <span v-else-if="reserva.estado === 'sentada'" class="rounded-full bg-fundo px-2.5 py-1 text-suave">Sem mesa</span>
                                    <span v-if="Number(reserva.pessoas) > 10" class="rounded-full bg-laranja-claro px-2.5 py-1 text-laranja-texto">Grupo grande</span>
                                </div>
                                <p v-if="reserva.observacoes" class="mt-2 rounded-lg bg-fundo px-2.5 py-1.5 text-sm text-suave">{{ reserva.observacoes }}</p>

                                <div v-if="reservaEmEdicao === reserva.id" class="mt-3 max-w-md space-y-2">
                                    <input v-model="editForm.nome" type="text" placeholder="Nome" aria-label="Nome" class="h-12 w-full rounded-[10px] border-linha-forte font-bold text-tinta focus:border-verde focus:ring-verde">
                                    <div class="grid grid-cols-2 gap-2">
                                        <input v-model="editForm.hora" type="time" aria-label="Hora" class="h-12 rounded-[10px] border-linha-forte font-bold text-tinta focus:border-verde focus:ring-verde">
                                        <input v-model="editForm.pessoas" type="number" min="1" aria-label="Pessoas" class="h-12 rounded-[10px] border-linha-forte font-bold text-tinta focus:border-verde focus:ring-verde">
                                    </div>
                                    <input v-model="editForm.observacoes" type="text" placeholder="Observações" aria-label="Observações" class="h-12 w-full rounded-[10px] border-linha-forte text-tinta focus:border-verde focus:ring-verde">
                                </div>
                                <div v-if="reservaEmEdicao === reserva.id && Object.keys(editForm.errors).length" role="alert" class="mt-2 rounded-[10px] bg-perigo-claro p-2 text-sm font-semibold text-perigo-texto">
                                    <div v-for="erro in editForm.errors" :key="erro">{{ erro }}</div>
                                </div>
                                <div v-if="sentarReservaId === reserva.id" class="mt-3">
                                    <label class="block text-sm font-bold text-verde-escuro">
                                        {{ reserva.estado === 'sentada' ? `Mudar mesa (atual: ${reserva.mesa_atribuida || '—'})` : 'Nº da mesa' }}
                                        <input :class="{ '!border-perigo': sentarForm.errors.mesa_numero }" v-model="sentarForm.mesa_numero" type="number" min="1" placeholder="Nº da mesa (opcional)" class="mt-1 h-14 w-full max-w-xs rounded-[10px] border-linha-forte text-xl font-bold text-tinta focus:border-verde focus:ring-verde" autofocus><span v-if="sentarForm.errors.mesa_numero" class="block text-[13px] font-semibold text-perigo-texto">{{ sentarForm.errors.mesa_numero }}</span>
                                    </label>
                                    <span v-if="!sentarForm.mesa_numero" class="mt-1.5 block rounded-lg bg-laranja-claro px-2.5 py-1.5 text-sm font-semibold text-laranja-texto">Sem nº de mesa não saberás onde está a pessoa se sair sem pagar.</span>
                                </div>
                            </div>

                            <div v-if="reservaEmEdicao === reserva.id" class="grid grid-cols-2 gap-2 self-start">
                                <button type="button" class="h-14 rounded-[10px] bg-verde font-bold text-white hover:bg-verde-escuro disabled:opacity-45" :disabled="editForm.processing" @click="guardarEdicao(reserva)">Gravar</button>
                                <button type="button" class="h-14 rounded-[10px] border border-linha-forte bg-white font-bold disabled:opacity-45" :disabled="editForm.processing" @click="cancelarEdicao">Fechar</button>
                            </div>
                            <div v-else-if="sentarReservaId === reserva.id" class="grid grid-cols-2 gap-2 self-start">
                                <AvisoErros :errors="sentarForm.errors" :excluir="['mesa_numero']" class="col-span-2" />
                                <button type="button" class="h-14 rounded-[10px] bg-verde font-bold text-white hover:bg-verde-escuro disabled:opacity-45" :disabled="sentarForm.processing" @click="confirmarSentar(reserva)">Confirmar</button>
                                <button type="button" class="h-14 rounded-[10px] border border-linha-forte bg-white font-bold" @click="sentarReservaId = null">Cancelar</button>
                            </div>
                            <div v-else class="grid grid-cols-6 gap-1.5 self-start">
                                <button type="button" class="col-span-4 h-[52px] rounded-[10px] bg-verde text-base font-bold text-white hover:bg-verde-escuro" @click="abrirSentar(reserva)">{{ reserva.estado === 'sentada' ? 'Mudar mesa' : 'Sentar' }}</button>
                                <button type="button" class="col-span-2 h-[52px] rounded-[10px] bg-laranja text-base font-bold text-white disabled:opacity-45" :disabled="reserva.estado === 'sentada'" @click="chamar(reserva)">Chamar</button>
                                <button type="button" class="col-span-2 h-11 rounded-[10px] border border-linha-forte bg-white text-sm font-bold disabled:opacity-45" :disabled="reserva.estado === 'sentada'" @click="editar(reserva)">Editar</button>
                                <button type="button" class="col-span-2 h-11 rounded-[10px] border border-linha-forte bg-white text-sm font-bold disabled:opacity-45" :disabled="reserva.estado === 'sentada'" @click="cancelar(reserva)">Cancelar</button>
                                <button type="button" class="col-span-2 h-11 rounded-[10px] border border-perigo/30 bg-perigo-claro text-sm font-bold text-perigo-texto" @click="eliminar(reserva)">Eliminar</button>
                            </div>
                        </article>
                    </div>
                </section>

                <aside aria-label="Próximas reservas" class="flex min-h-0 flex-col rounded-[14px] border border-linha bg-white">
                    <div class="shrink-0 space-y-2.5 border-b border-linha p-4">
                        <h2 class="text-lg font-extrabold">Próximas</h2>
                        <label class="block"><span class="sr-only">Pesquisar próximas</span>
                            <input v-model="pesquisaProximas" type="search" class="h-11 w-full rounded-[10px] border-linha-forte bg-fundo text-[15px] text-tinta placeholder:text-suave-2 focus:border-verde focus:ring-verde" placeholder="Pesquisar…">
                        </label>
                        <div role="group" aria-label="Período" class="flex flex-wrap gap-1.5">
                            <button
                                v-for="opcao in periodos"
                                :key="opcao.valor"
                                type="button"
                                class="h-11 rounded-full px-3.5 text-sm font-bold"
                                :class="filtroProximas === opcao.valor ? 'bg-azul text-white' : 'border border-linha-forte bg-white text-tinta'"
                                :aria-pressed="filtroProximas === opcao.valor"
                                @click="filtroProximas = opcao.valor"
                            >{{ opcao.rotulo }}</button>
                        </div>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto px-4">
                        <p v-if="!proximasFiltradas.length" class="py-6 text-center text-sm text-suave">Nenhuma reserva encontrada.</p>
                        <div v-for="reserva in proximasFiltradas" :key="reserva.id" class="border-b border-linha-fraca py-2.5 last:border-b-0">
                            <div class="flex items-center justify-between gap-3">
                                <strong class="truncate text-base font-bold">{{ reserva.nome }}</strong>
                                <span class="shrink-0 text-sm font-bold text-azul">{{ dia(reserva.data) }}</span>
                            </div>
                            <span class="text-sm text-suave">{{ horaReserva(reserva) }} · {{ reserva.pessoas }} pessoas</span>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- NOVA RESERVA -->
        <div v-if="menuAtivo === 'nova'" class="flex min-h-0 flex-1 items-start justify-center overflow-y-auto p-4 sm:p-6">
            <form class="grid w-full max-w-lg gap-4 rounded-[14px] border border-linha bg-white p-5 sm:p-6" @submit.prevent="criarReserva">
                <h2 class="text-2xl font-extrabold">Nova reserva</h2>
                <label class="block text-sm font-semibold text-suave">Nome *
                    <input v-model="form.nome" type="text" required class="mt-1 h-14 w-full rounded-[10px] border-linha-forte text-lg font-bold text-tinta focus:border-verde focus:ring-verde" placeholder="Nome de quem reserva">
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block text-sm font-semibold text-suave">Data
                        <input v-model="form.data_reserva" type="date" class="mt-1 h-14 w-full rounded-[10px] border-linha-forte font-bold text-tinta focus:border-verde focus:ring-verde">
                    </label>
                    <label class="block text-sm font-semibold text-suave">Hora
                        <input v-model="form.hora" type="time" class="mt-1 h-14 w-full rounded-[10px] border-linha-forte font-bold text-tinta focus:border-verde focus:ring-verde">
                    </label>
                </div>
                <div>
                    <span class="text-sm font-semibold text-suave">Pessoas</span>
                    <div class="mt-1 flex items-center gap-3">
                        <button type="button" aria-label="Menos uma pessoa" class="h-14 w-14 shrink-0 rounded-[10px] border border-linha-forte bg-fundo text-3xl font-bold" @click="mudarPessoas(-1)">−</button>
                        <input v-model="form.pessoas" type="number" min="1" aria-label="Pessoas" class="h-14 w-full min-w-0 rounded-[10px] border-linha-forte text-center text-3xl font-extrabold text-tinta focus:border-verde focus:ring-verde">
                        <button type="button" aria-label="Mais uma pessoa" class="h-14 w-14 shrink-0 rounded-[10px] border border-linha-forte bg-fundo text-3xl font-bold" @click="mudarPessoas(1)">+</button>
                    </div>
                </div>
                <label class="block text-sm font-semibold text-suave">Observações (opcional)
                    <textarea v-model="form.observacoes" rows="3" class="mt-1 w-full rounded-[10px] border-linha-forte text-tinta focus:border-verde focus:ring-verde"></textarea>
                </label>
                <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-[10px] bg-perigo-claro p-3 font-semibold text-perigo-texto">
                    <div v-for="erro in form.errors" :key="erro">{{ erro }}</div>
                </div>
                <button class="h-16 rounded-[10px] bg-verde text-xl font-bold text-white hover:bg-verde-escuro disabled:opacity-45" :disabled="form.processing">
                    {{ form.processing ? 'A criar…' : 'Criar reserva' }}
                </button>
            </form>
        </div>

        <!-- Modal QR de reserva -->
        <div v-if="qrReserva" class="fixed inset-0 z-50 flex items-center justify-center bg-escuro/60 p-4" @click.self="fecharQrReserva">
            <div role="dialog" aria-label="Notificações da reserva" class="w-full max-w-xs rounded-[14px] bg-white p-5 text-center">
                <h2 class="text-xl font-extrabold">Notificações</h2>
                <span class="mt-1 block text-[15px] font-semibold text-suave">{{ qrReserva.nome }}</span>
                <span class="mt-2 block text-sm text-suave">O cliente lê com o telemóvel e ativa as notificações. Fecha sozinho em 3 minutos.</span>
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR da reserva" class="mx-auto my-4 h-56 w-56 rounded-[14px] border border-linha p-2">
                <a :href="qrReserva.url" target="_blank" class="block truncate text-xs underline">{{ qrReserva.url }}</a>
                <button type="button" class="mt-4 h-14 w-full rounded-[10px] bg-escuro font-bold text-white" @click="fecharQrReserva">Fechar</button>
            </div>
        </div>

        <ChamarComissaoModal
            v-if="chamandoComissao"
            :operador-nome="operadorNome || posNome"
            @fechar="chamandoComissao = false"
        />
    </main>
</template>
