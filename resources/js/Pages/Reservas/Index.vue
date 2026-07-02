<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';

defineProps({ reservas: Object });

const hoje = new Date().toISOString().slice(0, 10);
const form = useForm({
    nome: '',
    data_reserva: hoje,
    hora: '20:00',
    pessoas: 2,
    estado: 'confirmada',
});

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
        <h1 class="mb-6 text-2xl font-bold">Reservas</h1>

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
                    <tr v-for="reserva in reservas.data" :key="reserva.id" class="border-t">
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
                                :class="reserva.estado === 'sentada' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'"
                            >
                                {{ reserva.estado }}
                            </span>
                        </td>
                        <td class="pr-3 text-right">
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded-md border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-700 hover:bg-amber-50 disabled:opacity-40"
                                    :disabled="reserva.estado === 'sentada'"
                                    @click="marcarChamada(reserva)"
                                >
                                    Chamada
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 disabled:opacity-40"
                                    :disabled="reserva.estado === 'sentada'"
                                    @click="marcarSentada(reserva)"
                                >
                                    Sentada
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!reservas.data.length">
                        <td colspan="7" class="p-8 text-center text-slate-500">Ainda nao ha reservas.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
