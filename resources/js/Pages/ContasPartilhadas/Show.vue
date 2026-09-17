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

const euros = (valor) => Number(valor || 0).toLocaleString('pt-PT', {
    style: 'currency',
    currency: 'EUR',
});

const percentagem = (valor) => Number(valor || 0).toLocaleString('pt-PT', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
}) + '%';

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

    <div class="min-h-screen bg-slate-100 px-4 py-6">
        <div class="mx-auto max-w-3xl">
            <header class="mb-6">
                <h1 class="text-2xl font-black text-slate-900">Contas partilhadas do evento</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Receita bruta e divisao pelas associacoes participantes. Cada associacao
                    suporta os seus proprios custos.
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span>Atualizado as {{ atualizadoEm }}</span>
                    <button type="button" class="font-bold text-slate-900 underline" @click="recarregar">
                        Atualizar agora
                    </button>
                </div>
            </header>

            <section class="mb-5 rounded-xl bg-white p-5 shadow-sm">
                <div class="text-sm font-bold text-slate-500">Receita bruta do periodo</div>
                <div class="mt-1 text-4xl font-black text-emerald-700">{{ euros(divisao.receita_bruta) }}</div>
                <form class="mt-4 grid gap-2 sm:grid-cols-[1fr_1fr_auto]" @submit.prevent="filtrar">
                    <input v-model="filtros.data_inicio" type="date" class="rounded-md border-slate-300 text-sm">
                    <input v-model="filtros.data_fim" type="date" class="rounded-md border-slate-300 text-sm">
                    <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white">Filtrar</button>
                </form>
            </section>

            <section class="mb-5 rounded-xl bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-lg font-black">Divisao acordada</h2>

                <div v-if="!divisao.percentagens_ok" class="mb-3 rounded-md bg-amber-50 p-3 text-sm font-bold text-amber-800">
                    As percentagens somam {{ percentagem(divisao.soma_percentagens) }} e nao 100%.
                    Os valores abaixo sao provisorios.
                </div>

                <div v-if="!divisao.linhas.length" class="rounded-md bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">
                    Ainda nao ha associacoes definidas.
                </div>

                <table v-else class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2">Associacao</th>
                            <th class="text-right">Percentagem</th>
                            <th class="text-right">Quota-parte</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="linha in divisao.linhas" :key="linha.id" class="border-t border-slate-100">
                            <td class="py-3 font-bold">
                                {{ linha.nome }}
                                <span v-if="linha.sigla" class="ml-1 text-xs font-normal text-slate-500">({{ linha.sigla }})</span>
                            </td>
                            <td class="text-right">{{ percentagem(linha.percentagem) }}</td>
                            <td class="text-right font-black">{{ euros(linha.valor) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-slate-200">
                            <td class="py-3 font-black">Atribuido</td>
                            <td class="text-right font-bold">{{ percentagem(divisao.soma_percentagens) }}</td>
                            <td class="text-right font-black">{{ euros(divisao.atribuido) }}</td>
                        </tr>
                        <tr v-if="Math.abs(Number(divisao.residuo)) >= 0.01">
                            <td colspan="2" class="py-2 text-xs text-slate-500">Por atribuir / arredondamento</td>
                            <td class="text-right text-xs font-bold text-slate-500">{{ euros(divisao.residuo) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <section class="rounded-xl bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-lg font-black">Origem da receita</h2>
                <div v-for="linha in receitas" :key="linha.categoria + '-' + linha.origem" class="flex justify-between border-t border-slate-100 py-3 text-sm">
                    <span class="font-bold">{{ linha.label }}</span>
                    <strong class="text-emerald-700">{{ euros(linha.valor) }}</strong>
                </div>
            </section>

            <p class="mt-6 text-center text-xs text-slate-400">
                Pagina de consulta. Os valores sao atualizados automaticamente durante o evento.
            </p>
        </div>
    </div>
</template>
