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
    criado: 'bg-verde-claro text-verde-escuro',
    alterado: 'bg-laranja-claro text-laranja-texto',
    apagado: 'bg-perigo-claro text-perigo-texto',
};
const acaoBorda = {
    criado: 'border-l-verde',
    alterado: 'border-l-laranja',
    apagado: 'border-l-perigo',
};
const acoes = [
    ['todas', 'Todas', 'bg-tinta'],
    ['criado', 'Criado', 'bg-verde-ok'],
    ['alterado', 'Alterado', 'bg-laranja'],
    ['apagado', 'Apagado', 'bg-perigo'],
];
// Escolher a ação filtra logo (mesma chamada que o botão Filtrar)
const escolherAcao = (valor) => {
    filtros.acao = valor;
    carregar();
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta">
            <h1 class="text-[30px] font-extrabold leading-tight">Manutenção</h1>
            <nav aria-label="Manutenção" class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-[12px] bg-linha-fraca p-1">
                <Link :href="route('manutencao.limpeza.index')" class="tab text-suave hover:text-tinta">Limpeza de dados</Link>
                <Link :href="route('manutencao.logs.index')" class="tab bg-white text-tinta shadow-sm" aria-current="page">Logs de alterações</Link>
            </nav>
            <p class="max-w-3xl text-[15px] text-suave">
                Histórico de dados criados, alterados ou apagados por funcionários no backoffice e no POS.
            </p>

            <form class="cartao flex flex-col gap-4 p-4 sm:p-5" @submit.prevent="carregar">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[repeat(4,minmax(0,1fr))_auto]">
                    <label class="rotulo">Data início<input v-model="filtros.data_inicio" type="date" class="campo"></label>
                    <label class="rotulo">Data fim<input v-model="filtros.data_fim" type="date" class="campo"></label>
                    <label class="rotulo">Dados
                        <select v-model="filtros.modelo" class="campo">
                            <option value="todos">Todos</option>
                            <option v-for="modelo in modelos" :key="modelo.value" :value="modelo.value">{{ modelo.label }}</option>
                        </select>
                    </label>
                    <label class="rotulo">Funcionário<input v-model="filtros.funcionario" type="search" class="campo" placeholder="Nome"></label>
                    <button type="submit" class="h-12 self-end rounded-[10px] bg-verde px-8 text-[15px] font-bold text-white transition hover:bg-verde-escuro sm:col-span-2 lg:col-span-1">Filtrar</button>
                </div>
                <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Ação">
                    <span class="mr-1 text-sm font-semibold text-suave">Ação</span>
                    <button
                        v-for="[valor, rotulo, ponto] in acoes"
                        :key="valor"
                        type="button"
                        role="radio"
                        class="inline-flex h-11 items-center gap-2 rounded-full border px-4 text-sm font-bold transition"
                        :class="filtros.acao === valor ? 'border-tinta bg-tinta text-white' : 'border-linha-forte bg-white hover:bg-fundo'"
                        :aria-checked="filtros.acao === valor"
                        @click="escolherAcao(valor)"
                    >
                        <span class="h-2 w-2 rounded-full" :class="filtros.acao === valor && valor === 'todas' ? 'bg-white' : ponto"></span>
                        {{ rotulo }}
                    </button>
                </div>
            </form>

            <section class="flex flex-col gap-3">
                <article v-for="log in logs" :key="log.id" class="cartao border-l-4 p-4 sm:p-5" :class="acaoBorda[log.action] ?? 'border-l-linha-forte'">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-extrabold uppercase tracking-wide" :class="acaoClasses[log.action] ?? 'bg-fundo text-suave'">{{ log.action }}</span>
                                <strong class="text-[17px] font-extrabold">{{ log.model }} #{{ log.auditable_id }}</strong>
                                <span v-if="log.auditable_label" class="text-suave">· {{ log.auditable_label }}</span>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-suave">
                                <span class="inline-flex items-center gap-1.5">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                                    <span class="tabular-nums">{{ log.created_at }}</span>
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21c1-4 4-6 8-6s7 2 8 6" /></svg>
                                    <strong class="font-bold text-tinta">{{ log.actor_name }}</strong> · {{ log.actor_type }}
                                </span>
                                <span v-if="log.ip" class="tabular-nums">IP {{ log.ip }}</span>
                            </div>
                        </div>
                        <a v-if="log.url" :href="log.url" class="inline-flex h-11 shrink-0 items-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-sm font-bold text-tinta hover:bg-fundo" target="_blank" rel="noreferrer">
                            Abrir origem
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" /><path d="M18 14v6H4V6h6" /></svg>
                        </a>
                    </div>

                    <div v-if="campos(log).length" class="mt-3 overflow-hidden rounded-[10px] border border-linha-fraca">
                        <div class="hidden grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_minmax(0,1.5fr)] bg-fundo px-3.5 py-2 text-xs font-semibold text-suave sm:grid">
                            <span>Campo</span><span>Antes</span><span>Depois</span>
                        </div>
                        <div v-for="campo in campos(log)" :key="campo" class="grid gap-x-3 border-t border-linha-fraca px-3.5 py-2 text-sm first:border-t-0 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_minmax(0,1.5fr)] sm:first:border-t">
                            <span class="font-mono text-[13px] font-semibold text-tinta">{{ campo }}</span>
                            <span class="break-words text-suave" :class="log.action === 'alterado' && valor(log.old_values?.[campo]) !== '-' ? 'line-through' : ''"><span class="text-xs sm:hidden">Antes: </span>{{ valor(log.old_values?.[campo]) }}</span>
                            <span class="break-words font-bold text-tinta"><span class="text-xs font-normal text-suave sm:hidden">Depois: </span>{{ valor(log.new_values?.[campo]) }}</span>
                        </div>
                    </div>
                </article>

                <div v-if="!logs?.length" class="cartao p-8 text-center text-sm text-suave-2">
                    Ainda não existem alterações registadas para este filtro.
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.tab { @apply inline-flex h-11 shrink-0 items-center whitespace-nowrap rounded-[10px] px-4 text-sm font-bold transition; }
.cartao { @apply rounded-[14px] border border-linha bg-white; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta focus:border-verde focus:ring-verde; }
</style>
