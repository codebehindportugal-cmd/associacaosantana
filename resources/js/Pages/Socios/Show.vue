<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps({ socio: Object });

const iniciais = computed(() => String(props.socio?.nome ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .map((parte) => parte[0])
    .filter((_, i, todas) => i === 0 || i === todas.length - 1)
    .join('')
    .toUpperCase());

const euros = (valor) => Number(valor ?? 0).toLocaleString('pt-PT', { useGrouping: 'always', minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const anosAtraso = (n) => `${n} ${Number(n) === 1 ? 'ano' : 'anos'} em atraso`;

const estados = {
    pago: ['Paga', 'bg-verde-claro text-verde-escuro'],
    pendente: ['Pendente', 'bg-laranja-claro text-laranja-texto'],
    em_atraso: ['Em atraso', 'bg-perigo-claro text-perigo-texto'],
};
const estado = (cota) => estados[cota.estado] ?? estados.pendente;
const tipo = (cota) => cota.mes ? `Mensal (${String(cota.mes).padStart(2, '0')})` : 'Anual';
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex min-w-0 flex-col gap-1.5">
                    <Link :href="route('socios.index')" class="inline-flex items-center gap-1 self-start text-sm font-bold text-verde hover:text-verde-escuro">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                        Sócios
                    </Link>
                    <h1 class="break-words text-[30px] font-extrabold leading-tight">{{ socio.nome }}</h1>
                </div>
                <Link :href="route('socios.edit', socio.id)" class="inline-flex h-12 items-center gap-2 rounded-[10px] border border-linha-forte bg-white px-5 text-[15px] font-bold text-tinta hover:bg-fundo">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z" /></svg>
                    Editar
                </Link>
            </div>

            <div class="grid items-start gap-5 lg:grid-cols-[340px_minmax(0,1fr)]">
                <section class="flex flex-col gap-4 rounded-[14px] border border-linha bg-white p-5">
                    <div class="flex items-center gap-3.5">
                        <span aria-hidden="true" class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-verde-claro text-xl font-extrabold text-verde-escuro">{{ iniciais }}</span>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-sm font-semibold text-suave-2">Sócio n.º</span>
                            <strong class="text-[26px] font-extrabold leading-none">{{ socio.numero_socio }}</strong>
                        </div>
                    </div>
                    <dl class="flex flex-col gap-3 text-base">
                        <div>
                            <dt class="text-[13px] font-semibold text-suave-2">Terra</dt>
                            <dd class="mt-0.5">{{ socio.morada || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[13px] font-semibold text-suave-2">Telefone</dt>
                            <dd class="mt-0.5 text-lg font-bold">{{ socio.telefone || '—' }}</dd>
                        </div>
                    </dl>
                    <div v-if="socio.cota_em_dia" role="status" class="flex flex-col gap-1 rounded-xl bg-verde-claro p-4 text-center text-verde-escuro">
                        <strong class="text-[17px] font-extrabold tracking-wide">COTA EM DIA</strong>
                    </div>
                    <div v-else role="status" class="flex flex-col gap-1 rounded-xl bg-perigo-claro p-4 text-center text-perigo-texto">
                        <strong class="text-[17px] font-extrabold tracking-wide">EM ATRASO</strong>
                        <span class="text-[15px] font-semibold">{{ anosAtraso(socio.anos_em_atraso) }} · {{ euros(socio.valor_em_divida) }} em dívida</span>
                    </div>
                    <Link :href="route('cotas.index', { socio: socio.id })" class="inline-flex h-[52px] items-center justify-center gap-2 rounded-[10px] bg-laranja text-base font-extrabold text-white hover:brightness-95">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20M6 15h4" /></svg>
                        Registar cota
                    </Link>
                </section>

                <section class="min-w-0 overflow-hidden rounded-[14px] border border-linha bg-white">
                    <div class="border-b border-linha-fraca px-5 py-4">
                        <h2 class="text-[19px] font-extrabold">Histórico de cotas</h2>
                    </div>
                    <table class="w-full border-collapse text-base">
                        <thead>
                            <tr class="text-left text-[13px] text-suave-2">
                                <th scope="col" class="border-b border-linha-fraca px-5 py-3 font-semibold">Ano</th>
                                <th scope="col" class="hidden border-b border-linha-fraca px-3 py-3 font-semibold sm:table-cell">Tipo</th>
                                <th scope="col" class="border-b border-linha-fraca px-3 py-3 text-right font-semibold">Valor</th>
                                <th scope="col" class="border-b border-linha-fraca px-5 py-3 font-semibold">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cota in socio.cotas" :key="cota.id" class="border-b border-linha-fraca">
                                <td class="px-5 py-3 font-extrabold">
                                    {{ cota.ano }}
                                    <span class="block text-[13px] font-medium text-suave-2 sm:hidden">{{ tipo(cota) }}</span>
                                </td>
                                <td class="hidden px-3 py-3 text-suave sm:table-cell">{{ tipo(cota) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-bold">{{ euros(cota.valor) }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex h-7 items-center whitespace-nowrap rounded-full px-3 text-[13px] font-extrabold" :class="estado(cota)[1]">{{ estado(cota)[0] }}</span>
                                </td>
                            </tr>
                            <tr v-if="!socio.cotas?.length">
                                <td colspan="4" class="p-8 text-center text-suave">Ainda não há cotas registadas.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
