<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Paginacao from '@/Components/Paginacao.vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
const props = defineProps({ cotas: Object, totais: Object, socios: Array, filters: Object });

// Vindo da ficha do sócio ("Registar cota"), o sócio já vem escolhido
const socioPedido = (() => {
    if (typeof window === 'undefined') return null;
    const id = Number(new URLSearchParams(window.location.search).get('socio'));
    return (props.socios ?? []).some((socio) => socio.id === id) ? id : null;
})();

// A cota e anual e simbolica: 5€ por ano
const form = useForm({ socio_id: socioPedido ?? props.socios?.[0]?.id ?? '', ano: new Date().getFullYear(), mes: null, tipo: 'anual', valor: 5, data_vencimento: `${new Date().getFullYear()}-12-31`, estado: 'pago', metodo_pagamento: 'dinheiro' });

const podeGerar = computed(
    () => (usePage().props.auth?.permissions ?? []).includes('cotas.gerar'),
);

const erroGerar = ref('');
const gerarCotas = () => {
    const ano = filtros.ano || new Date().getFullYear();

    if (confirm(`Gerar a cota anual de ${ano} (5€) para todos os sócios ativos que ainda não a tenham?`)) {
        erroGerar.value = '';
        router.post(route('cotas.gerar'), { ano }, {
            preserveScroll: true,
            onError: (erros) => { erroGerar.value = Object.values(erros).join(' '); },
        });
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
    pago: 'bg-verde-claro text-verde-escuro',
    pendente: 'bg-laranja-claro text-laranja-texto',
    em_atraso: 'bg-perigo-claro text-perigo-texto',
};

const corFiltro = { '': 'text-tinta', pago: 'text-verde-escuro', pendente: 'text-laranja-texto', em_atraso: 'text-perigo-texto' };

const euros = (valor) => Number(valor ?? 0).toLocaleString('pt-PT', { useGrouping: 'always', minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

const filtrar = () => router.get(route('cotas.index'), {
    ano: filtros.ano || undefined,
    mes: filtros.mes || undefined,
    estado: filtros.estado || undefined,
}, { preserveState: true, preserveScroll: true, replace: true });

const campo = 'h-12 w-full min-w-0 rounded-[10px] border border-linha-forte bg-white px-3 text-base text-tinta focus:border-verde focus:ring-verde';
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-[30px] font-extrabold leading-tight">Cotas</h1>
                    <p class="text-[15px] text-suave">A cota é anual e simbólica: 5 € por ano.</p>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <Link :href="route('socios.emAtraso')" class="inline-flex h-12 items-center gap-2 rounded-[10px] border border-linha-forte bg-white px-[18px] text-[15px] font-bold text-perigo hover:bg-perigo-claro">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                        Sócios em atraso
                    </Link>
                    <button
                        v-if="podeGerar"
                        type="button"
                        class="inline-flex h-12 items-center gap-2 rounded-[10px] bg-escuro-2 px-[18px] text-[15px] font-bold text-white hover:bg-escuro"
                        @click="gerarCotas"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4v6h6M20 20v-6h-6" /><path d="M20 10a8 8 0 0 0-14-4L4 10M4 14a8 8 0 0 0 14 4l2-4" /></svg>
                        Gerar cotas do ano
                    </button>
                </div>
            </div>

            <div v-if="erroGerar" role="alert" class="rounded-[10px] bg-perigo-claro p-3 font-semibold text-perigo-texto">{{ erroGerar }}</div>

            <section aria-label="Totais" class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-[14px] border border-l-[5px] border-linha border-l-verde-ok bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">Cobrado</div>
                    <strong class="text-[30px] font-extrabold">{{ euros(totais?.cobrado) }}</strong>
                </div>
                <div class="rounded-[14px] border border-l-[5px] border-linha border-l-laranja bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">Pendente</div>
                    <strong class="text-[30px] font-extrabold text-laranja-texto">{{ euros(totais?.pendente) }}</strong>
                </div>
            </section>

            <section class="flex flex-col gap-3.5 rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                <div class="flex flex-col gap-0.5">
                    <h2 class="text-[19px] font-extrabold">Registar cota</h2>
                    <span class="text-sm text-suave-2">Escolhe o sócio, confirma o valor e carrega em Registar.</span>
                </div>
                <form class="grid grid-cols-2 items-end gap-2.5 sm:grid-cols-3 xl:grid-cols-[minmax(0,2.2fr)_110px_120px_170px_170px_auto]" @submit.prevent="form.post(route('cotas.store'))">
                    <label class="col-span-full flex flex-col gap-1.5 text-sm font-semibold text-suave xl:col-span-1">
                        Sócio
                        <select v-model="form.socio_id" :class="campo"><option v-for="socio in socios" :key="socio.id" :value="socio.id">{{ socio.numero_socio }} · {{ socio.nome }}<template v-if="socio.morada"> · {{ socio.morada }}</template></option></select>
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                        Ano
                        <input v-model="form.ano" type="number" inputmode="numeric" :class="[campo, 'font-bold']" placeholder="Ano">
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                        Valor (€)
                        <input v-model="form.valor" type="number" step="0.01" inputmode="decimal" :class="[campo, 'font-bold']" placeholder="Valor">
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                        Vencimento
                        <input v-model="form.data_vencimento" type="date" :class="[campo, 'px-2 text-[15px]']">
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                        Estado
                        <select v-model="form.estado" :class="campo"><option value="pago">Paga</option><option value="pendente">Pendente</option><option value="em_atraso">Em atraso</option></select>
                    </label>
                    <button class="col-span-full h-12 rounded-[10px] bg-laranja px-6 text-base font-extrabold text-white hover:brightness-95 disabled:opacity-60 sm:col-span-1" :disabled="form.processing">{{ form.processing ? 'A registar...' : 'Registar' }}</button>
                    <div v-if="Object.keys(form.errors).length" role="alert" class="col-span-full rounded-[10px] bg-perigo-claro px-4 py-3 text-sm font-semibold text-perigo-texto">
                        <div v-for="(erro, campoErro) in form.errors" :key="campoErro">{{ erro }}</div>
                    </div>
                </form>
            </section>

            <div class="flex flex-wrap items-center gap-2.5">
                <label>
                    <span class="sr-only">Ano</span>
                    <select v-model="filtros.ano" class="h-11 rounded-[10px] border border-linha-forte bg-white pl-2.5 pr-8 text-[15px] text-tinta focus:border-verde focus:ring-verde" @change="filtrar"><option value="">Todos os anos</option><option v-for="ano in anos" :key="ano" :value="ano">{{ ano }}</option></select>
                </label>
                <label>
                    <span class="sr-only">Mês</span>
                    <select v-model="filtros.mes" class="h-11 rounded-[10px] border border-linha-forte bg-white pl-2.5 pr-8 text-[15px] text-tinta focus:border-verde focus:ring-verde" @change="filtrar"><option value="">Todos os meses</option><option v-for="mes in 12" :key="mes" :value="mes">{{ mes }}</option></select>
                </label>
                <div role="group" aria-label="Filtrar por estado" class="flex flex-wrap gap-2">
                    <button
                        v-for="opcao in [['', 'Todas'], ['pago', 'Pagas'], ['pendente', 'Pendentes'], ['em_atraso', 'Em atraso']]"
                        :key="opcao[0]"
                        type="button"
                        :aria-pressed="filtros.estado === opcao[0]"
                        class="h-11 rounded-full px-4 text-sm font-bold"
                        :class="filtros.estado === opcao[0] ? 'bg-tinta text-white' : ['border border-linha-forte bg-white hover:bg-fundo', corFiltro[opcao[0]]]"
                        @click="filtros.estado = opcao[0]; filtrar()"
                    >
                        {{ opcao[1] }}
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full border-collapse text-base">
                        <thead>
                            <tr class="text-left text-[13px] text-suave-2">
                                <th scope="col" class="w-[70px] border-b border-linha-fraca px-5 py-3.5 font-semibold">N.º</th>
                                <th scope="col" class="border-b border-linha-fraca px-3 py-3.5 font-semibold">Sócio</th>
                                <th scope="col" class="border-b border-linha-fraca px-3 py-3.5 font-semibold">Terra</th>
                                <th scope="col" class="border-b border-linha-fraca px-3 py-3.5 font-semibold">Período</th>
                                <th scope="col" class="border-b border-linha-fraca px-3 py-3.5 text-right font-semibold">Valor</th>
                                <th scope="col" class="border-b border-linha-fraca px-5 py-3.5 font-semibold">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cota in cotas.data" :key="cota.id" class="border-b border-linha-fraca">
                                <td class="px-5 py-3 font-extrabold text-suave">{{ cota.socio?.numero_socio ?? '—' }}</td>
                                <td class="px-3 py-3 font-bold">
                                    <Link v-if="cota.socio" :href="route('socios.show', cota.socio.id)" class="text-tinta hover:text-verde">{{ cota.socio.nome }}</Link>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-3 py-3 text-suave">{{ cota.socio?.morada || '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3">{{ periodo(cota) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-extrabold">{{ euros(cota.valor) }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex h-7 items-center whitespace-nowrap rounded-full px-3 text-[13px] font-extrabold" :class="corEstado[cota.estado] ?? 'bg-fundo text-suave'">
                                        {{ rotuloEstado[cota.estado] ?? cota.estado }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul class="divide-y divide-linha-fraca md:hidden">
                    <li v-for="cota in cotas.data" :key="cota.id" class="grid grid-cols-[auto_minmax(0,1fr)_auto] gap-x-2.5 gap-y-0.5 px-4 py-3">
                        <span class="row-span-3 pt-0.5 font-extrabold text-suave">{{ cota.socio?.numero_socio ?? '—' }}</span>
                        <span class="truncate font-bold">
                            <Link v-if="cota.socio" :href="route('socios.show', cota.socio.id)" class="text-tinta">{{ cota.socio.nome }}</Link>
                            <template v-else>—</template>
                        </span>
                        <span class="text-right">
                            <span class="inline-flex h-7 items-center whitespace-nowrap rounded-full px-3 text-[13px] font-extrabold" :class="corEstado[cota.estado] ?? 'bg-fundo text-suave'">{{ rotuloEstado[cota.estado] ?? cota.estado }}</span>
                        </span>
                        <span class="truncate text-sm text-suave">{{ cota.socio?.morada || '—' }}</span>
                        <span class="whitespace-nowrap text-right font-extrabold">{{ euros(cota.valor) }}</span>
                        <span class="col-span-2 text-sm text-suave-2">{{ periodo(cota) }}</span>
                    </li>
                </ul>

                <div v-if="!cotas.data.length" class="p-8 text-center text-suave">Não há cotas para estes filtros.</div>

                <Paginacao :dados="cotas" etiqueta="cotas" />
            </div>
        </div>
    </AppLayout>
</template>
