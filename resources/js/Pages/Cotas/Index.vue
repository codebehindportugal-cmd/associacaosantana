<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Paginacao from '@/Components/Paginacao.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
const props = defineProps({ cotas: Object, totais: Object, socios: Array, filters: Object });
// A cota e anual e simbolica: 5€ por ano
const form = useForm({ socio_id: props.socios?.[0]?.id ?? '', ano: new Date().getFullYear(), mes: null, tipo: 'anual', valor: 5, data_vencimento: `${new Date().getFullYear()}-12-31`, estado: 'pago', metodo_pagamento: 'dinheiro' });

const podeGerar = computed(
    () => (usePage().props.auth?.permissions ?? []).includes('cotas.gerar'),
);

const gerarCotas = () => {
    const ano = filtros.ano || new Date().getFullYear();

    if (confirm(`Gerar a cota anual de ${ano} (5€) para todos os sócios ativos que ainda não a tenham?`)) {
        router.post(route('cotas.gerar'), { ano }, { preserveScroll: true });
    }
};

const anoAtual = new Date().getFullYear();
const anos = [anoAtual + 1, anoAtual, anoAtual - 1, anoAtual - 2];
const filtros = reactive({
    ano: props.filters?.ano ?? '',
    mes: props.filters?.mes ?? '',
    estado: props.filters?.estado ?? '',
});
// Cota anual nao tem mes: mostrar "2026/" ficava a meio
const periodo = (cota) => cota.mes ? `${String(cota.mes).padStart(2, '0')}/${cota.ano}` : `Anual ${cota.ano}`;

const rotuloEstado = { pago: 'Paga', pendente: 'Pendente', em_atraso: 'Em atraso' };

const corEstado = {
    pago: 'bg-emerald-100 text-emerald-800',
    pendente: 'bg-amber-100 text-amber-800',
    em_atraso: 'bg-red-100 text-red-800',
};

const filtrar = () => router.get(route('cotas.index'), {
    ano: filtros.ano || undefined,
    mes: filtros.mes || undefined,
    estado: filtros.estado || undefined,
}, { preserveState: true, preserveScroll: true, replace: true });
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold">Cotas</h1>
            <button
                v-if="podeGerar"
                type="button"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white"
                @click="gerarCotas"
            >
                Gerar cotas do ano
            </button>
        </div>
        <div class="mb-6 grid gap-4 md:grid-cols-2"><div class="rounded-lg bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Cobrado</div><div class="text-3xl font-bold">{{ Number(totais?.cobrado ?? 0).toFixed(2) }}€</div></div><div class="rounded-lg bg-white p-5 shadow-sm"><div class="text-sm text-slate-500">Pendente</div><div class="text-3xl font-bold">{{ Number(totais?.pendente ?? 0).toFixed(2) }}€</div></div></div>
        <form class="mb-6 grid gap-3 rounded-lg bg-white p-5 shadow-sm md:grid-cols-4" @submit.prevent="form.post(route('cotas.store'))">
            <select v-model="form.socio_id" class="rounded-md border-slate-300"><option v-for="socio in socios" :key="socio.id" :value="socio.id">{{ socio.numero_socio }} · {{ socio.nome }}<template v-if="socio.morada"> · {{ socio.morada }}</template></option></select>
            <input v-model="form.ano" type="number" class="rounded-md border-slate-300" placeholder="Ano">
            <input v-model="form.valor" type="number" step="0.01" class="rounded-md border-slate-300" placeholder="Valor">
            <input v-model="form.data_vencimento" type="date" class="rounded-md border-slate-300">
            <select v-model="form.estado" class="rounded-md border-slate-300"><option>pago</option><option>pendente</option><option>em_atraso</option></select>
            <button class="rounded-md bg-slate-900 px-4 py-2 text-white" :disabled="form.processing">{{ form.processing ? 'A registar...' : 'Registar' }}</button>
            <div v-if="Object.keys(form.errors).length" class="col-span-full rounded-md bg-red-50 p-3 text-sm text-red-700">
                <div v-for="(erro, campo) in form.errors" :key="campo">{{ erro }}</div>
            </div>
        </form>
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <select v-model="filtros.ano" class="rounded-md border-slate-300 text-sm" @change="filtrar"><option value="">Todos os anos</option><option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option></select>
            <select v-model="filtros.mes" class="rounded-md border-slate-300 text-sm" @change="filtrar"><option value="">Todos os meses</option><option v-for="mes in 12" :key="mes" :value="mes">{{ mes }}</option></select>
            <button
                v-for="opcao in [['', 'Todas'], ['pago', 'Pagas'], ['pendente', 'Pendentes'], ['em_atraso', 'Em atraso']]"
                :key="opcao[0]"
                type="button"
                class="rounded-md border px-3 py-2 text-sm font-bold"
                :class="filtros.estado === opcao[0] ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700'"
                @click="filtros.estado = opcao[0]; filtrar()"
            >
                {{ opcao[1] }}
            </button>
        </div>
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="p-3">N.º</th>
                        <th class="p-3">Sócio</th>
                        <th class="p-3">Terra</th>
                        <th class="p-3">Período</th>
                        <th class="p-3 text-right">Valor</th>
                        <th class="p-3">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="cota in cotas.data" :key="cota.id" class="border-t border-slate-100">
                        <td class="p-3 font-mono font-bold text-slate-700">{{ cota.socio?.numero_socio ?? '—' }}</td>
                        <td class="p-3">{{ cota.socio?.nome }}</td>
                        <td class="p-3 text-slate-600">{{ cota.socio?.morada || '—' }}</td>
                        <td class="p-3 whitespace-nowrap">{{ periodo(cota) }}</td>
                        <td class="p-3 text-right font-bold">{{ Number(cota.valor).toFixed(2) }}€</td>
                        <td class="p-3">
                            <span class="rounded-full px-2 py-1 text-xs font-bold" :class="corEstado[cota.estado] ?? 'bg-slate-100 text-slate-700'">
                                {{ rotuloEstado[cota.estado] ?? cota.estado }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!cotas.data.length">
                        <td colspan="6" class="p-6 text-center text-slate-500">Não há cotas para estes filtros.</td>
                    </tr>
                </tbody>
            </table>
            <Paginacao :dados="cotas" etiqueta="cotas" />
        </div>
    </AppLayout>
</template>
