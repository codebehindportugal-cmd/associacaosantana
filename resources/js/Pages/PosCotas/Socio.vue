<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    socio: Object,
    cotasRecentes: Array,
    anosEmAtraso: Number,
    valorEmDivida: Number,
    anosPagos: Array,
    valorCota: { type: Number, default: 5 },
    anoInscricao: Number,
});

const anoAtual = new Date().getFullYear();
const pagos = computed(() => new Set((props.anosPagos ?? []).map(Number)));

// Mostra desde a inscricao ate hoje, mas nunca mais de 6 anos de uma vez:
// numa mesa de arraial ninguem quer uma grelha com trinta botoes
const anos = computed(() => {
    const inicio = Math.max(props.anoInscricao ?? anoAtual, anoAtual - 5);
    const lista = [];

    for (let ano = Math.min(inicio, anoAtual); ano <= anoAtual; ano++) {
        lista.push(ano);
    }

    return lista;
});

// Ja vem escolhido o que falta pagar — normalmente e so o ano corrente
const selecionados = ref(anos.value.filter((ano) => !pagos.value.has(ano)));

const alternar = (ano) => {
    if (pagos.value.has(ano)) return;

    const indice = selecionados.value.indexOf(ano);
    if (indice === -1) selecionados.value.push(ano);
    else selecionados.value.splice(indice, 1);
};

const metodo = ref('dinheiro');
const recebido = ref('');
const total = computed(() => selecionados.value.length * props.valorCota);
const troco = computed(() => Math.max(0, Number(recebido.value || total.value) - total.value));
const euros = (v) => Number(v ?? 0).toFixed(2).replace('.', ',') + ' €';

// So UI: rotulos, atraso em anos e valores rapidos para o dinheiro recebido
const metodos = [
    { id: 'dinheiro', label: 'Dinheiro' },
    { id: 'mbway', label: 'MB Way' },
    { id: 'transferencia', label: 'Transferência' },
];
const nomeMetodo = (m) => metodos.find((x) => x.id === m)?.label ?? m ?? '';
const atrasoLabel = computed(() => (Number(props.anosEmAtraso) === 1 ? '1 ano em atraso' : `${props.anosEmAtraso ?? 0} anos em atraso`));
const resumo = computed(() => (selecionados.value.length === 1 ? '1 ano a pagar' : `${selecionados.value.length} anos a pagar`));
const rapidos = [10, 20, 50];

const form = useForm({ anos: [], metodo_pagamento: 'dinheiro', valor_recebido: null });

const submit = () => {
    if (!selecionados.value.length) return;

    form.anos = [...selecionados.value].sort();
    form.metodo_pagamento = metodo.value;
    form.valor_recebido = recebido.value || total.value;
    form.post(route('pos.cotas.pagar', props.socio.id));
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums lg:h-screen">
        <header class="flex h-16 shrink-0 items-center justify-between gap-3 bg-escuro px-4 text-white sm:px-6">
            <div class="flex items-center gap-4">
                <Link :href="route('pos.cotas.socio.pesquisa')" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    Voltar à pesquisa
                </Link>
                <div class="hidden text-lg font-extrabold sm:block">Receber cota</div>
            </div>
            <div class="hidden text-sm text-[#B9C4BE] sm:block">Tesouraria · Cotas</div>
        </header>

        <div class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_560px]">
            <section aria-label="Sócio" class="flex min-h-0 flex-col gap-[18px] p-4 sm:p-6">
                <div>
                    <div class="text-[15px] font-bold text-suave-2">Sócio N.º {{ socio.numero_socio }}</div>
                    <h1 class="mb-1.5 mt-1 text-[34px] font-extrabold leading-tight">{{ socio.nome }}</h1>
                    <div v-if="socio.morada || socio.telefone" class="text-base text-suave">
                        <template v-if="socio.morada">{{ socio.morada }}</template>
                        <template v-if="socio.morada && socio.telefone"> · </template>
                        <template v-if="socio.telefone">{{ socio.telefone }}</template>
                    </div>
                </div>

                <div v-if="socio.cota_em_dia" class="flex items-center gap-4 rounded-[14px] border border-verde-claro2 bg-verde-claro px-5 py-[18px] text-verde-escuro">
                    <svg class="h-8 w-8 shrink-0 text-verde" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
                    <span class="text-[22px] font-extrabold">Cota em dia</span>
                </div>
                <div v-else class="flex items-center gap-4 rounded-[14px] border border-[#F0C9C2] bg-perigo-claro px-5 py-[18px] text-perigo-texto">
                    <svg class="h-8 w-8 shrink-0 text-perigo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[22px] font-extrabold">{{ atrasoLabel }}</span>
                        <span class="text-base font-semibold">Em dívida: {{ euros(valorEmDivida) }}</span>
                    </div>
                </div>

                <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[14px] border border-linha bg-white">
                    <h2 class="border-b border-linha-fraca px-5 py-3.5 text-base font-extrabold">Últimas cotas</h2>
                    <div v-if="!cotasRecentes.length" class="px-5 py-6 text-base text-suave">Ainda não há cotas registadas.</div>
                    <div v-else class="min-h-0 flex-1 overflow-auto">
                        <table class="w-full border-collapse text-base">
                            <thead>
                                <tr class="text-left text-[13px] uppercase tracking-[.06em] text-suave-2">
                                    <th class="py-2.5 pl-5 pr-2 font-bold">Ano</th>
                                    <th class="hidden px-2 py-2.5 font-bold sm:table-cell">Tipo</th>
                                    <th class="px-2 py-2.5 text-right font-bold">Valor</th>
                                    <th class="px-2 py-2.5 font-bold">Estado</th>
                                    <th class="py-2.5 pl-2 pr-5 font-bold">Método</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="cota in cotasRecentes.slice(0, 12)" :key="cota.id" class="border-t border-linha-fraca">
                                    <td class="py-3 pl-5 pr-2 font-bold">{{ cota.ano }}</td>
                                    <td class="hidden px-2 py-3 sm:table-cell">{{ cota.mes ? 'Mensal' : 'Anual' }}</td>
                                    <td class="px-2 py-3 text-right">{{ euros(cota.valor) }}</td>
                                    <td class="px-2 py-3 font-semibold" :class="cota.estado === 'pago' ? 'text-verde-escuro' : 'text-perigo-texto'">{{ cota.estado === 'pago' ? 'Paga' : cota.estado }}</td>
                                    <td class="py-3 pl-2 pr-5">{{ nomeMetodo(cota.metodo_pagamento) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <form aria-label="Registar pagamento" class="flex min-h-0 flex-col gap-4 border-t border-linha bg-white p-4 sm:px-6 sm:py-5 lg:border-l lg:border-t-0" @submit.prevent="submit">
                <div>
                    <h2 class="text-[22px] font-extrabold">Registar pagamento</h2>
                    <p class="mt-1 text-[15px] text-suave">Cota anual de {{ euros(valorCota) }}. Toque nos anos a pagar.</p>
                </div>

                <div class="grid grid-cols-3 gap-2.5">
                    <button
                        v-for="ano in anos"
                        :key="ano"
                        type="button"
                        class="flex h-[72px] flex-col items-center justify-center gap-0.5 rounded-xl"
                        :class="pagos.has(ano)
                            ? 'cursor-default border border-verde-claro2 bg-verde-claro text-verde-escuro'
                            : selecionados.includes(ano)
                                ? 'border-2 border-laranja bg-laranja text-white'
                                : 'border-2 border-dashed border-laranja bg-white text-tinta'"
                        :disabled="pagos.has(ano)"
                        :aria-pressed="selecionados.includes(ano)"
                        @click="alternar(ano)"
                    >
                        <span class="text-[22px] font-extrabold">{{ ano }}</span>
                        <span class="text-[13px] font-bold">{{ pagos.has(ano) ? 'Paga' : selecionados.includes(ano) ? 'A pagar' : 'Por pagar' }}</span>
                    </button>
                </div>

                <fieldset>
                    <legend class="mb-2 text-sm font-bold text-suave">Método de pagamento</legend>
                    <div class="grid grid-cols-3 gap-2.5">
                        <button
                            v-for="m in metodos"
                            :key="m.id"
                            type="button"
                            class="h-14 rounded-[10px] border border-linha-forte px-1 text-[15px] font-bold sm:text-[17px]"
                            :class="metodo === m.id ? 'bg-escuro text-white' : 'bg-white text-tinta'"
                            :aria-pressed="metodo === m.id"
                            @click="metodo = m.id"
                        >
                            {{ m.label }}
                        </button>
                    </div>
                </fieldset>

                <div v-if="metodo === 'dinheiro'" class="grid gap-2.5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <div>
                        <label for="recebido" class="mb-1.5 block text-sm font-bold text-suave">Valor recebido</label>
                        <input
                            id="recebido"
                            v-model="recebido"
                            inputmode="decimal"
                            class="h-14 w-full rounded-[10px] border border-linha-forte bg-fundo px-4 text-2xl font-extrabold text-tinta focus:border-verde focus:ring-verde"
                            placeholder="Recebido"
                        >
                    </div>
                    <div class="flex gap-2">
                        <button
                            v-for="r in rapidos"
                            :key="r"
                            type="button"
                            class="h-14 flex-1 rounded-[10px] border border-linha-forte bg-white px-3.5 text-[17px] font-bold text-tinta"
                            @click="recebido = String(r)"
                        >
                            {{ r }} €
                        </button>
                    </div>
                </div>

                <div class="mt-auto flex items-baseline justify-between gap-3 rounded-[14px] bg-laranja-claro px-[18px] py-4">
                    <span class="flex flex-col gap-0.5">
                        <span class="text-[15px] font-semibold text-laranja-texto">{{ resumo }}</span>
                        <span v-if="metodo === 'dinheiro'" class="text-lg font-bold text-laranja-texto">Troco {{ euros(troco) }}</span>
                    </span>
                    <span class="text-[40px] font-extrabold text-tinta">{{ euros(total) }}</span>
                </div>

                <AvisoErros :errors="form.errors" class="text-[15px]" />

                <button
                    class="flex h-[72px] items-center justify-center gap-2.5 rounded-xl bg-verde text-[21px] font-extrabold text-white hover:bg-verde-escuro disabled:cursor-not-allowed disabled:bg-[#9AA59F]"
                    :disabled="!selecionados.length || form.processing"
                >
                    <svg class="h-[26px] w-[26px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
                    Registar pagamento
                </button>

                <p v-if="!selecionados.length" class="-mt-1.5 text-center text-sm font-semibold text-suave-2">
                    Não há anos por pagar.
                </p>
            </form>
        </div>
    </div>
</template>
