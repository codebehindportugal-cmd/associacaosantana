<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    filters: Object,
    logs: Array,
    modelos: Array,
});

const filtros = reactive({
    data_inicio: props.filters?.data_inicio,
    data_fim: props.filters?.data_fim,
    acao: props.filters?.acao ?? 'todas',
    modelo: props.filters?.modelo ?? 'todos',
    funcionario: props.filters?.funcionario ?? '',
});

const carregar = () => {
    router.get(route('manutencao.logs.index'), filtros, {
        preserveState: true,
        preserveScroll: true,
    });
};

const valor = (item) => {
    if (item === null || item === undefined || item === '') return '-';
    if (typeof item === 'boolean') return item ? 'Sim' : 'Não';
    return String(item);
};

const campos = (log) => Array.from(new Set([
    ...Object.keys(log.old_values ?? {}),
    ...Object.keys(log.new_values ?? {}),
]));

const acaoClasses = {
    criado: 'bg-emerald-100 text-emerald-800',
    alterado: 'bg-amber-100 text-amber-900',
    apagado: 'bg-red-100 text-red-800',
};
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-950">Logs de alterações</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Histórico de dados criados, alterados ou apagados por funcionários no backoffice e no POS.
                </p>
            </div>
            <Link :href="route('manutencao.limpeza.index')" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-black text-white">
                Limpeza de dados
            </Link>
        </div>

        <form class="mb-6 grid gap-4 bg-white p-5 shadow-sm ring-1 ring-slate-200 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto]" @submit.prevent="carregar">
            <label>
                <span class="mb-1 block text-sm font-bold text-slate-700">Data início</span>
                <input v-model="filtros.data_inicio" type="date" class="w-full rounded-md border-slate-300">
            </label>

            <label>
                <span class="mb-1 block text-sm font-bold text-slate-700">Data fim</span>
                <input v-model="filtros.data_fim" type="date" class="w-full rounded-md border-slate-300">
            </label>

            <label>
                <span class="mb-1 block text-sm font-bold text-slate-700">Ação</span>
                <select v-model="filtros.acao" class="w-full rounded-md border-slate-300">
                    <option value="todas">Todas</option>
                    <option value="criado">Criado</option>
                    <option value="alterado">Alterado</option>
                    <option value="apagado">Apagado</option>
                </select>
            </label>

            <label>
                <span class="mb-1 block text-sm font-bold text-slate-700">Dados</span>
                <select v-model="filtros.modelo" class="w-full rounded-md border-slate-300">
                    <option value="todos">Todos</option>
                    <option v-for="modelo in modelos" :key="modelo.value" :value="modelo.value">{{ modelo.label }}</option>
                </select>
            </label>

            <label>
                <span class="mb-1 block text-sm font-bold text-slate-700">Funcionário</span>
                <input v-model="filtros.funcionario" type="search" class="w-full rounded-md border-slate-300" placeholder="Nome">
            </label>

            <button type="submit" class="self-end rounded-md bg-slate-900 px-4 py-2.5 font-black text-white">
                Filtrar
            </button>
        </form>

        <section class="space-y-4">
            <article v-for="log in logs" :key="log.id" class="bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-black uppercase" :class="acaoClasses[log.action] ?? 'bg-slate-100 text-slate-700'">
                                {{ log.action }}
                            </span>
                            <strong>{{ log.model }} #{{ log.auditable_id }}</strong>
                            <span v-if="log.auditable_label" class="text-slate-600">· {{ log.auditable_label }}</span>
                        </div>
                        <div class="mt-2 text-sm text-slate-500">
                            {{ log.created_at }} · {{ log.actor_name }} · {{ log.actor_type }} · {{ log.ip }}
                        </div>
                    </div>
                    <a v-if="log.url" :href="log.url" class="text-sm font-bold text-slate-700 hover:text-slate-950" target="_blank" rel="noreferrer">
                        Abrir origem
                    </a>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs uppercase text-slate-500">
                                <th class="py-2 pr-4">Campo</th>
                                <th class="py-2 pr-4">Antes</th>
                                <th class="py-2">Depois</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="campo in campos(log)" :key="campo" class="border-b last:border-0">
                                <td class="py-2 pr-4 font-bold text-slate-700">{{ campo }}</td>
                                <td class="max-w-md py-2 pr-4 text-slate-600">{{ valor(log.old_values?.[campo]) }}</td>
                                <td class="max-w-md py-2 text-slate-900">{{ valor(log.new_values?.[campo]) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <div v-if="!logs?.length" class="bg-white p-8 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">
                Ainda não existem alterações registadas para este filtro.
            </div>
        </section>
    </AppLayout>
</template>
