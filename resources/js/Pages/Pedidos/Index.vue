<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import Paginacao from '@/Components/Paginacao.vue';
import { computed, ref } from 'vue';

const props = defineProps({ pedidos: Object, filters: Object, mesas: Array, resumo: Object });

const estados = [
    ['abertos', 'Abertos'],
    ['pronto', 'Prontos'],
    ['entregue', 'Fechados'],
    ['todos', 'Todos'],
];
const pesquisa = ref('');
const pedidosLista = computed(() => {
    const lista = props.pedidos?.data ?? [];
    const termo = pesquisa.value.trim().toLowerCase();
    if (!termo) return lista;
    return lista.filter((p) => `#${p.id} ${p.mesa?.designacao ?? ''} ${p.mesa?.nome ?? ''} ${p.mesa?.numero ?? ''} ${p.operador_nome ?? ''} ${p.user?.name ?? ''} ${p.numero_senha ?? ''} ${p.nome_reserva ?? ''}`.toLowerCase().includes(termo));
});
const totalPedido = (pedido) => Number(pedido.total ?? pedido.total_calculado ?? (pedido.items ?? []).reduce((soma, item) => soma + Number(item.preco_unitario) * Number(item.quantidade), 0));
const formatarPreco = (valor) => `${Number(valor ?? 0).toFixed(2).replace('.', ',')}€`;
const criadoPor = (pedido) => pedido.operador_nome ?? pedido.user?.name ?? pedido.pos?.nome ?? 'Sem utilizador';
const estadoClass = (estado) => ({
    pendente: 'bg-laranja-claro text-laranja-texto',
    preparacao: 'bg-[#E8EEFA] text-[#1E4592]',
    pronto: 'bg-verde-claro2 text-verde-escuro',
    entregue: 'bg-linha-fraca text-suave',
    cancelado: 'bg-perigo-claro text-perigo-texto',
}[estado] ?? 'bg-linha-fraca text-suave');
const estadoNome = (estado) => ({
    pendente: 'Pendente',
    preparacao: 'Em preparação',
    pronto: 'Pronto',
    entregue: 'Fechado',
    cancelado: 'Cancelado',
}[estado] ?? estado);
</script>

<template>
    <AppLayout>
        <div class="flex flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-[30px] font-extrabold leading-tight">Tesouraria de pedidos</h1>
                    <p class="text-[15px] text-suave">Contas abertas, fecho e talões para cliente.</p>
                </div>
                <Link :href="route('pedidos.create')" class="inline-flex h-[52px] items-center gap-2 rounded-[10px] bg-verde px-5 text-base font-bold text-white hover:bg-verde-escuro">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                    Novo pedido
                </Link>
            </div>

            <div class="grid gap-3.5 sm:grid-cols-3">
                <div class="flex flex-col gap-1 rounded-[14px] border border-[#F5D9BF] bg-laranja-claro p-5">
                    <span class="text-sm font-bold text-laranja-texto">Contas abertas</span>
                    <span class="text-[34px] font-extrabold leading-tight text-laranja-texto">{{ resumo?.abertos ?? 0 }}</span>
                </div>
                <div class="flex flex-col gap-1 rounded-[14px] border border-[#C6E2D4] bg-verde-claro p-5">
                    <span class="text-sm font-bold text-verde-escuro">Prontos a receber</span>
                    <span class="text-[34px] font-extrabold leading-tight text-verde-escuro">{{ resumo?.pronto ?? 0 }}</span>
                </div>
                <div class="flex flex-col gap-1 rounded-[14px] border border-linha bg-white p-5">
                    <span class="text-sm font-bold text-suave">Fechados hoje</span>
                    <span class="text-[34px] font-extrabold leading-tight">{{ resumo?.fechados_hoje ?? 0 }}</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <nav aria-label="Filtrar por estado" class="flex flex-wrap gap-2">
                    <Link
                        v-for="[valor, label] in estados"
                        :key="valor"
                        :href="route('pedidos.index', { estado: valor })"
                        class="inline-flex h-11 items-center rounded-full border px-[18px] text-[15px] font-bold transition"
                        :class="(filters?.estado ?? 'abertos') === valor ? 'border-escuro bg-escuro text-white' : 'border-linha-forte bg-white text-tinta hover:bg-fundo'"
                        :aria-current="(filters?.estado ?? 'abertos') === valor ? 'page' : undefined"
                    >
                        {{ label }}
                    </Link>
                </nav>
                <label class="flex h-12 min-w-0 flex-[1_1_260px] items-center gap-2.5 rounded-[10px] border border-linha-forte bg-white px-3.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                    <svg class="shrink-0 text-suave-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4a7 7 0 1 0 0 14a7 7 0 1 0 0-14M20 20l-4-4" /></svg>
                    <span class="sr-only">Pesquisar</span>
                    <input v-model="pesquisa" type="search" class="w-full border-0 p-0 text-base focus:ring-0" placeholder="Mesa, nº pedido, operador...">
                </label>
            </div>

            <section v-if="pedidosLista.length" aria-label="Pedidos" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <article v-for="pedido in pedidosLista" :key="pedido.id"
                    class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-3 border-b border-linha-fraca px-5 py-3.5 last:border-b-0 md:grid-cols-[minmax(0,1fr)_130px_100px_110px_auto]">
                    <div class="flex min-w-0 flex-col gap-0.5">
                        <span class="text-lg font-extrabold">{{ pedido.mesa?.designacao ?? 'Para levar' }}</span>
                        <span class="text-sm text-suave-2">Pedido #{{ pedido.id }} · {{ criadoPor(pedido) }}</span>
                        <span v-if="pedido.nome_reserva" class="text-sm font-bold text-azul">Reserva: {{ pedido.nome_reserva }}</span>
                        <span class="mt-1 inline-flex items-center gap-2 text-sm text-suave md:hidden">
                            <span class="inline-flex h-7 items-center rounded-full px-2.5 text-sm font-bold" :class="estadoClass(pedido.estado)">{{ estadoNome(pedido.estado) }}</span>
                            {{ pedido.items?.length ?? 0 }} artigos
                        </span>
                    </div>
                    <span class="hidden md:block"><span class="inline-flex h-7 items-center whitespace-nowrap rounded-full px-3 text-sm font-bold" :class="estadoClass(pedido.estado)">{{ estadoNome(pedido.estado) }}</span></span>
                    <span class="hidden text-[15px] text-suave md:block">{{ pedido.items?.length ?? 0 }} artigos</span>
                    <span class="text-right text-xl font-extrabold">{{ formatarPreco(totalPedido(pedido)) }}</span>
                    <div class="col-span-2 flex gap-2 md:col-span-1">
                        <Link :href="route('pedidos.show', pedido.id)" class="inline-flex h-11 flex-1 items-center justify-center whitespace-nowrap rounded-[10px] bg-laranja px-4 text-[15px] font-bold text-white hover:brightness-95 md:flex-none">
                            Receber / Ver
                        </Link>
                        <Link :href="route('pedidos.talao', pedido.id)" class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 rounded-[10px] border border-linha-forte bg-white px-3.5 text-[15px] font-bold text-tinta hover:bg-fundo md:flex-none">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M3 9h18v8H3zM6 14h12v7H6z" /></svg>
                            Talão
                        </Link>
                    </div>
                </article>
                <Paginacao :dados="pedidos" etiqueta="pedidos" />
            </section>
            <div v-else class="rounded-[14px] border border-linha bg-white p-10 text-center text-[15px] text-suave">
                Não há pedidos nesta vista.
            </div>
        </div>
    </AppLayout>
</template>
