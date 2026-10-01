<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { watch } from 'vue';

const props = defineProps({ reservas: Array, dataFiltro: String });

const hoje = new Date().toISOString().slice(0, 10);
const dataEscolhida = ref(props.dataFiltro || hoje);

const filtrarData = () => {
    router.get(route('reservas.index'), { data: dataEscolhida.value }, { preserveScroll: true });
};

// Filtros locais (pesquisa + estado)
const pesquisa = ref('');
const filtroEstado = ref('');
const reservasFiltradas = computed(() => (props.reservas ?? []).filter((r) => {
    if (filtroEstado.value && r.estado !== filtroEstado.value) return false;
    const termo = pesquisa.value.trim().toLowerCase();
    if (termo && !`${r.nome} ${r.telefone ?? ''}`.toLowerCase().includes(termo)) return false;
    return true;
}));

const form = useForm({
    nome: '',
    data_reserva: hoje,
    hora: '20:00',
    pessoas: 2,
    estado: 'confirmada',
});

const editandoId = ref(null);
const editForm = useForm({ nome: '', data: '', hora: '', pessoas: 1, observacoes: '' });

const editar = (reserva) => {
    editandoId.value = reserva.id;
    editForm.nome = reserva.nome;
    editForm.data = reserva.data;
    editForm.hora = reserva.hora?.slice(0, 5) ?? '';
    editForm.pessoas = reserva.pessoas;
    editForm.observacoes = reserva.observacoes ?? '';
};

const cancelarEdicao = () => { editandoId.value = null; editForm.clearErrors(); };

const guardarEdicao = (reserva) => {
    editForm.patch(route('reservas.update', reserva.id), {
        preserveScroll: true,
        onSuccess: () => cancelarEdicao(),
    });
};

watch(dataEscolhida, filtrarData);

const eliminar = (reserva) => {
    if (confirm(`Eliminar reserva de ${reserva.nome}?`)) {
        router.delete(route('reservas.destroy', reserva.id), { preserveScroll: true });
    }
};

const totalPessoasSentadas = computed(() =>
    (props.reservas ?? [])
        .filter((r) => r.estado === 'sentada')
        .reduce((soma, r) => soma + (Number(r.pessoas) || 0), 0)
);

const totalPessoasPorSentar = computed(() =>
    (props.reservas ?? [])
        .filter((r) => r.estado !== 'sentada' && r.estado !== 'cancelada')
        .reduce((soma, r) => soma + (Number(r.pessoas) || 0), 0)
);

const criarReserva = () => {
    form
        .transform((dados) => ({
            ...dados,
            data: dados.data_reserva,
        }))
        .post(route('reservas.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('nome');
                form.pessoas = 2;
            },
            onFinish: () => form.transform((dados) => dados),
        });
};

const formatarDia = (data) => new Date(`${data}T00:00:00`).toLocaleDateString('pt-PT', {
    weekday: 'short',
    day: '2-digit',
    month: '2-digit',
});

const formatarHoraData = (data) => {
    if (!data) {
        return 'Não';
    }

    return new Date(data).toLocaleTimeString('pt-PT', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const marcarChamada = (reserva) => {
    router.patch(route('reservas.chamar', reserva.id), {}, { preserveScroll: true });
};

const marcarSentada = (reserva) => {
    if (!confirm(`Marcar ${reserva.nome} como sentada?`)) {
        return;
    }

    router.patch(route('reservas.sentar', reserva.id), {}, { preserveScroll: true });
};

// Apresentação
const filtrosEstado = [['', 'Todas'], ['confirmada', 'Confirmadas'], ['sentada', 'Sentadas'], ['cancelada', 'Canceladas']];
const estadoNome = (estado) => ({ confirmada: 'Confirmada', sentada: 'Sentada', cancelada: 'Cancelada' }[estado] ?? estado);
const estadoClass = (estado) => estado === 'sentada'
    ? 'bg-verde-claro2 text-verde-escuro'
    : estado === 'cancelada' ? 'bg-perigo-claro text-perigo-texto' : 'bg-[#E8EEFA] text-[#1E4592]';
</script>

<template>
    <AppLayout>
        <div class="flex flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-[30px] font-extrabold leading-tight">Reservas</h1>
                    <p class="text-[15px] text-suave">{{ props.reservas.length }} reservas</p>
                </div>
                <label class="flex flex-col gap-1.5">
                    <span class="text-sm font-bold text-suave">Data</span>
                    <input v-model="dataEscolhida" type="date" class="h-12 rounded-[10px] border-linha-forte bg-white px-3 text-base font-bold focus:border-verde focus:ring-verde">
                </label>
            </div>

            <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3">
                <div class="flex flex-col gap-0.5 rounded-[14px] border border-[#C6E2D4] bg-verde-claro p-5">
                    <span class="text-[34px] font-extrabold leading-tight text-verde-escuro">{{ totalPessoasSentadas }}</span>
                    <span class="text-sm font-bold text-verde-escuro">Pessoas sentadas</span>
                </div>
                <div class="flex flex-col gap-0.5 rounded-[14px] border border-[#C9D6F2] bg-[#E8EEFA] p-5">
                    <span class="text-[34px] font-extrabold leading-tight text-[#1E4592]">{{ totalPessoasPorSentar }}</span>
                    <span class="text-sm font-bold text-[#1E4592]">Por sentar</span>
                </div>
                <div class="col-span-2 flex flex-col gap-0.5 rounded-[14px] border border-linha bg-white p-5 sm:col-span-1">
                    <span class="text-[34px] font-extrabold leading-tight">{{ totalPessoasSentadas + totalPessoasPorSentar }}</span>
                    <span class="text-sm font-bold text-suave">Total pessoas hoje</span>
                </div>
            </div>

            <form aria-labelledby="nova-reserva" class="flex flex-col gap-3 rounded-[14px] border border-linha bg-white p-5" @submit.prevent="criarReserva">
                <h2 id="nova-reserva" class="text-lg font-extrabold">Nova reserva</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_140px_170px_140px_auto] lg:items-end">
                    <label class="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-1">
                        <span class="text-sm font-bold text-suave">Nome</span>
                        <input v-model="form.nome" class="h-12 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde" placeholder="Nome">
                    </label>
                    <label class="flex flex-col gap-1.5">
                        <span class="text-sm font-bold text-suave">Pessoas</span>
                        <input v-model="form.pessoas" type="number" min="1" class="h-12 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde font-bold" placeholder="Pessoas">
                    </label>
                    <label class="flex flex-col gap-1.5">
                        <span class="text-sm font-bold text-suave">Dia</span>
                        <input v-model="form.data_reserva" type="date" class="h-12 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde">
                    </label>
                    <label class="flex flex-col gap-1.5">
                        <span class="text-sm font-bold text-suave">Hora</span>
                        <input v-model="form.hora" type="time" class="h-12 w-full rounded-[10px] border-linha-forte px-3 text-base text-tinta focus:border-verde focus:ring-verde">
                    </label>
                    <button type="submit" class="h-12 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white hover:bg-verde-escuro disabled:opacity-60" :disabled="form.processing">
                        {{ form.processing ? 'A criar...' : 'Criar reserva' }}
                    </button>
                </div>
                <div v-if="Object.keys(form.errors).length" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto">
                    <div v-for="erro in form.errors" :key="erro">{{ erro }}</div>
                </div>
            </form>

            <div class="flex flex-wrap items-center gap-2.5">
                <div role="group" aria-label="Filtrar por estado" class="flex flex-wrap gap-2">
                    <button
                        v-for="opcao in filtrosEstado"
                        :key="opcao[0]"
                        type="button"
                        :aria-pressed="filtroEstado === opcao[0]"
                        class="inline-flex h-11 items-center rounded-full border px-[18px] text-[15px] font-bold transition"
                        :class="filtroEstado === opcao[0] ? 'border-escuro bg-escuro text-white' : 'border-linha-forte bg-white text-tinta hover:bg-fundo'"
                        @click="filtroEstado = opcao[0]"
                    >
                        {{ opcao[1] }}
                    </button>
                </div>
                <label class="flex h-12 min-w-0 flex-[1_1_260px] items-center gap-2.5 rounded-[10px] border border-linha-forte bg-white px-3.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                    <svg class="shrink-0 text-suave-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
                    <span class="sr-only">Pesquisar</span>
                    <input v-model="pesquisa" type="search" class="w-full border-0 p-0 text-base focus:ring-0" placeholder="Nome ou telefone...">
                </label>
            </div>

            <section aria-label="Lista de reservas" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="hidden gap-3 border-b border-linha-fraca px-5 py-3 text-[13px] font-bold text-suave lg:grid lg:grid-cols-[90px_100px_minmax(0,1fr)_70px_80px_110px_auto]">
                    <span>Hora</span><span>Dia</span><span>Nome</span><span>Pessoas</span><span>Chamada</span><span>Estado</span><span class="text-right">Ações</span>
                </div>
                <template v-for="reserva in reservasFiltradas" :key="reserva.id">
                    <div class="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-3 gap-y-2 border-b border-linha-fraca px-5 py-3 lg:grid-cols-[90px_100px_minmax(0,1fr)_70px_80px_110px_auto]"
                        :class="[editandoId === reserva.id ? 'bg-[#F5F9F7]' : '', reserva.estado === 'cancelada' ? 'bg-fundo/60' : '']">
                        <span class="text-xl font-extrabold">{{ reserva.hora?.slice(0, 5) }}</span>
                        <span class="text-[15px] text-suave lg:order-none">{{ formatarDia(reserva.data) }}</span>
                        <span class="col-span-2 text-base font-bold lg:col-span-1">{{ reserva.nome }}</span>
                        <span class="inline-flex items-center gap-1.5 text-base font-bold">
                            <svg class="text-suave-2" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 7a4 4 0 1 0 8 0a4 4 0 1 0-8 0M5 21a7 7 0 0 1 14 0" /></svg>
                            {{ reserva.pessoas }}<span class="sr-only"> pessoas</span>
                        </span>
                        <span class="flex flex-wrap items-center gap-2 lg:contents">
                            <span>
                                <span class="sr-only">Chamada: </span>
                                <span class="inline-flex h-7 min-w-14 items-center justify-center rounded-full px-2.5 text-sm font-bold" :class="reserva.chamada_em ? 'bg-[#E8EEFA] text-[#1E4592]' : 'bg-linha-fraca text-suave'">
                                    {{ formatarHoraData(reserva.chamada_em) }}
                                </span>
                            </span>
                            <span>
                                <span class="inline-flex h-7 items-center rounded-full px-3 text-sm font-bold" :class="estadoClass(reserva.estado)">{{ estadoNome(reserva.estado) }}</span>
                            </span>
                        </span>
                        <div class="col-span-2 flex flex-wrap justify-end gap-1.5 lg:col-span-1">
                            <template v-if="editandoId !== reserva.id">
                                <button
                                    v-if="reserva.estado !== 'sentada'"
                                    type="button"
                                    class="h-11 rounded-[10px] border border-[#C9D6F2] bg-[#E8EEFA] px-3 text-sm font-bold text-[#1E4592] hover:brightness-95"
                                    @click="marcarChamada(reserva)"
                                >Chamada</button>
                                <button
                                    v-if="reserva.estado !== 'sentada'"
                                    type="button"
                                    class="h-11 rounded-[10px] bg-verde px-3.5 text-sm font-bold text-white hover:bg-verde-escuro"
                                    @click="marcarSentada(reserva)"
                                >Sentada</button>
                                <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-3 text-sm font-bold text-tinta hover:bg-fundo" @click="editar(reserva)">Editar</button>
                                <button type="button" :aria-label="`Eliminar reserva de ${reserva.nome}`" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-[#F0C9C2] bg-white text-perigo hover:bg-perigo-claro" @click="eliminar(reserva)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3" /></svg>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div v-if="editandoId === reserva.id" class="flex flex-col gap-2.5 border-b border-t-2 border-b-linha-fraca border-t-verde bg-[#F5F9F7] px-5 py-3.5">
                        <span class="text-[13px] font-extrabold uppercase tracking-[.06em] text-verde-escuro">A editar: {{ reserva.nome }}</span>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_150px_120px_90px_minmax(0,1fr)_auto_auto]">
                            <input v-model="editForm.nome" aria-label="Nome" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-[15px] text-tinta focus:border-verde focus:ring-verde" placeholder="Nome">
                            <input v-model="editForm.data" aria-label="Dia" type="date" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-[15px] text-tinta focus:border-verde focus:ring-verde">
                            <input v-model="editForm.hora" aria-label="Hora" type="time" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-[15px] text-tinta focus:border-verde focus:ring-verde">
                            <input v-model="editForm.pessoas" aria-label="Pessoas" type="number" min="1" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-[15px] text-tinta focus:border-verde focus:ring-verde">
                            <input v-model="editForm.observacoes" aria-label="Observações" class="h-11 w-full rounded-[10px] border-linha-forte px-3 text-[15px] text-tinta focus:border-verde focus:ring-verde" placeholder="Observações">
                            <button type="button" class="h-11 rounded-[10px] bg-verde px-4 text-sm font-bold text-white hover:bg-verde-escuro disabled:opacity-40" :disabled="editForm.processing" @click="guardarEdicao(reserva)">Gravar</button>
                            <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-4 text-sm font-bold text-tinta hover:bg-fundo" @click="cancelarEdicao">Cancelar</button>
                        </div>
                        <div v-if="Object.keys(editForm.errors).length" class="rounded-[10px] bg-perigo-claro p-2.5 text-sm font-bold text-perigo-texto">
                            <div v-for="erro in editForm.errors" :key="erro">{{ erro }}</div>
                        </div>
                    </div>
                </template>
                <div v-if="!reservas.length" class="p-8 text-center text-[15px] text-suave">Ainda não há reservas.</div>
                <div v-else-if="!reservasFiltradas.length" class="p-8 text-center text-[15px] text-suave">Nenhuma reserva corresponde ao filtro.</div>
            </section>
        </div>
    </AppLayout>
</template>
