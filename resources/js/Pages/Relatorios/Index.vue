<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ resumo: Object, top_produtos_hoje: Array, vendas_bar_por_ponto: Array, caixas_por_ponto: Array });
const euros = (v) => Number(v ?? 0).toLocaleString('pt-PT', { useGrouping: 'always', minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const hoje = new Date().toLocaleDateString('pt-PT');
const percentagem = (v) => Number(v || 0).toLocaleString('pt-PT', { useGrouping: 'always', minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';

// "De onde veio o dinheiro": barra empilhada com os 3 tipos de venda
const tipos = computed(() => {
    const linhas = [
        { nome: 'Restaurante', valor: Number(props.resumo?.vendas_restaurante_hoje || 0), cor: 'bg-verde' },
        { nome: 'Bar Conta', valor: Number(props.resumo?.vendas_bar_hoje || 0), cor: 'bg-azul' },
        { nome: 'Bar Pré-pago', valor: Number(props.resumo?.vendas_prepago_hoje || 0), cor: 'bg-[#7A9FE0]' },
    ];
    const total = linhas.reduce((soma, linha) => soma + linha.valor, 0);
    return linhas.map((linha) => ({ ...linha, largura: total ? (linha.valor / total) * 100 : 0 }));
});

// Caixa ainda aberta = há dias abertos que não foram fechados
const caixaAberta = (linha) => Number(linha.dias_fechados ?? 0) < Number(linha.dias_abertos ?? 0) && !Number(linha.valor_contado);
const corDiferenca = (v) => Number(v || 0) === 0 ? 'text-tinta' : Number(v) > 0 ? 'text-verde' : 'text-perigo';
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <span class="text-sm font-bold text-suave-2">Hoje — {{ hoje }}</span>
                    <h1 class="text-[30px] font-extrabold leading-tight">Relatórios</h1>
                </div>
                <Link :href="route('relatorios.periodo')" class="inline-flex h-12 items-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white hover:bg-verde-escuro">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                    Relatório por período
                </Link>
            </div>

            <section aria-label="Resumo de hoje" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-[14px] bg-escuro p-[18px] text-white">
                    <div class="text-sm font-bold text-escuro-inativo">Total vendido</div>
                    <strong class="text-[32px] font-extrabold">{{ euros(resumo.total_vendas_hoje) }}</strong>
                </div>
                <div class="rounded-[14px] border border-linha bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">Custo estimado</div>
                    <strong class="text-[32px] font-extrabold">{{ euros(resumo.custo_estimado_hoje) }}</strong>
                </div>
                <div class="rounded-[14px] border border-linha bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">Margem estimada</div>
                    <strong class="text-[32px] font-extrabold text-verde">{{ euros(resumo.margem_estimada_hoje) }}</strong>
                    <div class="text-sm font-bold text-verde">{{ percentagem(resumo.margem_percentagem_hoje) }}</div>
                </div>
                <div class="rounded-[14px] border border-linha bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">N.º de pedidos</div>
                    <strong class="text-[32px] font-extrabold">{{ resumo.total_pedidos_hoje }}</strong>
                </div>
            </section>

            <section aria-label="Vendas por tipo" class="flex flex-col gap-3.5 rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                <h2 class="text-[19px] font-extrabold">De onde veio o dinheiro</h2>
                <div class="flex h-3.5 overflow-hidden rounded-full bg-linha-fraca" aria-hidden="true">
                    <div v-for="tipo in tipos" :key="tipo.nome" :class="tipo.cor" :style="{ width: tipo.largura + '%' }" />
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="tipo in tipos" :key="tipo.nome" class="flex items-center gap-2.5">
                        <span class="h-3 w-3 shrink-0 rounded-[3px]" :class="tipo.cor" />
                        <span class="flex-1">{{ tipo.nome }}</span>
                        <strong>{{ euros(tipo.valor) }}</strong>
                    </div>
                </div>
            </section>

            <div class="grid items-start gap-5 lg:grid-cols-2">
                <section class="rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="mb-2 text-[19px] font-extrabold">Dinheiro do Bar por ponto</h2>
                    <div v-if="!vendas_bar_por_ponto?.length" class="border-t border-linha-fraca py-3 text-suave">Sem vendas de bar neste período.</div>
                    <div v-for="linha in vendas_bar_por_ponto" :key="linha.ponto" class="flex flex-wrap justify-between gap-x-3 gap-y-1 border-t border-linha-fraca py-3 text-base">
                        <span class="font-bold">{{ linha.ponto }}</span>
                        <span><strong>{{ euros(linha.total) }}</strong> <span class="text-suave-2">· {{ linha.pedidos }} pedidos</span></span>
                    </div>
                </section>
                <section class="rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="mb-2 text-[19px] font-extrabold">Top 5 produtos hoje</h2>
                    <div v-if="!top_produtos_hoje?.length" class="border-t border-linha-fraca py-3 text-suave">Ainda não há vendas hoje.</div>
                    <ol>
                        <li v-for="(p, i) in top_produtos_hoje" :key="p.nome" class="flex items-center gap-3 border-t border-linha-fraca py-2.5">
                            <span class="w-[26px] shrink-0 font-extrabold text-suave-2">{{ i + 1 }}</span>
                            <span class="min-w-0 flex-1">
                                <strong class="break-words">{{ p.nome }}</strong>
                                <span class="block text-[13px] text-suave-2">{{ p.quantidade }} un. · custo {{ euros(p.custo_estimado) }} · margem <span class="font-semibold text-verde">{{ euros(p.margem_estimada) }} ({{ percentagem(p.margem_percentagem) }})</span></span>
                            </span>
                            <strong class="whitespace-nowrap">{{ euros(p.total) }}</strong>
                        </li>
                    </ol>
                </section>
            </div>

            <section class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <h2 class="text-[19px] font-extrabold">Caixa e fundo de maneio</h2>
                    <Link :href="route('caixa.index')" class="text-[15px] font-bold text-verde hover:text-verde-escuro">Ir para a caixa diária</Link>
                </div>
                <div v-if="!caixas_por_ponto?.length" class="border-t border-linha-fraca px-5 py-4 text-suave">Ainda não há caixas abertas.</div>
                <template v-else>
                    <!-- Tabela em ecrãs largos -->
                    <table class="hidden w-full border-collapse text-base md:table">
                        <thead>
                            <tr class="text-right text-[13px] text-suave-2">
                                <th scope="col" class="border-y border-linha-fraca px-5 py-3 text-left font-semibold">Ponto</th>
                                <th scope="col" class="border-y border-linha-fraca p-3 font-semibold">Fundo</th>
                                <th scope="col" class="border-y border-linha-fraca p-3 font-semibold">Vendas</th>
                                <th scope="col" class="border-y border-linha-fraca p-3 font-semibold">Esperado</th>
                                <th scope="col" class="border-y border-linha-fraca p-3 font-semibold">Contado</th>
                                <th scope="col" class="border-y border-linha-fraca px-5 py-3 font-semibold">Diferença</th>
                            </tr>
                        </thead>
                        <tbody class="text-right">
                            <tr v-for="linha in caixas_por_ponto" :key="linha.ponto" class="border-b border-linha-fraca last:border-0">
                                <th scope="row" class="px-5 py-3 text-left">{{ linha.ponto }}</th>
                                <td class="p-3">{{ euros(linha.fundo_maneio) }}</td>
                                <td class="p-3">{{ euros(linha.vendas) }}</td>
                                <td class="p-3 font-extrabold text-verde">{{ euros(linha.esperado_caixa) }}</td>
                                <td class="p-3" :class="caixaAberta(linha) ? 'text-suave-2' : ''">{{ caixaAberta(linha) ? '—' : euros(linha.valor_contado) }}</td>
                                <td class="px-5 py-3" :class="caixaAberta(linha) ? 'text-suave-2' : ['font-extrabold', corDiferenca(linha.diferenca)]">{{ caixaAberta(linha) ? 'caixa aberta' : euros(linha.diferenca) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <!-- Cartões em ecrãs estreitos -->
                    <ul class="divide-y divide-linha-fraca border-t border-linha-fraca md:hidden">
                        <li v-for="linha in caixas_por_ponto" :key="linha.ponto" class="grid grid-cols-2 gap-x-3 gap-y-1 px-4 py-3 text-[15px]">
                            <strong class="col-span-2 text-base">{{ linha.ponto }}</strong>
                            <span class="flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">Fundo</span>{{ euros(linha.fundo_maneio) }}</span>
                            <span class="flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">Vendas</span>{{ euros(linha.vendas) }}</span>
                            <span class="flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">Esperado</span><strong class="text-verde">{{ euros(linha.esperado_caixa) }}</strong></span>
                            <span class="flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">Contado</span>{{ caixaAberta(linha) ? '—' : euros(linha.valor_contado) }}</span>
                            <span class="col-span-2 flex justify-between gap-2"><span class="text-[13px] font-semibold text-suave-2">Diferença</span><span :class="caixaAberta(linha) ? 'text-suave-2' : ['font-extrabold', corDiferenca(linha.diferenca)]">{{ caixaAberta(linha) ? 'caixa aberta' : euros(linha.diferenca) }}</span></span>
                        </li>
                    </ul>
                </template>
            </section>
        </div>
    </AppLayout>
</template>
