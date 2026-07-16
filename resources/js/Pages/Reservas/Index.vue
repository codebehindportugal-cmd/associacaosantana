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
        return 'Nao';
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
</script>

<template>
    <AppLayout>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Reservas</h1>
            <div class="flex items-center gap-2">
                <label class="text-sm font-semibold text-slate-600">Data:</label>
                <input
                    v-model="dataEscolhida"
                    type="date"
                    class="rounded-md border-slate-300 text-sm"
                >
                <span class="text-sm text-slate-500">{{ props.reservas.length }} reservas</span>
            </div>
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-center">
                <div class="text-3xl font-black text-emerald-700">{{ totalPessoasSentadas }}</div>
                <div class="mt-1 text-sm font-semibold text-emerald-600">Pessoas sentadas</div>
            </div>
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 text-center">
                <div class="text-3xl font-black text-slate-700">{{ totalPessoasPorSentar }}</div>
                <div class="mt-1 text-sm font-semibold text-slate-500">Por sentar</div>
            </div>
            <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-center sm:col-span-1 col-span-2">
                <div class="text-3xl font-black text-blue-700">{{ totalPessoasSentadas + totalPessoasPorSentar }}</div>
                <div class="mt-1 text-sm font-semibold text-blue-600">Total pessoas hoje</div>
            </div>
        </div>

        <form class="mb-6 rounded-lg bg-white p-5 shadow-sm" @submit.prevent="criarReserva">
            <div class="grid gap-3 md:grid-cols-[1fr_130px_130px_120px_auto]">
                <input v-model="form.nome" class="rounded-md border-slate-300" placeholder="Nome">
                <input v-model="form.pessoas" type="number" min="1" class="rounded-md border-slate-300" placeholder="Pessoas">
                <input v-model="form.data_reserva" type="date" class="rounded-md border-slate-300">
                <input v-model="form.hora" type="time" class="rounded-md border-slate-300">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 font-semibold text-white disabled:opacity-60" :disabled="form.processing">
                    {{ form.processing ? 'A criar...' : 'Criar reserva' }}
                </button>
            </div>

            <div v-if="Object.keys(form.errors).length" class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700">
                <div v-for="erro in form.errors" :key="erro">{{ erro }}</div>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="w-full min-w-[860px] text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-3">Hora</th>
                        <th>Dia</th>
                        <th>Nome</th>
                        <th>Pessoas</th>
                        <th>Chamada</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="reserva in reservas" :key="reserva.id">
                        <tr class="border-t" :class="editandoId === reserva.id ? 'bg-blue-50' : ''">
                            <td class="p-3 text-lg font-bold">{{ reserva.hora?.slice(0, 5) }}</td>
                            <td class="font-semibold">{{ formatarDia(reserva.data) }}</td>
                            <td>{{ reserva.nome }}</td>
                            <td>{{ reserva.pessoas }}</td>
                            <td>
                                <span
                                    class="inline-flex min-w-16 justify-center rounded-full px-3 py-1 text-xs font-bold"
                                    :class="reserva.chamada_em ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'"
                                >
                                    {{ formatarHoraData(reserva.chamada_em) }}
                                </span>
                            </td>
                            <td>
                                <span
                                    class="inline-flex min-w-20 justify-center rounded-full px-3 py-1 text-xs font-bold capitalize"
                                    :class="reserva.estado === 'sentada' ? 'bg-emerald-100 text-emerald-800' : reserva.estado === 'cancelada' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-800'"
                                >
                                    {{ reserva.estado }}
                                </span>
                            </td>
                            <td class="pr-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        v-if="editandoId !== reserva.id"
                                        type="button"
                                        class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                        @click="editar(reserva)"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        v-if="editandoId !== reserva.id"
                                        type="button"
                                        class="rounded-md border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50 disabled:opacity-40"
                                        :disabled="reserva.estado === 'sentada'"
                                        @click="marcarChamada(reserva)"
                                    >
                                        Chamada
                                    </button>
                                    <button
                                        v-if="editandoId !== reserva.id"
                                        type="button"
                                        class="rounded-md border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-40"
                                        :disabled="reserva.estado === 'sentada'"
                                        @click="marcarSentada(reserva)"
                                    >
                                        Sentada
                                    </button>
                                    <button
                                        v-if="editandoId !== reserva.id"
                                        type="button"
                                        class="rounded-md border border-red-300 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                                        @click="eliminar(reserva)"
                                    >
                                        Eliminar
                                    </button>
                                    <template v-if="editandoId === reserva.id">
                                        <button
                                            type="button"
                                            class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40"
                                            :disabled="editForm.processing"
                                            @click="guardarEdicao(reserva)"
                                        >
                                            Gravar
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600"
                                            @click="cancelarEdicao"
                                        >
                                            Cancelar
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="editandoId === reserva.id" class="border-t bg-blue-50">
                            <td colspan="7" class="px-3 pb-3">
                                <div class="grid gap-2 md:grid-cols-[1fr_130px_100px_80px_1fr]">
                                    <input v-model="editForm.nome" class="rounded-md border-slate-300" placeholder="Nome">
                                    <input v-model="editForm.data" type="date" class="rounded-md border-slate-300">
                                    <input v-model="editForm.hora" type="time" class="rounded-md border-slate-300">
                                    <input v-model="editForm.pessoas" type="number" min="1" class="rounded-md border-slate-300">
                                    <input v-model="editForm.observacoes" class="rounded-md border-slate-300" placeholder="Observações">
                                </div>
                                <div v-if="Object.keys(editForm.errors).length" class="mt-2 rounded-md bg-red-50 p-2 text-sm text-red-700">
                                    <div v-for="erro in editForm.errors" :key="erro">{{ erro }}</div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!reservas.length">
                        <td colspan="7" class="p-8 text-center text-slate-500">Ainda não há reservas.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
