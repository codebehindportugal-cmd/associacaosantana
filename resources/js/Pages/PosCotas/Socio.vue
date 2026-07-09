<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps({ socio: Object, cotasRecentes: Array, mesesEmAtraso: Number, valorEmDivida: Number });
const tipo = ref('mensal');
const ano = ref(new Date().getFullYear());
const metodo = ref('dinheiro');
const recebido = ref('');
const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
const pagos = computed(() => new Set((props.cotasRecentes ?? []).filter(c => c.estado === 'pago' && c.ano === ano.value && c.mes).map(c => c.mes)));
const selecionados = ref(Array.from({ length: new Date().getMonth() + 1 }, (_, i) => i + 1).filter(m => !pagos.value.has(m)));
const total = computed(() => tipo.value === 'anual' ? 50 : selecionados.value.length * 5);
const troco = computed(() => Math.max(0, Number(recebido.value || total.value) - total.value));
const form = useForm({ tipo: 'mensal', meses: [], ano: ano.value, valor: 0, metodo_pagamento: 'dinheiro', valor_recebido: null });
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const submit = () => { form.tipo = tipo.value; form.meses = selecionados.value; form.ano = ano.value; form.valor = total.value; form.metodo_pagamento = metodo.value; form.valor_recebido = recebido.value || total.value; form.post(route('pos.cotas.pagar', props.socio.id)); };
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-lg bg-gray-800 p-5"><h1 class="text-3xl font-black">{{ socio.nome }}</h1><p class="font-bold text-gray-300">Sócio N.º {{ socio.numero_socio }} · {{ socio.telefone }} · {{ socio.email }}</p><div class="my-5 rounded-lg p-4 text-xl font-black" :class="socio.cota_em_dia ? 'bg-emerald-600' : 'bg-red-600'">{{ socio.cota_em_dia ? '✅ COTA EM DIA' : `⚠️ ${mesesEmAtraso} MESES EM ATRASO - ${euros(valorEmDivida)} em dívida` }}</div><table class="w-full text-left text-sm"><tbody><tr v-for="cota in cotasRecentes.slice(0,12)" :key="cota.id" class="border-b border-gray-700"><td class="py-2">{{ cota.mes ? meses[cota.mes - 1] : 'Anual' }}/{{ cota.ano }}</td><td>{{ cota.tipo }}</td><td>{{ euros(cota.valor) }}</td><td>{{ cota.estado }}</td><td>{{ cota.metodo_pagamento }}</td></tr></tbody></table></section>
            <form class="rounded-lg bg-gray-800 p-5" @submit.prevent="submit"><h2 class="mb-4 text-2xl font-black">REGISTAR PAGAMENTO</h2><div class="mb-4 grid grid-cols-2 gap-2"><button type="button" class="rounded p-4 font-black" :class="tipo === 'mensal' ? 'bg-blue-600' : 'bg-gray-700'" @click="tipo = 'mensal'">MENSAL</button><button type="button" class="rounded p-4 font-black" :class="tipo === 'anual' ? 'bg-blue-600' : 'bg-gray-700'" @click="tipo = 'anual'">ANUAL</button></div><input v-model.number="ano" type="number" class="mb-4 w-full rounded bg-gray-900 p-3 font-black text-white">
                <div v-if="tipo === 'mensal'" class="grid grid-cols-3 gap-2"><label v-for="(nome, i) in meses" :key="nome" class="rounded p-3 text-center font-black" :class="pagos.has(i + 1) ? 'bg-emerald-700' : selecionados.includes(i + 1) ? 'bg-red-700' : 'bg-gray-700'"><input v-model="selecionados" class="hidden" type="checkbox" :value="i + 1" :disabled="pagos.has(i + 1)">{{ nome }}</label></div>
                <div class="my-4 grid grid-cols-3 gap-2"><button v-for="m in ['dinheiro','mbway','transferencia']" :key="m" type="button" class="rounded p-3 font-black uppercase" :class="metodo === m ? 'bg-emerald-600' : 'bg-gray-700'" @click="metodo = m">{{ m }}</button></div><input v-if="metodo === 'dinheiro'" v-model="recebido" class="w-full rounded bg-gray-900 p-3 text-xl font-black text-white" placeholder="Recebido"><div class="my-4 text-3xl font-black text-emerald-400">{{ euros(total) }} <span v-if="metodo === 'dinheiro'" class="text-lg">Troco {{ euros(troco) }}</span></div><button class="w-full rounded-lg bg-emerald-600 p-5 text-xl font-black">✅ REGISTAR PAGAMENTO</button><Link :href="route('pos.cotas.socio.pesquisa')" class="mt-3 block rounded-lg bg-gray-700 p-4 text-center font-black">← VOLTAR À PESQUISA</Link></form>
        </div>
    </main>
</template>
