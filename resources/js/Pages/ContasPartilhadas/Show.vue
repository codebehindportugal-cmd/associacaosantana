<script setup>
import { Head, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, reactive } from 'vue';

const props = defineProps({
    filters: Object,
    receitas: Array,
    divisao: Object,
    atualizadoEm: String,
});

const filtros = reactive({ ...props.filters });

const euros = (valor) => Number(valor || 0).toLocaleString('pt-PT', { useGrouping: 'always',
    style: 'currency',
    currency: 'EUR',
});

const percentagem = (valor) => Number(valor || 0).toLocaleString('pt-PT', { useGrouping: 'always',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
}) + '%';

// Largura da barra de percentagem (só visual)
const larguraBarra = (valor) => `${Math.min(100, Math.max(0, Number(valor || 0)))}%`;

const recarregar = () => router.reload({ only: ['receitas', 'divisao', 'atualizadoEm'] });

const filtrar = () => router.get(window.location.pathname, filtros, {
    preserveState: true,
    preserveScroll: true,
});

let temporizador = null;
onMounted(() => {
    temporizador = setInterval(recarregar, 60000);
});
onUnmounted(() => {
    if (temporizador) clearInterval(temporizador);
});
</script>

<template>
    <Head title="Contas partilhadas do evento" />

    <div class="min-h-screen bg-fundo px-4 py-8 font-sans text-tinta tabular-nums sm:py-12">
        <div class="mx-auto w-full max-w-[880px]">
            <header class="mb-6">
                <h1 class="m-0 text-[clamp(28px,3.6vw,40px)] font-extrabold leading-[1.1] tracking-[-0.01em]">Contas partilhadas do evento</h1>
                <p class="m-0 mt-2 max-w-[38rem] text-base text-suave">
                    Receita bruta e divisao pelas associacoes participantes. Cada associacao
                    suporta os seus proprios custos.
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-suave">
                    <span class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-verde-ok" aria-hidden="true"></span>
                        Atualizado as {{ atualizadoEm }}
                    </span>
                    <button type="button" class="inline-flex h-11 items-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo" @click="recarregar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8" /><path d="M21 3v5h-5" /></svg>
                        Atualizar agora
                    </button>
                </div>
            </header>

            <section class="mb-5 grid gap-5 rounded-[14px] border border-linha bg-white p-5 sm:p-6 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                <div>
                    <div class="text-[15px] font-bold text-suave">Receita bruta do periodo</div>
                    <div class="mt-1 text-[clamp(36px,5vw,52px)] font-extrabold leading-none text-verde">{{ euros(divisao.receita_bruta) }}</div>
                </div>
                <form class="grid grid-cols-2 gap-2.5 rounded-[10px] bg-fundo p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="filtrar">
                    <input v-model="filtros.data_inicio" type="date" aria-label="Data de início" class="h-11 rounded-[10px] border-linha-forte text-[15px] focus:border-verde focus:ring-verde">
                    <input v-model="filtros.data_fim" type="date" aria-label="Data de fim" class="h-11 rounded-[10px] border-linha-forte text-[15px] focus:border-verde focus:ring-verde">
                    <button class="col-span-2 h-11 rounded-[10px] bg-escuro px-5 text-[15px] font-bold text-white transition hover:bg-escuro-2 sm:col-span-1">Filtrar</button>
                </form>
            </section>

            <section class="mb-5 rounded-[14px] border border-linha bg-white p-5 sm:p-6">
                <h2 class="m-0 mb-4 text-[22px] font-extrabold">Divisao acordada</h2>

                <div v-if="!divisao.percentagens_ok" class="mb-4 flex items-start gap-2.5 rounded-[10px] bg-laranja-claro p-3.5 text-[15px] font-bold text-laranja-texto" role="alert">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mt-px shrink-0"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg>
                    <span>As percentagens somam {{ percentagem(divisao.soma_percentagens) }} e nao 100%.
                    Os valores abaixo sao provisorios.</span>
                </div>

                <div v-if="!divisao.linhas.length" class="rounded-[10px] bg-fundo p-6 text-center text-[15px] font-bold text-suave">
                    Ainda nao ha associacoes definidas.
                </div>

                <div v-else>
                    <!-- Cabeçalho (ecrãs largos) -->
                    <div class="hidden grid-cols-[minmax(0,1fr)_120px_130px] gap-4 border-b border-linha pb-2 text-xs font-bold uppercase tracking-[0.1em] text-suave-2 sm:grid">
                        <span>Associacao</span>
                        <span class="text-right">Percentagem</span>
                        <span class="text-right">Quota-parte</span>
                    </div>
                    <div
                        v-for="linha in divisao.linhas"
                        :key="linha.id"
                        class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 border-b border-linha-fraca py-3.5 sm:grid-cols-[minmax(0,1fr)_120px_130px]"
                    >
                        <div class="col-span-2 min-w-0 sm:col-span-1">
                            <div class="font-bold">
                                {{ linha.nome }}
                                <span v-if="linha.sigla" class="ml-1 text-sm font-normal text-suave-2">({{ linha.sigla }})</span>
                            </div>
                            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-linha-fraca" aria-hidden="true">
                                <div class="h-full rounded-full bg-verde transition-all duration-500" :style="{ width: larguraBarra(linha.percentagem) }"></div>
                            </div>
                        </div>
                        <div class="text-left text-base text-suave sm:text-right">{{ percentagem(linha.percentagem) }}</div>
                        <div class="text-right text-lg font-extrabold">{{ euros(linha.valor) }}</div>
                    </div>
                    <div class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-4 border-t-2 border-linha pt-3.5 sm:grid-cols-[minmax(0,1fr)_120px_130px]">
                        <span class="text-lg font-extrabold">Atribuido</span>
                        <span class="text-right font-bold">{{ percentagem(divisao.soma_percentagens) }}</span>
                        <span class="text-right text-lg font-extrabold">{{ euros(divisao.atribuido) }}</span>
                    </div>
                    <div v-if="Math.abs(Number(divisao.residuo)) >= 0.01" class="mt-2 flex items-center justify-between gap-4 text-sm text-suave">
                        <span>Por atribuir / arredondamento</span>
                        <span class="font-bold">{{ euros(divisao.residuo) }}</span>
                    </div>
                </div>
            </section>

            <section class="rounded-[14px] border border-linha bg-white p-5 sm:p-6">
                <h2 class="m-0 mb-2 text-[22px] font-extrabold">Origem da receita</h2>
                <div v-for="linha in receitas" :key="linha.categoria + '-' + linha.origem" class="flex min-h-[52px] items-center justify-between gap-4 border-t border-linha-fraca py-3 text-base">
                    <span class="font-bold">{{ linha.label }}</span>
                    <strong class="text-lg font-extrabold text-verde">{{ euros(linha.valor) }}</strong>
                </div>
            </section>

            <p class="m-0 mt-6 text-center text-sm text-suave">
                Pagina de consulta. Os valores sao atualizados automaticamente durante o evento.
            </p>
        </div>
    </div>
</template>
