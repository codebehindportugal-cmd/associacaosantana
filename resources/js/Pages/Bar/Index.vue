<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ pedidos: Array, caixas: Array, pontos: { type: Array, default: () => [] } });
// Nomes dos postos POS ativos (Impressoras > Postos POS)
const pontosPadrao = computed(() => props.pontos ?? []);
const pontoBar = ref('');
let intervalo = null;
const contas = computed(() => (props.pedidos ?? []).filter((p) => p.tipo === 'bar_conta' && !['entregue', 'cancelado'].includes(p.estado)));
const prepagos = computed(() => (props.pedidos ?? []).filter((p) => p.tipo === 'bar_prepago'));
const totaisPorPonto = computed(() => Object.entries((props.pedidos ?? []).reduce((acc, pedido) => {
    const ponto = pedido.ponto_bar || 'Sem ponto definido';
    acc[ponto] = (acc[ponto] || 0) + Number(pedido.total ?? pedido.total_calculado ?? 0);
    return acc;
}, {})).map(([ponto, total]) => ({ ponto, total })));
const total = (pedido) => Number(pedido.total ?? pedido.total_calculado ?? 0).toFixed(2).replace('.', ',') + '€';
const euros = (valor) => Number(valor ?? 0).toFixed(2).replace('.', ',') + '€';
const hora = (data) => new Date(data).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
const caixaAberta = computed(() => (props.caixas ?? []).some((caixa) => caixa.ponto === pontoBar.value && caixa.estado === 'aberta'));
const caixasPorPonto = computed(() => Object.fromEntries((props.caixas ?? []).map((caixa) => [caixa.ponto, caixa])));
const pontoQuery = computed(() => pontoBar.value ? { ponto: pontoBar.value } : {});

watch(pontoBar, (valor) => localStorage.setItem('santana_ponto_bar', valor || ''));
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    pontoBar.value = params.get('ponto') || localStorage.getItem('santana_ponto_bar') || '';
    intervalo = setInterval(() => router.reload({ only: ['pedidos', 'caixas'], preserveScroll: true }), 20000);
});
onBeforeUnmount(() => clearInterval(intervalo));
</script>

<template>
    <main class="min-h-screen bg-fundo font-sans text-tinta tabular-nums">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-[30px] font-extrabold leading-tight">Caixas - Senhas</h1>
                    <p class="text-[15px] text-suave">Bebidas por senha impressa e contas dos balcões.</p>
                </div>
                <Link :href="route('caixa.index')" class="inline-flex min-h-11 items-center gap-1.5 text-[15px] font-bold text-verde hover:text-verde-escuro">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                    Voltar às Caixas
                </Link>
            </div>

            <section aria-labelledby="ponto-titulo" class="flex flex-wrap items-end gap-4 rounded-[14px] border border-linha bg-white p-5">
                <label class="flex min-w-0 flex-[1_1_300px] flex-col gap-1.5">
                    <span id="ponto-titulo" class="text-sm font-bold text-suave">Nome deste ponto de venda</span>
                    <input v-model="pontoBar" list="pontos-bar" class="h-[52px] rounded-[10px] border-linha-forte px-3.5 text-lg font-bold text-tinta focus:border-verde focus:ring-verde" placeholder="Nome do ponto (ex: Café, Bar 1)">
                </label>
                <div role="status" class="flex min-h-[52px] min-w-0 flex-[1_1_300px] items-center gap-2.5 rounded-[10px] px-4 py-2 text-base font-bold"
                    :class="pontoBar && caixaAberta ? 'bg-verde-claro text-verde-escuro' : 'bg-laranja-claro text-laranja-texto'">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="pontoBar && caixaAberta ? 'bg-verde-ok' : 'bg-laranja'" />
                    <span v-if="pontoBar && caixaAberta">Caixa aberta · fundo {{ euros(caixasPorPonto[pontoBar]?.fundo_maneio) }}</span>
                    <span v-else>Abre a caixa deste ponto antes de vender.</span>
                </div>
                <datalist id="pontos-bar"><option v-for="ponto in pontosPadrao" :key="ponto" :value="ponto" /></datalist>
            </section>

            <div class="grid gap-3 sm:grid-cols-2">
                <Link :href="route('bar.prepago', pontoQuery)" class="flex min-h-[88px] items-center gap-4 rounded-[14px] bg-laranja px-5 text-white transition hover:brightness-95">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/20">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h18v12H3zM9 13a3 3 0 1 0 6 0a3 3 0 1 0-6 0" /></svg>
                    </span>
                    <span class="flex flex-col gap-0.5">
                        <span class="text-xl font-extrabold">Pré-Pago</span>
                        <span class="text-sm text-[#FFE7D3]">Paga antes, imprime senha</span>
                    </span>
                </Link>
                <Link :href="route('bar.nova-conta', pontoQuery)" class="flex min-h-[88px] items-center gap-4 rounded-[14px] bg-verde px-5 text-white transition hover:bg-verde-escuro">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/20">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h10v18H7zM10 8h4M10 12h4M10 16h2" /></svg>
                    </span>
                    <span class="flex flex-col gap-0.5">
                        <span class="text-xl font-extrabold">Nova Conta</span>
                        <span class="text-sm text-verde-claro2">Paga no final</span>
                    </span>
                </Link>
            </div>

            <section aria-labelledby="dpp" class="flex flex-col gap-3">
                <h2 id="dpp" class="text-lg font-extrabold">Dinheiro por ponto hoje</h2>
                <div v-if="!totaisPorPonto.length" class="rounded-[14px] border border-linha bg-white p-5 text-center text-[15px] text-suave-2">Ainda sem vendas hoje.</div>
                <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-3">
                    <div v-for="linha in totaisPorPonto" :key="linha.ponto"
                        class="flex flex-col gap-1 rounded-[14px] bg-white px-[18px] py-4"
                        :class="linha.ponto === pontoBar ? 'border-2 border-verde' : 'border border-linha'">
                        <span class="text-sm font-bold" :class="linha.ponto === pontoBar ? 'text-verde-escuro' : 'text-suave'">{{ linha.ponto }}<template v-if="linha.ponto === pontoBar"> · este ponto</template></span>
                        <span class="text-[28px] font-extrabold leading-tight">{{ euros(linha.total) }}</span>
                    </div>
                </div>
            </section>

            <div class="grid items-start gap-5 lg:grid-cols-2">
                <section aria-labelledby="contas-abertas" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                    <div class="flex items-center justify-between border-b border-linha-fraca px-5 py-4">
                        <h2 id="contas-abertas" class="text-lg font-extrabold">Contas Abertas</h2>
                        <span class="inline-flex h-7 items-center rounded-full bg-verde-claro px-2.5 text-sm font-extrabold text-verde-escuro">{{ contas.length }}</span>
                    </div>
                    <div v-if="!contas.length" class="p-6 text-center text-[15px] text-suave-2">Sem contas abertas.</div>
                    <div v-for="pedido in contas" :key="pedido.id" class="flex flex-wrap items-center gap-x-3.5 gap-y-2 border-b border-linha-fraca px-5 py-3 last:border-b-0">
                        <div class="flex min-w-0 flex-grow basis-48 flex-col gap-0.5">
                            <span class="text-[17px] font-extrabold">Conta #{{ pedido.id }}</span>
                            <span class="text-sm text-suave-2">{{ hora(pedido.created_at) }} · {{ pedido.ponto_bar || 'Sem ponto' }} · {{ pedido.observacoes || 'Sem identificação' }}</span>
                        </div>
                        <div class="ml-auto flex items-center gap-3.5">
                            <span class="text-lg font-extrabold">{{ total(pedido) }}</span>
                            <Link :href="route('bar.show', pedido.id)" class="flex h-11 items-center whitespace-nowrap rounded-[10px] bg-verde px-4 text-[15px] font-bold text-white hover:bg-verde-escuro">Ver Conta</Link>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="prepagos-hoje" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                    <div class="flex items-center justify-between border-b border-linha-fraca px-5 py-4">
                        <h2 id="prepagos-hoje" class="text-lg font-extrabold">Pré-Pagos Hoje</h2>
                        <span class="inline-flex h-7 items-center rounded-full bg-laranja-claro px-2.5 text-sm font-extrabold text-laranja-texto">{{ prepagos.length }}</span>
                    </div>
                    <div v-if="!prepagos.length" class="p-6 text-center text-[15px] text-suave-2">Sem pré-pagos emitidos.</div>
                    <div v-for="pedido in prepagos" :key="pedido.id" class="flex flex-wrap items-center gap-x-3.5 gap-y-2 border-b border-linha-fraca px-5 py-3 last:border-b-0">
                        <div class="flex min-w-0 flex-grow basis-48 flex-col gap-0.5">
                            <span class="text-[17px] font-extrabold">Senha #{{ pedido.numero_senha }}</span>
                            <span class="text-sm text-suave-2">{{ hora(pedido.created_at) }} · {{ pedido.ponto_bar || 'Sem ponto' }} · {{ pedido.estado }}</span>
                        </div>
                        <div class="ml-auto flex items-center gap-3.5">
                            <span class="text-lg font-extrabold">{{ total(pedido) }}</span>
                            <Link :href="route('bar.talao', pedido.id)" class="flex h-11 items-center gap-1.5 rounded-[10px] border border-linha-forte bg-white px-3.5 text-[15px] font-bold text-tinta hover:bg-fundo">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M3 9h18v8H3zM6 14h12v7H6z" /></svg>
                                Talão
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
            <p class="text-sm text-suave-2">A página atualiza sozinha a cada 20 segundos.</p>
        </div>
    </main>
</template>
