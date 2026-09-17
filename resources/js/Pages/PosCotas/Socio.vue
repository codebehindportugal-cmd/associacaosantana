<script setup>
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
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';

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
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-lg bg-gray-800 p-5">
                <h1 class="text-3xl font-black">{{ socio.nome }}</h1>
                <p class="font-bold text-gray-300">
                    Sócio N.º {{ socio.numero_socio }}
                    <template v-if="socio.morada"> · {{ socio.morada }}</template>
                    <template v-if="socio.telefone"> · {{ socio.telefone }}</template>
                </p>

                <div
                    class="my-5 rounded-lg p-4 text-xl font-black"
                    :class="socio.cota_em_dia ? 'bg-emerald-600' : 'bg-red-600'"
                >
                    <template v-if="socio.cota_em_dia">✅ COTA EM DIA</template>
                    <template v-else>
                        ⚠️ {{ anosEmAtraso }} ANO(S) POR PAGAR — {{ euros(valorEmDivida) }}
                    </template>
                </div>

                <table class="w-full text-left text-sm">
                    <tbody>
                        <tr v-for="cota in cotasRecentes.slice(0, 12)" :key="cota.id" class="border-b border-gray-700">
                            <td class="py-2 font-bold">{{ cota.ano }}</td>
                            <td>{{ cota.mes ? 'Mensal' : 'Anual' }}</td>
                            <td>{{ euros(cota.valor) }}</td>
                            <td>{{ cota.estado === 'pago' ? 'Paga' : cota.estado }}</td>
                            <td>{{ cota.metodo_pagamento }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <form class="rounded-lg bg-gray-800 p-5" @submit.prevent="submit">
                <h2 class="mb-1 text-2xl font-black">REGISTAR PAGAMENTO</h2>
                <p class="mb-4 text-sm font-bold text-gray-400">
                    Cota anual de {{ euros(valorCota) }}. Toca nos anos a pagar.
                </p>

                <div class="grid grid-cols-3 gap-2">
                    <button
                        v-for="ano in anos"
                        :key="ano"
                        type="button"
                        class="rounded p-4 text-lg font-black"
                        :class="pagos.has(ano)
                            ? 'cursor-default bg-emerald-700 text-emerald-200'
                            : selecionados.includes(ano) ? 'bg-red-700' : 'bg-gray-700'"
                        :disabled="pagos.has(ano)"
                        @click="alternar(ano)"
                    >
                        {{ ano }}
                        <span v-if="pagos.has(ano)" class="block text-xs font-bold">paga</span>
                    </button>
                </div>

                <div class="my-4 grid grid-cols-3 gap-2">
                    <button
                        v-for="m in ['dinheiro', 'mbway', 'transferencia']"
                        :key="m"
                        type="button"
                        class="rounded p-3 font-black uppercase"
                        :class="metodo === m ? 'bg-emerald-600' : 'bg-gray-700'"
                        @click="metodo = m"
                    >
                        {{ m }}
                    </button>
                </div>

                <input
                    v-if="metodo === 'dinheiro'"
                    v-model="recebido"
                    class="w-full rounded bg-gray-900 p-3 text-xl font-black text-white"
                    placeholder="Recebido"
                >

                <div class="my-4 text-3xl font-black text-emerald-400">
                    {{ euros(total) }}
                    <span v-if="metodo === 'dinheiro'" class="text-lg">Troco {{ euros(troco) }}</span>
                </div>

                <button
                    class="w-full rounded-lg bg-emerald-600 p-5 text-xl font-black disabled:opacity-40"
                    :disabled="!selecionados.length || form.processing"
                >
                    ✅ REGISTAR PAGAMENTO
                </button>

                <p v-if="!selecionados.length" class="mt-2 text-center text-sm font-bold text-gray-400">
                    Não há anos por pagar.
                </p>

                <Link :href="route('pos.cotas.socio.pesquisa')" class="mt-3 block rounded-lg bg-gray-700 p-4 text-center font-black">
                    ← VOLTAR À PESQUISA
                </Link>
            </form>
        </div>
    </main>
</template>
