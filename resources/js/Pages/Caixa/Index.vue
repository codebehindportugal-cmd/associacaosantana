<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    data: String,
    pontos_padrao: Array,
    caixas: Array,
});

const form = useForm({
    ponto: props.pontos_padrao?.[0] ?? 'Restaurante',
    fundo_maneio: 0,
});

const fecharForm = useForm({
    valor_contado: 0,
    observacoes_fecho: '',
});

const caixaAFechar = ref(null);
const caixasPorPonto = computed(() => Object.fromEntries((props.caixas ?? []).map((caixa) => [caixa.ponto, caixa])));
const restaurante = computed(() => caixasPorPonto.value.Restaurante ?? null);
const pontosBar = computed(() => (props.pontos_padrao ?? []).filter((ponto) => ponto !== 'Restaurante'));
const totalFundo = computed(() => (props.caixas ?? []).reduce((total, caixa) => total + Number(caixa.fundo_maneio || 0), 0));
const totalVendas = computed(() => (props.caixas ?? []).reduce((total, caixa) => total + Number(caixa.vendas || 0), 0));
const totalEsperado = computed(() => (props.caixas ?? []).reduce((total, caixa) => total + Number(caixa.esperado_caixa || 0), 0));
const totalContado = computed(() => (props.caixas ?? []).reduce((total, caixa) => total + Number(caixa.valor_contado || 0), 0));
const euros = (valor) => Number(valor ?? 0).toFixed(2) + '€';
const hora = (data) => data ? new Date(data).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' }) : '';
const diferencaClass = (valor) => Number(valor || 0) === 0 ? 'text-slate-700' : Number(valor) > 0 ? 'text-emerald-700' : 'text-red-700';
const estadoLabel = (caixa) => !caixa ? 'FALTA ABRIR' : caixa.estado === 'fechada' ? 'FECHADA' : 'ABERTA';
const estadoClasses = (caixa) => !caixa ? 'bg-amber-100 text-amber-800' : caixa.estado === 'fechada' ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-800';

const prepararAbertura = (ponto) => {
    form.ponto = ponto;
    form.fundo_maneio = caixasPorPonto.value[ponto]?.fundo_maneio ?? 0;
};

const abrirCaixa = () => {
    form.post(route('caixa.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('fundo_maneio'),
    });
};

const prepararFecho = (caixa) => {
    caixaAFechar.value = caixa.id;
    fecharForm.valor_contado = Number(caixa.esperado_caixa || 0).toFixed(2);
    fecharForm.observacoes_fecho = '';
};

const cancelarFecho = () => {
    caixaAFechar.value = null;
    fecharForm.reset();
};

const fecharCaixa = (caixa) => {
    fecharForm.patch(route('caixa.fechar', caixa.id), {
        preserveScroll: true,
        onSuccess: cancelarFecho,
    });
};
</script>

<template>
    <AppLayout>
        <div class="mb-6 overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-sm lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-black uppercase tracking-[0.3em] text-amber-300">{{ data }}</p>
                    <h1 class="mt-3 text-4xl font-black tracking-tight">Caixas</h1>
                    <p class="mt-2 max-w-2xl text-sm font-semibold text-slate-300">Abre o Restaurante para trabalhar contas de mesa. Abre os cafés/bares para vender por senha e controlar trocos.</p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-right text-sm md:grid-cols-4">
                    <div class="rounded-2xl bg-white/10 p-3"><div class="text-slate-300">Fundo</div><strong class="text-lg">{{ euros(totalFundo) }}</strong></div>
                    <div class="rounded-2xl bg-white/10 p-3"><div class="text-slate-300">Vendas</div><strong class="text-lg">{{ euros(totalVendas) }}</strong></div>
                    <div class="rounded-2xl bg-emerald-400 p-3 text-emerald-950"><div>Esperado</div><strong class="text-lg">{{ euros(totalEsperado) }}</strong></div>
                    <div class="rounded-2xl bg-white p-3 text-slate-950"><div>Contado</div><strong class="text-lg">{{ euros(totalContado) }}</strong></div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <div class="space-y-6">
                <section class="overflow-hidden rounded-[2rem] bg-white shadow-sm ring-1 ring-slate-200">
                    <div class="bg-gradient-to-r from-slate-900 to-slate-700 p-5 text-white">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[0.25em] text-amber-300">Restaurante</p>
                                <h2 class="mt-2 text-3xl font-black">Contas de mesa</h2>
                                <p class="mt-1 text-sm text-slate-300">Usa esta caixa para abrir mesas, receber contas e fechar o dia do restaurante.</p>
                            </div>
                            <span class="rounded-full px-4 py-2 text-xs font-black" :class="estadoClasses(restaurante)">{{ estadoLabel(restaurante) }}</span>
                        </div>
                    </div>

                    <div class="grid gap-4 p-5 lg:grid-cols-[1fr_280px]">
                        <div v-if="restaurante" class="grid gap-3 sm:grid-cols-4">
                            <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs font-bold uppercase text-slate-500">Fundo</div><strong class="text-2xl">{{ euros(restaurante.fundo_maneio) }}</strong></div>
                            <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs font-bold uppercase text-slate-500">Vendas</div><strong class="text-2xl">{{ euros(restaurante.vendas) }}</strong></div>
                            <div class="rounded-2xl bg-emerald-50 p-4"><div class="text-xs font-bold uppercase text-emerald-700">Esperado</div><strong class="text-2xl text-emerald-800">{{ euros(restaurante.esperado_caixa) }}</strong></div>
                            <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs font-bold uppercase text-slate-500">Pedidos</div><strong class="text-2xl">{{ restaurante.pedidos }}</strong></div>
                        </div>
                        <div v-else class="rounded-2xl bg-amber-50 p-5 font-bold text-amber-800">Abre primeiro a caixa do Restaurante para poderes abrir contas de mesa.</div>

                        <div class="grid gap-2">
                            <button type="button" class="rounded-2xl border border-slate-300 px-4 py-3 font-black" @click="prepararAbertura('Restaurante')">{{ restaurante ? 'Reabrir / ajustar fundo' : 'Abrir Restaurante' }}</button>
                            <Link :href="route('mesas.index')" class="rounded-2xl bg-slate-900 px-4 py-3 text-center font-black text-white">Ir para mesas</Link>
                            <Link :href="route('pedidos.index')" class="rounded-2xl bg-white px-4 py-3 text-center font-black text-slate-900 ring-1 ring-slate-300">Ver contas</Link>
                        </div>
                    </div>

                    <div v-if="restaurante?.estado === 'fechada'" class="mx-5 mb-5 rounded-2xl bg-slate-50 p-4 text-sm">
                        <div class="flex justify-between"><span>Contado</span><strong>{{ euros(restaurante.valor_contado) }}</strong></div>
                        <div class="flex justify-between"><span>Diferença</span><strong :class="diferencaClass(restaurante.diferenca)">{{ euros(restaurante.diferenca) }}</strong></div>
                    </div>

                    <div v-if="restaurante?.estado === 'aberta' && caixaAFechar !== restaurante.id" class="border-t border-slate-100 p-5">
                        <button type="button" class="w-full rounded-2xl bg-red-600 px-4 py-4 font-black text-white" @click="prepararFecho(restaurante)">Fechar Restaurante</button>
                    </div>

                    <form v-if="caixaAFechar === restaurante?.id" class="border-t border-slate-100 bg-slate-50 p-5" @submit.prevent="fecharCaixa(restaurante)">
                        <div class="grid gap-3 md:grid-cols-[180px_1fr_auto_auto] md:items-end">
                            <label class="block text-sm font-bold text-slate-600">Valor contado
                                <input v-model.number="fecharForm.valor_contado" type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border-slate-300 text-xl font-black">
                            </label>
                            <label class="block text-sm font-bold text-slate-600">Observações
                                <input v-model="fecharForm.observacoes_fecho" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Opcional">
                            </label>
                            <button type="button" class="rounded-xl border border-slate-300 px-4 py-3 font-bold" @click="cancelarFecho">Cancelar</button>
                            <button class="rounded-xl bg-red-600 px-4 py-3 font-black text-white disabled:opacity-50" :disabled="fecharForm.processing">Confirmar fecho</button>
                        </div>
                    </form>
                </section>

                <section class="rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.25em] text-emerald-700">Senhas impressas</p>
                            <h2 class="text-2xl font-black">Cafés e bares</h2>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <article v-for="ponto in pontosBar" :key="ponto" class="rounded-3xl border p-4" :class="caixasPorPonto[ponto] ? caixasPorPonto[ponto].estado === 'fechada' ? 'border-slate-200 bg-slate-50' : 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-xl font-black">{{ ponto }}</h3>
                                    <p v-if="caixasPorPonto[ponto]?.estado === 'fechada'" class="text-sm font-bold text-slate-600">Fechado às {{ hora(caixasPorPonto[ponto].fechado_as) }}</p>
                                    <p v-else-if="caixasPorPonto[ponto]" class="text-sm font-bold text-emerald-700">Aberto às {{ hora(caixasPorPonto[ponto].aberto_as) }}</p>
                                    <p v-else class="text-sm font-bold text-amber-700">Ainda não aberto</p>
                                </div>
                                <span class="rounded-full px-3 py-1 text-xs font-black" :class="estadoClasses(caixasPorPonto[ponto])">{{ estadoLabel(caixasPorPonto[ponto]) }}</span>
                            </div>

                            <div v-if="caixasPorPonto[ponto]" class="mt-4 grid grid-cols-3 gap-2 text-sm">
                                <div class="rounded-xl bg-white/80 p-3"><div class="text-slate-500">Fundo</div><strong>{{ euros(caixasPorPonto[ponto].fundo_maneio) }}</strong></div>
                                <div class="rounded-xl bg-white/80 p-3"><div class="text-slate-500">Vendas</div><strong>{{ euros(caixasPorPonto[ponto].vendas) }}</strong></div>
                                <div class="rounded-xl bg-white/80 p-3"><div class="text-slate-500">Esperado</div><strong>{{ euros(caixasPorPonto[ponto].esperado_caixa) }}</strong></div>
                            </div>

                            <div v-if="caixasPorPonto[ponto]?.estado === 'fechada'" class="mt-3 rounded-xl bg-white/80 p-3 text-sm">
                                <div class="flex justify-between"><span>Contado</span><strong>{{ euros(caixasPorPonto[ponto].valor_contado) }}</strong></div>
                                <div class="flex justify-between"><span>Diferença</span><strong :class="diferencaClass(caixasPorPonto[ponto].diferenca)">{{ euros(caixasPorPonto[ponto].diferenca) }}</strong></div>
                            </div>

                            <div class="mt-4 grid gap-2" :class="caixasPorPonto[ponto]?.estado === 'aberta' ? 'grid-cols-3' : 'grid-cols-2'">
                                <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 font-bold" @click="prepararAbertura(ponto)">{{ caixasPorPonto[ponto] ? 'Ajustar' : 'Abrir' }}</button>
                                <Link v-if="caixasPorPonto[ponto]?.estado === 'aberta'" :href="route('bar.index', { ponto })" class="rounded-xl bg-emerald-600 px-3 py-2 text-center font-black text-white">Vender senhas</Link>
                                <button v-if="caixasPorPonto[ponto]?.estado === 'aberta' && caixaAFechar !== caixasPorPonto[ponto].id" type="button" class="rounded-xl bg-slate-900 px-3 py-2 font-black text-white" @click="prepararFecho(caixasPorPonto[ponto])">Fechar</button>
                            </div>

                            <form v-if="caixaAFechar === caixasPorPonto[ponto]?.id" class="mt-4 rounded-xl bg-white p-3" @submit.prevent="fecharCaixa(caixasPorPonto[ponto])">
                                <label class="block text-sm font-bold text-slate-600">Valor contado
                                    <input v-model.number="fecharForm.valor_contado" type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border-slate-300 text-xl font-black">
                                </label>
                                <label class="mt-3 block text-sm font-bold text-slate-600">Observações
                                    <textarea v-model="fecharForm.observacoes_fecho" class="mt-1 w-full rounded-xl border-slate-300" rows="2" placeholder="Opcional"></textarea>
                                </label>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button type="button" class="rounded-xl border border-slate-300 px-3 py-2 font-bold" @click="cancelarFecho">Cancelar</button>
                                    <button class="rounded-xl bg-red-600 px-3 py-2 font-black text-white disabled:opacity-50" :disabled="fecharForm.processing">Confirmar</button>
                                </div>
                            </form>
                        </article>
                    </div>
                </section>
            </div>

            <form class="sticky top-6 self-start rounded-[2rem] bg-white p-5 shadow-sm ring-1 ring-slate-200" @submit.prevent="abrirCaixa">
                <p class="text-xs font-black uppercase tracking-[0.25em] text-slate-500">Abertura</p>
                <h2 class="mt-2 text-2xl font-black">Abrir / reabrir caixa</h2>
                <label class="mt-5 block text-sm font-bold text-slate-600">Ponto
                    <input v-model="form.ponto" list="pontos-caixa" class="mt-1 w-full rounded-xl border-slate-300 text-lg font-black">
                </label>
                <datalist id="pontos-caixa"><option v-for="ponto in pontos_padrao" :key="ponto" :value="ponto" /></datalist>
                <label class="mt-4 block text-sm font-bold text-slate-600">Fundo de maneio
                    <input v-model.number="form.fundo_maneio" type="number" min="0" step="0.01" class="mt-1 w-full rounded-xl border-slate-300 text-2xl font-black" placeholder="0.00">
                </label>
                <div v-if="Object.keys(form.errors).length" class="mt-4 rounded-xl bg-red-50 p-3 text-sm font-bold text-red-700">
                    <div v-for="erro in form.errors" :key="erro">{{ erro }}</div>
                </div>
                <button class="mt-5 w-full rounded-2xl bg-slate-900 p-4 font-black text-white disabled:opacity-50" :disabled="form.processing">
                    {{ form.processing ? 'A guardar...' : 'Guardar abertura' }}
                </button>
                <p class="mt-3 text-xs text-slate-500">Se uma caixa estiver fechada, reabrir limpa o fecho e permite continuar a trabalhar nesse ponto.</p>
            </form>
        </div>
    </AppLayout>
</template>
