<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
const props = defineProps({
    filters: Object,
    resumo: Object,
    vendas_por_dia: Array,
    vendas_por_tipo: Array,
    vendas_bar_por_ponto: Array,
    caixas_por_ponto: Array,
    top_produtos: Array,
    todos_produtos: Array,
    top_categorias: Array,
    vendas_por_hora: Array,
    vendas_por_secao: Array,
    metodos_pagamento: Array,
    festa_receitas: Array,
    festa_custos: Array,
});
const filtros = reactive({ ...props.filters });
const max = computed(() => Math.max(1, ...(props.vendas_por_dia ?? []).map((d) => Number(d.total))));
const maxHora = computed(() => Math.max(1, ...(props.vendas_por_hora ?? []).map((h) => Number(h.total))));
const euros = (v) => Number(v ?? 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const filtrar = () => router.get(route('relatorios.periodo'), filtros, { preserveState: true });
const pdf = () => { window.location = route('relatorios.pdf', filtros); };
const mostrarTodosProdutos = ref(false);
const produtosVisiveis = computed(() => mostrarTodosProdutos.value ? (props.todos_produtos ?? []) : (props.top_produtos ?? []));
const totalFestaReceitas = computed(() => (props.festa_receitas ?? []).reduce((s, r) => s + Number(r.valor), 0));
const totalFestaCustos = computed(() => (props.festa_custos ?? []).reduce((s, c) => s + Number(c.valor), 0));

// --- Só para a apresentação ---
const percentagem = (v) => Number(v || 0).toLocaleString('pt-PT', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
const eurosCurto = (v) => Math.round(Number(v || 0)).toLocaleString('pt-PT') + ' €';
const sinal = (v) => (Number(v) > 0 ? '+' : '') + euros(v);
const corDiferenca = (v) => Number(v || 0) === 0 ? 'text-tinta' : Number(v) > 0 ? 'text-verde' : 'text-perigo';
const diaCurto = (data) => {
    const [, mes, dia] = String(data).split('T')[0].split('-');
    return dia && mes ? `${dia}/${mes}` : data;
};
const hora = (h) => `${String(h).padStart(2, '0')}h`;
const maxPedidosHora = computed(() => Math.max(1, ...(props.vendas_por_hora ?? []).map((h) => Number(h.pedidos))));
const horaPico = computed(() => (props.vendas_por_hora ?? []).reduce((melhor, h) => (!melhor || Number(h.total) > Number(melhor.total) ? h : melhor), null));

const nomesTipo = { restaurante: 'Restaurante', bar_conta: 'Bar Conta', bar_prepago: 'Bar Pré-pago' };
const nomeTipo = (tipo) => nomesTipo[tipo] ?? tipo;

const coresSecao = {
    grelhados: 'bg-secao-grelhados',
    cozinha: 'bg-secao-cozinha',
    bar: 'bg-secao-bar',
    sobremesas: 'bg-secao-sobremesas',
    acompanhamentos: 'bg-secao-acompanhamentos',
    servico: 'bg-secao-servico',
};
const corSecao = (secao) => coresSecao[String(secao ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')] ?? 'bg-suave';

const campo = 'h-12 w-full min-w-0 rounded-[10px] border border-linha-forte bg-white px-2.5 text-[15px] text-tinta focus:border-verde focus:ring-verde';
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <Link :href="route('relatorios.index')" class="inline-flex items-center gap-1 self-start text-sm font-bold text-verde hover:text-verde-escuro">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                        Relatórios de hoje
                    </Link>
                    <h1 class="text-[30px] font-extrabold leading-tight">Relatório por período</h1>
                </div>
                <button type="button" class="inline-flex h-12 items-center gap-2 rounded-[10px] border border-linha-forte bg-white px-5 text-[15px] font-bold text-tinta hover:bg-fundo" @click="pdf">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 21h16" /></svg>
                    Exportar PDF
                </button>
            </div>

            <!-- Filtros -->
            <form class="grid items-end gap-3 rounded-[14px] border border-linha bg-white p-4 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="filtrar">
                <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">De<input v-model="filtros.data_inicio" type="date" :class="campo"></label>
                <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Até<input v-model="filtros.data_fim" type="date" :class="campo"></label>
                <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Tipo de venda
                    <select v-model="filtros.tipo" :class="campo">
                        <option value="todos">Todos</option>
                        <option value="restaurante">Restaurante</option>
                        <option value="bar">Bar Conta</option>
                        <option value="bar_prepago">Bar Pré-pago</option>
                    </select>
                </label>
                <button class="h-12 rounded-[10px] bg-verde text-[15px] font-bold text-white hover:bg-verde-escuro">Filtrar</button>
            </form>

            <!-- Resumo -->
            <section aria-label="Resumo do período" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-[14px] bg-escuro p-[18px] text-white">
                    <div class="text-sm font-bold text-escuro-inativo">Total vendas</div>
                    <strong class="text-[30px] font-extrabold">{{ euros(resumo.total_periodo) }}</strong>
                </div>
                <div class="rounded-[14px] border border-linha bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">Custo estimado</div>
                    <strong class="text-[30px] font-extrabold">{{ euros(resumo.custo_estimado) }}</strong>
                </div>
                <div class="rounded-[14px] border border-linha bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">Margem estimada</div>
                    <strong class="text-[30px] font-extrabold text-verde">{{ euros(resumo.margem_estimada) }}</strong>
                    <div class="text-sm font-bold text-verde">{{ percentagem(resumo.margem_percentagem) }}</div>
                </div>
                <div class="rounded-[14px] border border-linha bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">N.º de pedidos</div>
                    <strong class="text-[30px] font-extrabold">{{ Number(resumo.total_pedidos ?? 0).toLocaleString('pt-PT') }}</strong>
                </div>
            </section>

            <!-- Resultado da festa -->
            <section v-if="resumo.lucro_liquido !== undefined" class="flex flex-col gap-4 rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-[19px] font-extrabold">Resultado da festa</h2>
                    <a :href="route('contas-festa.index', { data_inicio: filters.data_inicio, data_fim: filters.data_fim })" class="text-[15px] font-bold text-verde hover:text-verde-escuro">Ver lançamentos</a>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-[10px] bg-verde-claro p-3.5 text-verde-escuro">
                        <div class="text-[13px] font-bold">Total receitas</div>
                        <strong class="text-2xl">{{ euros(totalFestaReceitas) }}</strong>
                    </div>
                    <div class="rounded-[10px] bg-perigo-claro p-3.5 text-perigo-texto">
                        <div class="text-[13px] font-bold">Total custos</div>
                        <strong class="text-2xl">{{ euros(totalFestaCustos) }}</strong>
                    </div>
                    <div class="rounded-[10px] p-3.5 text-white" :class="resumo.lucro_liquido >= 0 ? 'bg-verde' : 'bg-perigo'">
                        <div class="text-[13px] font-bold" :class="resumo.lucro_liquido >= 0 ? 'text-verde-claro2' : 'text-perigo-claro'">Lucro líquido</div>
                        <strong class="text-2xl">{{ euros(resumo.lucro_liquido || 0) }}</strong>
                    </div>
                </div>
                <div class="grid gap-5 lg:grid-cols-2">
                    <div>
                        <h3 class="mb-1 text-[15px] font-extrabold text-verde">Receitas</h3>
                        <div v-if="!festa_receitas?.length" class="border-t border-linha-fraca py-2.5 text-suave">Sem receitas neste período.</div>
                        <div v-for="r in festa_receitas" :key="r.label" class="flex justify-between gap-3 border-t border-linha-fraca py-2.5">
                            <span>{{ r.label }}</span>
                            <strong class="whitespace-nowrap">{{ euros(r.valor) }}</strong>
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-1 text-[15px] font-extrabold text-perigo">Despesas</h3>
                        <div v-if="!festa_custos?.length" class="border-t border-linha-fraca py-2.5 text-suave">Sem despesas neste período.</div>
                        <div v-for="c in festa_custos" :key="c.label" class="flex justify-between gap-3 border-t border-linha-fraca py-2.5">
                            <span>{{ c.label }}</span>
                            <strong class="whitespace-nowrap">{{ euros(c.valor) }}</strong>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Gráficos -->
            <div class="grid items-start gap-5 lg:grid-cols-2">
                <section class="flex min-w-0 flex-col gap-3.5 rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="text-[19px] font-extrabold">Vendas por dia</h2>
                    <div v-if="!vendas_por_dia?.length" class="text-suave">Sem vendas neste período.</div>
                    <div v-else class="overflow-x-auto">
                        <div
                            role="img"
                            :aria-label="'Vendas por dia: ' + vendas_por_dia.map((d) => `${diaCurto(d.data)} ${euros(d.total)}`).join(', ')"
                            class="flex h-[220px] items-end gap-3.5 border-b border-linha"
                            :style="{ minWidth: vendas_por_dia.length * 56 + 'px' }"
                        >
                            <div v-for="dia in vendas_por_dia" :key="dia.data" class="flex flex-1 flex-col items-center gap-1.5">
                                <strong class="whitespace-nowrap text-[13px]">{{ eurosCurto(dia.total) }}</strong>
                                <div class="w-full rounded-t-md bg-verde" :style="{ height: Math.max(2, Math.round((Number(dia.total) / max) * 180)) + 'px' }" />
                            </div>
                        </div>
                        <div class="mt-1.5 flex gap-3.5 text-[13px] text-suave-2" :style="{ minWidth: vendas_por_dia.length * 56 + 'px' }">
                            <span v-for="dia in vendas_por_dia" :key="'l' + dia.data" class="flex-1 text-center">{{ diaCurto(dia.data) }}</span>
                        </div>
                    </div>
                </section>

                <section v-if="vendas_por_hora?.length" class="flex min-w-0 flex-col gap-3.5 rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="text-[19px] font-extrabold">Vendas por hora <span class="text-sm font-semibold text-suave-2">(n.º de pedidos)</span></h2>
                    <div class="overflow-x-auto">
                        <div
                            role="img"
                            :aria-label="horaPico ? `Pedidos por hora, pico às ${hora(horaPico.hora)}` : 'Pedidos por hora'"
                            class="flex h-[220px] items-end gap-1.5 border-b border-linha"
                            :style="{ minWidth: vendas_por_hora.length * 28 + 'px' }"
                        >
                            <div v-for="h in vendas_por_hora" :key="h.hora" class="flex flex-1 flex-col items-center gap-1" :title="`${hora(h.hora)}: ${euros(h.total)}`">
                                <strong class="text-[11px]">{{ h.pedidos }}</strong>
                                <div class="w-full rounded-t bg-azul" :style="{ height: Math.max(2, Math.round((Number(h.pedidos) / maxPedidosHora) * 180)) + 'px' }" />
                            </div>
                        </div>
                        <div class="mt-1.5 flex gap-1.5 text-[11px] text-suave-2" :style="{ minWidth: vendas_por_hora.length * 28 + 'px' }">
                            <span v-for="h in vendas_por_hora" :key="'l' + h.hora" class="flex-1 text-center">{{ hora(h.hora) }}</span>
                        </div>
                    </div>
                    <p v-if="horaPico && maxHora" class="text-sm text-suave">Hora de mais movimento: <strong class="text-tinta">{{ hora(horaPico.hora) }}</strong> · {{ euros(horaPico.total) }}</p>
                    <details class="text-sm text-suave">
                        <summary class="cursor-pointer font-semibold text-tinta">Valor vendido por hora</summary>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                            <span v-for="h in vendas_por_hora" :key="'t' + h.hora"><strong class="text-tinta">{{ hora(h.hora) }}:</strong> {{ euros(h.total) }}</span>
                        </div>
                    </details>
                </section>
            </div>

            <!-- Tipo / Método / Secção / Categoria -->
            <div class="grid items-start gap-5 lg:grid-cols-2">
                <section class="rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="mb-2 text-[19px] font-extrabold">Vendas por tipo</h2>
                    <div v-if="!vendas_por_tipo?.length" class="border-t border-linha-fraca py-2.5 text-suave">Sem dados.</div>
                    <div v-for="r in vendas_por_tipo" :key="r.tipo" class="flex flex-wrap justify-between gap-x-3 border-t border-linha-fraca py-2.5">
                        <span>{{ nomeTipo(r.tipo) }}</span>
                        <span><strong>{{ euros(r.total) }}</strong> <span class="text-suave-2">· {{ percentagem(r.percentagem) }}</span></span>
                    </div>
                </section>
                <section class="rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="mb-2 text-[19px] font-extrabold">Método de pagamento</h2>
                    <div v-if="!metodos_pagamento?.length" class="border-t border-linha-fraca py-2.5 text-suave">Sem dados.</div>
                    <div v-for="m in metodos_pagamento" :key="m.metodo" class="flex flex-wrap justify-between gap-x-3 border-t border-linha-fraca py-2.5">
                        <span class="capitalize">{{ m.metodo }}</span>
                        <span><strong>{{ euros(m.total) }}</strong> <span class="text-suave-2">· {{ m.pedidos }} pedidos</span></span>
                    </div>
                </section>
                <section class="rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="mb-2 text-[19px] font-extrabold">Por secção</h2>
                    <div v-if="!vendas_por_secao?.length" class="border-t border-linha-fraca py-2.5 text-suave">Sem dados.</div>
                    <div v-for="s in vendas_por_secao" :key="s.secao" class="flex flex-wrap items-center justify-between gap-x-3 border-t border-linha-fraca py-2.5">
                        <span class="flex items-center gap-2 capitalize"><span class="h-2.5 w-2.5 rounded-full" :class="corSecao(s.secao)" />{{ s.secao }}</span>
                        <span><strong>{{ euros(s.total) }}</strong> <span class="text-suave-2">· {{ s.quantidade }}×</span></span>
                    </div>
                </section>
                <section class="rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="mb-2 text-[19px] font-extrabold">Por categoria</h2>
                    <div v-if="!top_categorias?.length" class="border-t border-linha-fraca py-2.5 text-suave">Sem dados.</div>
                    <div v-for="c in top_categorias" :key="c.categoria" class="flex justify-between gap-3 border-t border-linha-fraca py-2.5">
                        <span>{{ c.categoria }}</span>
                        <strong class="whitespace-nowrap">{{ euros(c.total) }}</strong>
                    </div>
                </section>
            </div>

            <!-- Bar por ponto + Caixa -->
            <div class="grid items-start gap-5 lg:grid-cols-2">
                <section class="rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="mb-2 text-[19px] font-extrabold">Dinheiro do Bar por ponto</h2>
                    <div v-if="!vendas_bar_por_ponto?.length" class="border-t border-linha-fraca py-2.5 text-suave">Sem vendas de bar neste período.</div>
                    <div v-for="linha in vendas_bar_por_ponto" :key="linha.ponto" class="flex items-baseline justify-between gap-3 border-t border-linha-fraca py-2.5">
                        <span class="font-bold">{{ linha.ponto }}</span>
                        <span class="flex flex-col items-end">
                            <strong class="whitespace-nowrap">{{ euros(linha.total) }}</strong>
                            <span class="whitespace-nowrap text-[13px] text-suave-2">{{ linha.pedidos }} pedidos · {{ percentagem(linha.percentagem) }}</span>
                        </span>
                    </div>
                </section>
                <section class="rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="mb-2 text-[19px] font-extrabold">Caixa e fundo de maneio</h2>
                    <div v-if="!caixas_por_ponto?.length" class="border-t border-linha-fraca py-2.5 text-suave">Sem caixas abertas neste período.</div>
                    <div v-for="linha in caixas_por_ponto" :key="linha.ponto" class="flex flex-col gap-1 border-t border-linha-fraca py-2.5">
                        <div class="flex justify-between gap-3">
                            <strong>{{ linha.ponto }}</strong>
                            <strong class="whitespace-nowrap" :class="corDiferenca(linha.diferenca)">Dif. {{ sinal(linha.diferenca) }}</strong>
                        </div>
                        <span class="text-[13px] text-suave-2">{{ linha.dias_abertos }} dias · {{ linha.dias_fechados }} fechados · fundo {{ euros(linha.fundo_maneio) }} · vendas {{ euros(linha.vendas) }} · esperado <span class="font-semibold text-verde">{{ euros(linha.esperado_caixa) }}</span> · contado {{ euros(linha.valor_contado) }}</span>
                    </div>
                </section>
            </div>

            <!-- Produtos vendidos -->
            <section class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <h2 class="text-[19px] font-extrabold">Produtos vendidos</h2>
                    <div role="group" aria-label="Mostrar" class="flex gap-1.5 rounded-xl bg-fundo p-1">
                        <button type="button" :aria-pressed="!mostrarTodosProdutos" class="h-10 rounded-[10px] px-3.5 text-sm font-bold" :class="!mostrarTodosProdutos ? 'bg-white text-tinta shadow-sm' : 'text-suave'" @click="mostrarTodosProdutos = false">Top 10</button>
                        <button type="button" :aria-pressed="mostrarTodosProdutos" class="h-10 rounded-[10px] px-3.5 text-sm font-bold" :class="mostrarTodosProdutos ? 'bg-white text-tinta shadow-sm' : 'text-suave'" @click="mostrarTodosProdutos = true">Ver todos ({{ todos_produtos?.length ?? 0 }})</button>
                    </div>
                </div>
                <div v-if="!produtosVisiveis.length" class="border-t border-linha-fraca px-5 py-4 text-suave">Sem produtos vendidos neste período.</div>
                <template v-else>
                    <table class="hidden w-full border-collapse text-[15px] md:table">
                        <thead>
                            <tr class="text-right text-[13px] text-suave-2">
                                <th scope="col" class="border-y border-linha-fraca px-5 py-3 text-left font-semibold">Produto</th>
                                <th scope="col" class="border-y border-linha-fraca p-3 text-left font-semibold">Categoria</th>
                                <th scope="col" class="border-y border-linha-fraca p-3 font-semibold">Qtd</th>
                                <th scope="col" class="border-y border-linha-fraca p-3 font-semibold">Total</th>
                                <th scope="col" class="border-y border-linha-fraca p-3 font-semibold">Custo</th>
                                <th scope="col" class="border-y border-linha-fraca p-3 font-semibold">Margem</th>
                                <th scope="col" class="border-y border-linha-fraca px-5 py-3 font-semibold">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in produtosVisiveis" :key="p.nome" class="border-b border-linha-fraca text-right last:border-0">
                                <td class="px-5 py-[11px] text-left font-bold">{{ p.nome }}</td>
                                <td class="px-3 py-[11px] text-left text-suave-2">{{ p.categoria || '—' }}</td>
                                <td class="px-3 py-[11px] font-bold">{{ p.quantidade }}</td>
                                <td class="whitespace-nowrap px-3 py-[11px]">{{ euros(p.total) }}</td>
                                <td class="whitespace-nowrap px-3 py-[11px] text-suave-2">{{ euros(p.custo_estimado) }}</td>
                                <td class="whitespace-nowrap px-3 py-[11px] font-bold text-verde">{{ euros(p.margem_estimada) }}</td>
                                <td class="px-5 py-[11px]" :class="Number(p.margem_percentagem) > 0 ? 'text-verde' : 'text-perigo'">{{ percentagem(p.margem_percentagem) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <ul class="divide-y divide-linha-fraca border-t border-linha-fraca md:hidden">
                        <li v-for="p in produtosVisiveis" :key="p.nome" class="grid grid-cols-2 gap-x-3 gap-y-1 px-4 py-3 text-[15px]">
                            <strong class="col-span-2 break-words">{{ p.nome }}</strong>
                            <span class="flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">Categoria</span><span class="truncate">{{ p.categoria || '—' }}</span></span>
                            <span class="flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">Qtd</span><strong>{{ p.quantidade }}</strong></span>
                            <span class="flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">Total</span>{{ euros(p.total) }}</span>
                            <span class="flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">Custo</span>{{ euros(p.custo_estimado) }}</span>
                            <span class="flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">Margem</span><strong class="text-verde">{{ euros(p.margem_estimada) }}</strong></span>
                            <span class="flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">%</span><span :class="Number(p.margem_percentagem) > 0 ? 'text-verde' : 'text-perigo'">{{ percentagem(p.margem_percentagem) }}</span></span>
                        </li>
                    </ul>
                </template>
            </section>
        </div>
    </AppLayout>
</template>
