<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ pedidos: Object, filters: Object, mesas: Array, resumo: Object });

const estados = [
    ['abertos', 'Abertos'],
    ['pronto', 'Prontos'],
    ['entregue', 'Fechados'],
    ['todos', 'Todos'],
];
const pedidosLista = computed(() => props.pedidos?.data ?? []);
const totalPedido = (pedido) => Number(pedido.total ?? pedido.total_calculado ?? (pedido.items ?? []).reduce((soma, item) => soma + Number(item.preco_unitario) * Number(item.quantidade), 0));
const formatarPreco = (valor) => `${Number(valor ?? 0).toFixed(2)}€`;
const criadoPor = (pedido) => pedido.operador_nome ?? pedido.user?.name ?? pedido.pos?.nome ?? 'Sem utilizador';
const estadoClass = (estado) => ({
    pendente: 'bg-orange-100 text-orange-800',
    preparacao: 'bg-sky-100 text-sky-800',
    pronto: 'bg-emerald-100 text-emerald-800',
    entregue: 'bg-slate-100 text-slate-700',
    cancelado: 'bg-red-100 text-red-800',
}[estado] ?? 'bg-slate-100 text-slate-700');
</script>

<template>
    <AppLayout>
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Tesouraria de pedidos</h1>
                <p class="mt-1 text-sm text-slate-500">Contas abertas, fecho e talões para cliente.</p>
            </div>
            <Link :href="route('pedidos.create')" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-bold text-white">Novo pedido</Link>
        </div>

        <div class="mb-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-md bg-white p-4 shadow-sm"><div class="text-sm font-semibold text-slate-500">Contas abertas</div><div class="mt-1 text-3xl font-black">{{ resumo?.abertos ?? 0 }}</div></div>
            <div class="rounded-md bg-white p-4 shadow-sm"><div class="text-sm font-semibold text-slate-500">Prontos a receber</div><div class="mt-1 text-3xl font-black text-emerald-700">{{ resumo?.pronto ?? 0 }}</div></div>
            <div class="rounded-md bg-white p-4 shadow-sm"><div class="text-sm font-semibold text-slate-500">Fechados hoje</div><div class="mt-1 text-3xl font-black">{{ resumo?.fechados_hoje ?? 0 }}</div></div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <Link
                v-for="[valor, label] in estados"
                :key="valor"
                :href="route('pedidos.index', { estado: valor })"
                class="rounded-md border px-3 py-2 text-sm font-bold"
                :class="(filters?.estado ?? 'abertos') === valor ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700'"
            >
                {{ label }}
            </Link>
        </div>

        <div v-if="pedidosLista.length" class="grid gap-3 lg:grid-cols-2 2xl:grid-cols-3">
            <article v-for="pedido in pedidosLista" :key="pedido.id" class="rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="text-2xl font-black">{{ pedido.mesa?.designacao ?? 'Para levar' }}</div>
                        <div class="mt-1 text-sm text-slate-500">Pedido #{{ pedido.id }} · {{ criadoPor(pedido) }}</div>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-black uppercase" :class="estadoClass(pedido.estado)">{{ pedido.estado }}</span>
                </div>
                <div class="mb-4 flex items-end justify-between rounded-md bg-slate-50 p-3">
                    <div class="text-sm font-semibold text-slate-500">{{ pedido.items?.length ?? 0 }} artigos</div>
                    <div class="text-3xl font-black">{{ formatarPreco(totalPedido(pedido)) }}</div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <Link :href="route('pedidos.show', pedido.id)" class="rounded-md bg-slate-900 px-3 py-3 text-center text-sm font-black text-white">
                        Receber / Ver
                    </Link>
                    <Link :href="route('pedidos.talao', pedido.id)" class="rounded-md border border-slate-300 px-3 py-3 text-center text-sm font-black">
                        Talão
                    </Link>
                </div>
            </article>
        </div>
        <div v-else class="rounded-lg bg-white p-10 text-center text-slate-500 shadow-sm">
            Não há pedidos nesta vista.
        </div>
    </AppLayout>
</template>
