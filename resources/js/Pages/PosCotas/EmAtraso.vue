<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ socios: Array });
const euros = (v) => Number(v ?? 0).toFixed(2).replace('.', ',') + ' €';
const total = computed(() => (props.socios ?? []).reduce((s, socio) => s + Number(socio.valor_em_divida ?? 0), 0));
const anosLabel = (n) => (Number(n) === 1 ? '1 ano em atraso' : `${n ?? 0} anos em atraso`);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums lg:h-screen">
        <header class="flex h-16 shrink-0 items-center justify-between gap-3 bg-escuro px-4 text-white sm:px-6">
            <div class="flex min-w-0 items-center gap-4">
                <Link :href="route('pos.cotas.index')" class="flex h-11 shrink-0 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    Voltar
                </Link>
                <h1 class="truncate text-lg font-extrabold">Sócios com cotas em atraso</h1>
            </div>
            <div class="hidden text-sm text-[#B9C4BE] sm:block">Tesouraria · Cotas</div>
        </header>

        <main class="flex min-h-0 flex-1 flex-col gap-4 p-4 sm:px-6 sm:py-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex items-baseline justify-between gap-3 rounded-[14px] border border-[#F0C9C2] bg-perigo-claro px-[22px] py-[18px] text-perigo-texto">
                    <span class="text-base font-bold">Total em dívida</span>
                    <span class="text-[40px] font-extrabold text-perigo">{{ euros(total) }}</span>
                </div>
                <div class="flex items-baseline justify-between gap-3 rounded-[14px] border border-linha bg-white px-[22px] py-[18px]">
                    <span class="text-base font-bold text-suave">Sócios em atraso</span>
                    <span class="text-[40px] font-extrabold">{{ socios.length }}</span>
                </div>
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="hidden grid-cols-[minmax(0,1fr)_180px_150px_180px] gap-4 border-b border-linha-fraca px-5 py-3 text-[13px] font-bold uppercase tracking-[.06em] text-suave-2 md:grid">
                    <span>Sócio</span><span>Atraso</span><span class="text-right">Em dívida</span><span />
                </div>
                <div class="min-h-0 flex-1 overflow-auto">
                    <div v-if="!socios.length" class="px-5 py-10 text-center text-lg font-bold text-suave">Não há sócios com cotas em atraso.</div>
                    <div
                        v-for="socio in socios"
                        :key="socio.id"
                        class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-3 border-b border-linha-fraca px-5 py-3 md:grid-cols-[minmax(0,1fr)_180px_150px_180px] md:gap-y-0"
                    >
                        <div class="col-span-2 flex min-w-0 flex-col gap-0.5 md:col-span-1">
                            <span class="text-xl font-bold">{{ socio.nome }}</span>
                            <span class="text-sm text-suave-2">N.º {{ socio.numero_socio }}<template v-if="socio.morada"> · {{ socio.morada }}</template></span>
                        </div>
                        <span class="flex h-8 items-center justify-self-start rounded-full bg-perigo-claro px-3 text-[15px] font-bold text-perigo-texto">{{ anosLabel(socio.anos_em_atraso) }}</span>
                        <span class="text-right text-[22px] font-extrabold text-perigo">{{ euros(socio.valor_em_divida) }}</span>
                        <Link :href="route('pos.cotas.socio', socio.id)" class="col-span-2 flex h-14 items-center justify-center rounded-[10px] bg-laranja text-lg font-extrabold text-white md:col-span-1">Cobrar</Link>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
