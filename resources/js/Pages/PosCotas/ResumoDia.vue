<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ cotas: Array });
const euros = (v) => Number(v ?? 0).toFixed(2).replace('.', ',') + ' €';
const total = computed(() => (props.cotas ?? []).reduce((s, c) => s + Number(c.valor ?? 0), 0));
const porMetodo = computed(() => (props.cotas ?? []).reduce((acc, c) => { acc[c.metodo_pagamento || 'outro'] = (acc[c.metodo_pagamento || 'outro'] || 0) + Number(c.valor); return acc; }, {}));
const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
const nomesMetodo = { dinheiro: 'Dinheiro', mbway: 'MB Way', transferencia: 'Transferência', outro: 'Outro' };
const nomeMetodo = (m) => nomesMetodo[m] ?? m ?? '';
const hora = (c) => new Date(c.updated_at).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
const periodo = (c) => `${c.tipo === 'anual' ? 'Anual' : meses[c.mes - 1]} ${c.ano}`;
const hoje = new Date().toLocaleDateString('pt-PT');
const imprimir = () => window.print();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums print:min-h-0 print:bg-white">
        <header class="flex h-16 shrink-0 items-center justify-between gap-3 bg-escuro px-4 text-white sm:px-6 print:hidden">
            <div class="flex min-w-0 items-center gap-4">
                <Link :href="route('pos.cotas.index')" class="flex h-11 shrink-0 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    Voltar
                </Link>
                <div class="hidden truncate text-lg font-extrabold sm:block">Resumo do dia · {{ hoje }}</div>
            </div>
            <button type="button" class="flex h-12 items-center gap-2 rounded-[10px] bg-white px-5 text-base font-extrabold text-tinta" @click="imprimir">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" /></svg>
                Imprimir resumo
            </button>
        </header>

        <main class="flex min-h-0 flex-1 flex-col gap-4 p-4 sm:px-6 sm:py-5">
            <h1 class="text-2xl font-extrabold sm:hidden print:block">Resumo do dia · {{ hoje }}</h1>

            <section aria-label="Totais" class="grid grid-cols-2 gap-3 lg:grid-cols-[1.3fr_repeat(4,minmax(0,1fr))]">
                <div class="col-span-2 flex flex-col gap-1.5 rounded-[14px] bg-verde px-5 py-4 text-white lg:col-span-1">
                    <span class="text-sm font-semibold text-verde-claro2">Total cobrado</span>
                    <span class="text-[38px] font-extrabold leading-none">{{ euros(total) }}</span>
                </div>
                <div class="flex flex-col gap-1.5 rounded-[14px] border border-linha bg-white px-5 py-4">
                    <span class="text-sm font-semibold text-suave">N.º de cotas</span>
                    <span class="text-[32px] font-extrabold leading-none">{{ cotas.length }}</span>
                </div>
                <div v-for="(valor, metodo) in porMetodo" :key="metodo" class="flex flex-col gap-1.5 rounded-[14px] border border-linha bg-white px-5 py-4">
                    <span class="text-sm font-semibold text-suave">{{ nomeMetodo(metodo) }}</span>
                    <span class="text-[28px] font-extrabold leading-none">{{ euros(valor) }}</span>
                </div>
            </section>

            <div class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div v-if="!cotas.length" class="px-5 py-10 text-center text-lg font-bold text-suave">Ainda não foram cobradas cotas hoje.</div>

                <!-- Ecrãs estreitos: cartões -->
                <ul v-if="cotas.length" class="divide-y divide-linha-fraca md:hidden print:hidden">
                    <li v-for="cota in cotas" :key="cota.id" class="flex items-start justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <div class="text-[17px] font-bold">{{ cota.socio?.nome }}</div>
                            <div class="text-sm text-suave">{{ hora(cota) }} · {{ periodo(cota) }} · {{ nomeMetodo(cota.metodo_pagamento) }}</div>
                        </div>
                        <div class="shrink-0 text-[17px] font-bold">{{ euros(cota.valor) }}</div>
                    </li>
                </ul>

                <table v-if="cotas.length" class="hidden w-full border-collapse text-[17px] md:table print:table">
                    <thead>
                        <tr class="border-b border-linha-fraca text-left text-[13px] uppercase tracking-[.06em] text-suave-2">
                            <th class="w-[90px] px-5 py-3 font-bold">Hora</th>
                            <th class="px-2 py-3 font-bold">Sócio</th>
                            <th class="px-2 py-3 font-bold">Período</th>
                            <th class="px-2 py-3 text-right font-bold">Valor</th>
                            <th class="py-3 pl-6 pr-5 font-bold">Método</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="cota in cotas" :key="cota.id" class="border-b border-linha-fraca">
                            <td class="px-5 py-3 text-suave">{{ hora(cota) }}</td>
                            <td class="px-2 py-3 font-bold">{{ cota.socio?.nome }}</td>
                            <td class="px-2 py-3">{{ periodo(cota) }}</td>
                            <td class="px-2 py-3 text-right font-bold">{{ euros(cota.valor) }}</td>
                            <td class="py-3 pl-6 pr-5">{{ nomeMetodo(cota.metodo_pagamento) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</template>
