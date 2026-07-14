<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
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
const euros = (v) => Number(v ?? 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '€';
const filtrar = () => router.get(route('relatorios.periodo'), filtros, { preserveState: true });
const pdf = () => { window.location = route('relatorios.pdf', filtros); };
const mostrarTodosProdutos = ref(false);
const produtosVisiveis = computed(() => mostrarTodosProdutos.value ? (props.todos_produtos ?? []) : (props.top_produtos ?? []));
const totalFestaReceitas = computed(() => (props.festa_receitas ?? []).reduce((s, r) => s + Number(r.valor), 0));
const totalFestaCustos = computed(() => (props.festa_custos ?? []).reduce((s, c) => s + Number(c.valor), 0));
</script>
<template>
<AppLayout>
    <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">
        <h1 class="text-2xl font-black">Relatório por Período</h1>

        <!-- Filtros -->
        <form class="grid gap-3 rounded-lg bg-white p-4 shadow-sm md:grid-cols-4" @submit.prevent="filtrar">
            <input v-model="filtros.data_inicio" type="date" class="rounded-md border-slate-300">
            <input v-model="filtros.data_fim" type="date" class="rounded-md border-slate-300">
            <select v-model="filtros.tipo" class="rounded-md border-slate-300">
                <option value="todos">Todos</option>
                <option value="restaurante">Restaurante</option>
                <option value="bar">Bar Conta</option>
                <option value="bar_prepago">Bar Pré-pago</option>
            </select>
            <button class="rounded-md bg-slate-900 px-4 py-2 font-bold text-white">Filtrar</button>
        </form>

        <!-- KPI cards - vendas -->
        <div class="grid gap-4 md:grid-cols-4">
            <div v-for="card in [['Total Vendas', resumo.total_periodo], ['Custo Estimado', resumo.custo_estimado], ['Margem Estimada', resumo.margem_estimada], ['N Pedidos', resumo.total_pedidos]]" :key="card[0]" class="rounded-lg bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">{{ card[0] }}</div>
                <div class="text-3xl font-black">{{ card[0] === 'N Pedidos' ? card[1] : euros(card[1]) }}</div>
                <div v-if="card[0] === 'Margem Estimada'" class="mt-1 text-sm font-bold text-emerald-700">{{ Number(resumo.margem_percentagem || 0).toFixed(1) }}%</div>
            </div>
        </div>

        <!-- Resultado da Festa -->
        <section v-if="resumo.lucro_liquido !== undefined" class="rounded-lg bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-black">Resultado da Festa</h2>
                <a :href="route('contas-festa.index', { data_inicio: filters.data_inicio, data_fim: filters.data_fim })" class="text-sm font-semibold text-amber-700 hover:underline">Ver lancamentos</a>
            </div>

            <!-- Totais resumo -->
            <div class="mb-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Receitas</div>
                    <div class="text-2xl font-black text-emerald-700">{{ euros(totalFestaReceitas) }}</div>
                </div>
                <div class="rounded-lg bg-red-50 border border-red-200 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Custos</div>
                    <div class="text-2xl font-black text-red-700">{{ euros(totalFestaCustos) }}</div>
                </div>
                <div class="rounded-lg border-2 p-4" :class="resumo.lucro_liquido >= 0 ? 'bg-emerald-100 border-emerald-400' : 'bg-red-100 border-red-400'">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lucro Liquido</div>
                    <div class="text-2xl font-black" :class="resumo.lucro_liquido >= 0 ? 'text-emerald-800' : 'text-red-800'">{{ euros(resumo.lucro_liquido || 0) }}</div>
                </div>
            </div>

            <!-- Detalhe por categoria -->
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <h3 class="mb-2 text-sm font-bold text-emerald-700">Receitas</h3>
                    <div v-if="!festa_receitas?.length" class="text-sm text-slate-400">Sem receitas neste periodo.</div>
                    <div v-for="r in festa_receitas" :key="r.label" class="flex justify-between border-t border-slate-100 py-2 text-sm">
                        <span>{{ r.label }}</span>
                        <strong class="text-emerald-700">{{ euros(r.valor) }}</strong>
                    </div>
                </div>
                <div>
                    <h3 class="mb-2 text-sm font-bold text-red-700">Despesas</h3>
                    <div v-if="!festa_custos?.length" class="text-sm text-slate-400">Sem despesas neste periodo.</div>
                    <div v-for="c in festa_custos" :key="c.label" class="flex justify-between border-t border-slate-100 py-2 text-sm">
                        <span>{{ c.label }}</span>
                        <strong class="text-red-700">{{ euros(c.valor) }}</strong>
                    </div>
                </div>
            </div>
        </section>

        <!-- Vendas por dia + exportar -->
        <section class="rounded-lg bg-white p-5 shadow-sm">
            <div class="mb-3 flex justify-between">
                <h2 class="font-black">Vendas por dia</h2>
                <button type="button" class="rounded-md bg-emerald-600 px-4 py-2 font-bold text-white" @click="pdf">Exportar PDF</button>
            </div>
            <div class="flex h-64 items-end gap-3 overflow-x-auto">
                <div v-for="dia in vendas_por_dia" :key="dia.data" class="flex min-w-20 flex-1 flex-col items-center">
                    <strong class="text-xs">{{ euros(dia.total) }}</strong>
                    <div class="w-full rounded-t bg-blue-600" :style="{ height: '210px' }"></div>
                    <span class="mt-2 text-xs">{{ dia.data }}</span>
                </div>
            </div>
        </section>

        <!-- Vendas por hora -->
        <section v-if="vendas_por_hora?.length" class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-black">Vendas por hora</h2>
            <div class="flex h-48 items-end gap-1 overflow-x-auto">
                <div v-for="h in vendas_por_hora" :key="h.hora" class="flex min-w-12 flex-1 flex-col items-center">
                    <strong class="text-xs">{{ h.pedidos }}</strong>
                    <div class="w-full rounded-t bg-violet-500" :style="{ height: '160px' }"></div>
                    <span class="mt-1 text-xs text-slate-500">{{ String(h.hora).padStart(2,'0') }}h</span>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap gap-4 text-sm">
                <div v-for="h in vendas_por_hora" :key="'t'+h.hora" class="text-slate-600">
                    <strong>{{ String(h.hora).padStart(2,'0') }}h:</strong> {{ euros(h.total) }}
                </div>
            </div>
        </section>

        <!-- Bar por ponto -->
        <section class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-black">Dinheiro do Bar por ponto</h2>
            <div v-if="!vendas_bar_por_ponto?.length" class="text-sm text-slate-500">Sem vendas de bar neste periodo.</div>
            <div v-for="linha in vendas_bar_por_ponto" :key="linha.ponto" class="flex justify-between border-t py-2">
                <span class="font-bold">{{ linha.ponto }}</span>
                <strong>{{ euros(linha.total) }} - {{ linha.pedidos }} pedidos - {{ Number(linha.percentagem).toFixed(1) }}%</strong>
            </div>
        </section>

        <!-- Caixa -->
        <section class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="mb-3 font-black">Caixa e fundo de maneio</h2>
            <div v-if="!caixas_por_ponto?.length" class="text-sm text-slate-500">Sem caixas abertas neste periodo.</div>
            <div v-for="linha in caixas_por_ponto" :key="linha.ponto" class="grid gap-2 border-t py-3 text-sm md:grid-cols-8">
                <strong>{{ linha.ponto }}</strong>
                <span>Dias: {{ linha.dias_abertos }}</span>
                <span>Fechados: {{ linha.dias_fechados }}</span>
                <span>Fundo: {{ euros(linha.fundo_maneio) }}</span>
                <span>Vendas: {{ euros(linha.vendas) }}</span>
                <strong class="text-emerald-700">Esperado: {{ euros(linha.esperado_caixa) }}</strong>
                <span>Contado: {{ euros(linha.valor_contado) }}</span>
                <strong :class="Number(linha.diferenca) >= 0 ? 'text-emerald-700' : 'text-red-700'">Dif.: {{ euros(linha.diferenca) }}</strong>
            </div>
        </section>

        <!-- Grid: Tipo + Metodo pagamento + Seccao + Categoria -->
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="mb-3 font-black">Vendas por Tipo</h2>
                <div v-for="r in vendas_por_tipo" :key="r.tipo" class="flex justify-between border-t py-2">
                    <span>{{ r.tipo }}</span>
                    <strong>{{ euros(r.total) }} - {{ Number(r.percentagem).toFixed(1) }}%</strong>
                </div>
            </section>
            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="mb-3 font-black">Metodo de Pagamento</h2>
                <div v-if="!metodos_pagamento?.length" class="text-sm text-slate-500">Sem dados.</div>
                <div v-for="m in metodos_pagamento" :key="m.metodo" class="flex justify-between border-t py-2">
                    <span class="capitalize">{{ m.metodo }}</span>
                    <strong>{{ euros(m.total) }} - {{ m.pedidos }} pedidos</strong>
                </div>
            </section>
            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="mb-3 font-black">Por Seccao (cozinha/bar)</h2>
                <div v-if="!vendas_por_secao?.length" class="text-sm text-slate-500">Sem dados.</div>
                <div v-for="s in vendas_por_secao" :key="s.secao" class="flex justify-between border-t py-2">
                    <span class="capitalize">{{ s.secao }}</span>
                    <strong>{{ s.quantidade }}x - {{ euros(s.total) }}</strong>
                </div>
            </section>
            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="mb-3 font-black">Por Categoria</h2>
                <div v-for="c in top_categorias" :key="c.categoria" class="flex justify-between border-t py-2">
                    <span>{{ c.categoria }}</span>
                    <strong>{{ euros(c.total) }}</strong>
                </div>
            </section>
        </div>

        <!-- Todos os produtos -->
        <section class="rounded-lg bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-black">Produtos Vendidos</h2>
                <button type="button" class="text-sm text-blue-600 hover:underline" @click="mostrarTodosProdutos = !mostrarTodosProdutos">
                    {{ mostrarTodosProdutos ? 'Ver top 10' : 'Ver todos (' + (todos_produtos?.length ?? 0) + ')' }}
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Produto</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-700">Categoria</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-700">Qtd</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-700">Total</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-700">Custo</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-700">Margem</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-700">%</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in produtosVisiveis" :key="p.nome" class="hover:bg-slate-50">
                            <td class="px-3 py-2 font-medium">{{ p.nome }}</td>
                            <td class="px-3 py-2 text-slate-500">{{ p.categoria || '-' }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ p.quantidade }}</td>
                            <td class="px-3 py-2 text-right">{{ euros(p.total) }}</td>
                            <td class="px-3 py-2 text-right text-slate-500">{{ euros(p.custo_estimado) }}</td>
                            <td class="px-3 py-2 text-right text-emerald-700 font-semibold">{{ euros(p.margem_estimada) }}</td>
                            <td class="px-3 py-2 text-right" :class="Number(p.margem_percentagem) > 0 ? 'text-emerald-700' : 'text-red-600'">
                                {{ Number(p.margem_percentagem || 0).toFixed(1) }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</AppLayout>
</template>
