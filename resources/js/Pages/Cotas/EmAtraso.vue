<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ socios: Array });

const euros = (valor) => Number(valor ?? 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
// Decisão: o atraso conta-se em anos (a cota é anual)
const anosAtraso = (n) => `${n} ${Number(n) === 1 ? 'ano' : 'anos'} em atraso`;
const dividaTotal = computed(() => (props.socios ?? []).reduce((total, socio) => total + Number(socio.valor_divida || 0), 0));
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <Link :href="route('cotas.index')" class="inline-flex items-center gap-1 self-start text-sm font-bold text-verde hover:text-verde-escuro">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                        Cotas
                    </Link>
                    <h1 class="text-[30px] font-extrabold leading-tight">Sócios em atraso</h1>
                </div>
                <a :href="route('socios.pdf')" class="inline-flex h-12 items-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white hover:bg-verde-escuro">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 21h16" /></svg>
                    Exportar PDF
                </a>
            </div>

            <section aria-label="Resumo" class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-[14px] border border-linha bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">Sócios em atraso</div>
                    <strong class="text-[30px] font-extrabold">{{ socios?.length ?? 0 }}</strong>
                </div>
                <div class="rounded-[14px] border border-[#F0D3CD] bg-perigo-claro p-[18px]">
                    <div class="text-sm font-bold text-perigo-texto">Dívida total</div>
                    <strong class="text-[30px] font-extrabold text-perigo-texto">{{ euros(dividaTotal) }}</strong>
                </div>
            </section>

            <div class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <table class="w-full border-collapse text-base">
                    <thead>
                        <tr class="text-left text-[13px] text-suave-2">
                            <th scope="col" class="border-b border-linha-fraca px-4 py-3.5 font-semibold sm:px-5">Sócio</th>
                            <th scope="col" class="border-b border-linha-fraca px-1 py-3.5 text-right font-semibold sm:px-3"><span class="hidden sm:inline">Anos em atraso</span><span class="sm:hidden">Anos</span></th>
                            <th scope="col" class="border-b border-linha-fraca px-4 py-3.5 text-right font-semibold sm:px-5">Dívida</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="socio in socios" :key="socio.id" class="border-b border-linha-fraca">
                            <td class="px-4 py-3 sm:px-5">
                                <Link :href="route('socios.show', socio.id)" class="inline-flex min-h-6 flex-wrap items-center text-tinta hover:text-verde">
                                    <span class="mr-2 font-extrabold text-suave-2">{{ socio.numero_socio }}</span>
                                    <strong>{{ socio.nome }}</strong>
                                </Link>
                                <div v-if="socio.morada" class="text-sm text-suave">{{ socio.morada }}</div>
                            </td>
                            <td class="whitespace-nowrap px-1 py-3 text-right sm:px-3">
                                <span class="hidden sm:inline">{{ anosAtraso(socio.anos_atraso) }}</span>
                                <span class="sm:hidden">{{ socio.anos_atraso }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-extrabold text-perigo sm:px-5">{{ euros(socio.valor_divida) }}</td>
                        </tr>
                        <tr v-if="!socios?.length">
                            <td colspan="3" class="p-8 text-center text-suave">Não há sócios com a cota em atraso.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
