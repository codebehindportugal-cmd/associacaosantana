<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';

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
const euros = (valor) => Number(valor ?? 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const hora = (data) => data ? new Date(data).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' }) : '';
const diferencaClass = (valor) => Number(valor || 0) === 0 ? 'text-tinta' : Number(valor) > 0 ? 'text-verde' : 'text-perigo';
const estadoLabel = (caixa) => !caixa ? 'FALTA ABRIR' : caixa.estado === 'fechada' ? 'FECHADA' : 'ABERTA';
const estadoClasses = (caixa) => !caixa ? 'bg-white text-laranja-texto' : caixa.estado === 'fechada' ? 'bg-linha-fraca text-suave' : 'bg-verde-claro2 text-verde-escuro';

// Formulário de abertura: em ecrãs estreitos fica no fim da página
const formAbertura = ref(null);
const dataExtenso = computed(() => {
    if (!props.data) return '';
    const texto = new Date(`${String(props.data).split('T')[0]}T00:00:00`).toLocaleDateString('pt-PT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    return texto.charAt(0).toUpperCase() + texto.slice(1);
});
const temFechadas = computed(() => (props.caixas ?? []).some((caixa) => caixa.estado === 'fechada'));

const prepararAbertura = (ponto) => {
    form.ponto = ponto;
    form.fundo_maneio = caixasPorPonto.value[ponto]?.fundo_maneio ?? 0;
    nextTick(() => formAbertura.value?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
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
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-col gap-1.5">
                <span class="text-sm font-bold text-suave-2">{{ dataExtenso || data }}</span>
                <h1 class="text-[30px] font-extrabold leading-tight">Caixa diária</h1>
                <p class="max-w-[640px] text-[15px] text-suave">{{ pontosBar.length ? 'Abre o Restaurante para trabalhar contas de mesa. Abre os cafés/bares para vender por senha e controlar trocos.' : 'Abre o Restaurante para trabalhar contas de mesa e controlar o fecho de caixa.' }}</p>
            </div>

            <section aria-label="Totais do dia" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="flex flex-col gap-1 rounded-[14px] border border-linha bg-white px-[18px] py-4">
                    <span class="text-sm font-semibold text-suave-2">Fundo de maneio</span>
                    <strong class="text-[22px] font-extrabold sm:text-[26px]">{{ euros(totalFundo) }}</strong>
                </div>
                <div class="flex flex-col gap-1 rounded-[14px] border border-linha bg-white px-[18px] py-4">
                    <span class="text-sm font-semibold text-suave-2">Vendas</span>
                    <strong class="text-[22px] font-extrabold sm:text-[26px]">{{ euros(totalVendas) }}</strong>
                </div>
                <div class="flex flex-col gap-1 rounded-[14px] border border-verde-claro2 bg-verde-claro px-[18px] py-4 text-verde-escuro">
                    <span class="text-sm font-bold">Esperado em caixa</span>
                    <strong class="text-[22px] font-extrabold sm:text-[26px]">{{ euros(totalEsperado) }}</strong>
                </div>
                <div class="flex flex-col gap-1 rounded-[14px] border border-linha bg-white px-[18px] py-4">
                    <span class="text-sm font-semibold text-suave-2">Contado</span>
                    <strong class="text-[22px] font-extrabold sm:text-[26px]">{{ euros(totalContado) }}</strong>
                    <span v-if="temFechadas" class="text-[13px] text-suave-2">só caixas já fechadas</span>
                </div>
            </section>

            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div class="flex min-w-0 flex-col gap-5">
                    <!-- Restaurante -->
                    <section class="overflow-hidden rounded-[14px] border border-linha bg-white">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-linha-fraca p-5">
                            <div class="flex flex-col gap-1">
                                <span class="text-[13px] font-bold uppercase tracking-[.08em] text-verde">Restaurante</span>
                                <h2 class="text-[22px] font-extrabold">Contas de mesa</h2>
                                <p class="text-[15px] text-suave">Usa esta caixa para abrir mesas, receber contas e fechar o dia do restaurante.</p>
                            </div>
                            <span class="inline-flex h-8 items-center gap-2 whitespace-nowrap rounded-full px-3.5 text-[13px] font-extrabold" :class="restaurante ? estadoClasses(restaurante) : 'bg-laranja-claro text-laranja-texto'">
                                <span class="h-2 w-2 rounded-full" :class="!restaurante ? 'bg-laranja' : restaurante.estado === 'fechada' ? 'bg-suave-2' : 'bg-verde-ok'" />
                                <template v-if="!restaurante">Falta abrir</template>
                                <template v-else-if="restaurante.estado === 'fechada'">Fechada{{ restaurante.fechado_as ? ` às ${hora(restaurante.fechado_as)}` : '' }}</template>
                                <template v-else>Aberta{{ restaurante.aberto_as ? ` às ${hora(restaurante.aberto_as)}` : '' }}</template>
                            </span>
                        </div>

                        <div v-if="restaurante" class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-4">
                            <div class="rounded-[10px] bg-fundo p-3.5"><div class="text-[13px] font-semibold text-suave-2">Fundo</div><strong class="text-[22px]">{{ euros(restaurante.fundo_maneio) }}</strong></div>
                            <div class="rounded-[10px] bg-fundo p-3.5"><div class="text-[13px] font-semibold text-suave-2">Vendas</div><strong class="text-[22px]">{{ euros(restaurante.vendas) }}</strong></div>
                            <div class="rounded-[10px] bg-verde-claro p-3.5 text-verde-escuro"><div class="text-[13px] font-bold">Esperado</div><strong class="text-[22px]">{{ euros(restaurante.esperado_caixa) }}</strong></div>
                            <div class="rounded-[10px] bg-fundo p-3.5"><div class="text-[13px] font-semibold text-suave-2">Pedidos</div><strong class="text-[22px]">{{ restaurante.pedidos }}</strong></div>
                        </div>
                        <div v-else class="m-5 rounded-[10px] bg-laranja-claro p-4 font-bold text-laranja-texto">Abre primeiro a caixa do Restaurante para poderes abrir contas de mesa.</div>

                        <div v-if="restaurante?.estado === 'fechada'" class="mx-5 mb-5 flex flex-col gap-1 border-t border-linha-fraca pt-3 text-[15px]">
                            <div class="flex justify-between"><span>Contado</span><strong>{{ euros(restaurante.valor_contado) }}</strong></div>
                            <div class="flex justify-between"><span>Diferença</span><strong :class="diferencaClass(restaurante.diferenca)">{{ euros(restaurante.diferenca) }}</strong></div>
                        </div>

                        <div class="flex flex-wrap gap-2.5 px-5 pb-5">
                            <Link :href="route('mesas.index')" class="inline-flex h-[52px] flex-[1_1_160px] items-center justify-center gap-2 rounded-[10px] bg-verde text-base font-bold text-white hover:bg-verde-escuro">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="4" rx="1" /><path d="M6 10v9M18 10v9" /></svg>
                                Ir para mesas
                            </Link>
                            <Link :href="route('pedidos.create', { para_levar: 1 })" class="inline-flex h-[52px] flex-[1_1_160px] items-center justify-center rounded-[10px] border border-linha-forte bg-white text-base font-bold text-tinta hover:bg-fundo">Pedido para levar</Link>
                            <Link :href="route('pedidos.index')" class="inline-flex h-[52px] flex-[1_1_160px] items-center justify-center rounded-[10px] border border-linha-forte bg-white text-base font-bold text-tinta hover:bg-fundo">Ver contas</Link>
                            <button type="button" class="h-[52px] flex-[1_1_160px] whitespace-nowrap rounded-[10px] border border-linha-forte px-2.5 text-[15px] font-bold" :class="restaurante ? 'bg-transparent text-suave hover:bg-fundo' : 'border-transparent bg-verde text-white hover:bg-verde-escuro'" @click="prepararAbertura('Restaurante')">{{ restaurante ? 'Reabrir / ajustar fundo' : 'Abrir Restaurante' }}</button>
                        </div>

                        <div v-if="restaurante?.estado === 'aberta' && caixaAFechar !== restaurante.id" class="border-t border-linha-fraca p-5">
                            <button type="button" class="inline-flex h-[52px] w-full items-center justify-center gap-2 rounded-[10px] bg-perigo text-base font-extrabold text-white hover:brightness-95" @click="prepararFecho(restaurante)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                                Fechar Restaurante
                            </button>
                        </div>

                        <form v-if="restaurante && caixaAFechar === restaurante.id" class="flex flex-col gap-3.5 border-t border-[#F0D3CD] bg-perigo-claro p-5" @submit.prevent="fecharCaixa(restaurante)">
                            <div class="flex items-center gap-2.5 text-[17px] font-extrabold text-perigo-texto">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                                Fechar Restaurante
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Valor contado
                                    <span class="flex h-14 items-center rounded-[10px] border border-linha-forte bg-white px-3.5 focus-within:border-verde">
                                        <input v-model.number="fecharForm.valor_contado" type="number" min="0" step="0.01" inputmode="decimal" class="w-full min-w-0 border-0 bg-transparent p-0 text-2xl font-extrabold text-tinta focus:ring-0">
                                        <span class="font-bold text-suave-2">€</span>
                                    </span>
                                </label>
                                <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Observações
                                    <input v-model="fecharForm.observacoes_fecho" class="h-14 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta focus:border-verde focus:ring-verde" placeholder="Opcional">
                                </label>
                            </div>
                            <p class="text-sm text-perigo-texto">Esperado: <strong>{{ euros(restaurante.esperado_caixa) }}</strong>. A diferença é calculada ao confirmar.</p>
                            <div v-if="Object.keys(fecharForm.errors).length" role="alert" class="rounded-[10px] bg-white p-3 text-sm font-semibold text-perigo-texto">
                                <div v-for="erro in fecharForm.errors" :key="erro">{{ erro }}</div>
                            </div>
                            <div class="flex flex-wrap justify-end gap-2.5">
                                <button type="button" class="h-12 rounded-[10px] border border-linha-forte bg-white px-5 text-[15px] font-bold text-tinta" @click="cancelarFecho">Cancelar</button>
                                <button class="h-12 rounded-[10px] bg-perigo px-6 text-[15px] font-extrabold text-white disabled:opacity-50" :disabled="fecharForm.processing">Confirmar fecho</button>
                            </div>
                        </form>
                    </section>

                    <!-- Cafés e bares -->
                    <section v-if="pontosBar.length" class="flex flex-col gap-3">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[13px] font-bold uppercase tracking-[.08em] text-azul">Senhas impressas</span>
                            <h2 class="text-[22px] font-extrabold">Cafés e bares</h2>
                        </div>
                        <div class="grid gap-3 md:grid-cols-2">
                            <article
                                v-for="ponto in pontosBar"
                                :key="ponto"
                                class="flex flex-col gap-3.5 rounded-[14px] p-[18px]"
                                :class="!caixasPorPonto[ponto]
                                    ? 'border border-dashed border-[#E3B58F] bg-laranja-claro'
                                    : caixasPorPonto[ponto].estado === 'fechada'
                                        ? 'border border-l-[5px] border-linha border-l-[#8E9A94] bg-white'
                                        : 'border border-l-[5px] border-linha border-l-verde-ok bg-white'"
                            >
                                <div class="flex items-start justify-between gap-2.5">
                                    <div class="min-w-0">
                                        <h3 class="break-words text-[19px] font-extrabold">{{ ponto }}</h3>
                                        <p v-if="caixasPorPonto[ponto]?.estado === 'fechada'" class="mt-0.5 text-sm font-semibold text-suave-2">Fechado às {{ hora(caixasPorPonto[ponto].fechado_as) }}</p>
                                        <p v-else-if="caixasPorPonto[ponto]" class="mt-0.5 text-sm font-semibold text-verde">Aberto às {{ hora(caixasPorPonto[ponto].aberto_as) }}</p>
                                        <p v-else class="mt-0.5 text-sm font-semibold text-laranja-texto">Ainda não aberto</p>
                                    </div>
                                    <span class="inline-flex h-7 shrink-0 items-center rounded-full px-3 text-xs font-extrabold" :class="estadoClasses(caixasPorPonto[ponto])">{{ estadoLabel(caixasPorPonto[ponto]) }}</span>
                                </div>

                                <div v-if="caixasPorPonto[ponto]" class="grid grid-cols-3 gap-2 text-sm">
                                    <div class="min-w-0 rounded-[10px] bg-fundo p-2.5"><div class="text-suave-2">Fundo</div><strong class="break-words">{{ euros(caixasPorPonto[ponto].fundo_maneio) }}</strong></div>
                                    <div class="min-w-0 rounded-[10px] bg-fundo p-2.5"><div class="text-suave-2">Vendas</div><strong class="break-words">{{ euros(caixasPorPonto[ponto].vendas) }}</strong></div>
                                    <div class="min-w-0 rounded-[10px] p-2.5" :class="caixasPorPonto[ponto].estado === 'aberta' ? 'bg-verde-claro text-verde-escuro' : 'bg-fundo'"><div :class="caixasPorPonto[ponto].estado === 'aberta' ? '' : 'text-suave-2'">Esperado</div><strong class="break-words">{{ euros(caixasPorPonto[ponto].esperado_caixa) }}</strong></div>
                                </div>

                                <div v-if="caixasPorPonto[ponto]?.estado === 'fechada'" class="flex flex-col gap-1 border-t border-linha-fraca pt-3 text-[15px]">
                                    <div class="flex justify-between"><span>Contado</span><strong>{{ euros(caixasPorPonto[ponto].valor_contado) }}</strong></div>
                                    <div class="flex justify-between"><span>Diferença</span><strong :class="diferencaClass(caixasPorPonto[ponto].diferenca)">{{ euros(caixasPorPonto[ponto].diferenca) }}</strong></div>
                                </div>

                                <div v-if="caixaAFechar !== caixasPorPonto[ponto]?.id || !caixasPorPonto[ponto]" class="grid grid-cols-2 gap-2">
                                    <Link v-if="caixasPorPonto[ponto]?.estado === 'aberta'" :href="route('bar.index', { ponto })" class="col-span-2 inline-flex h-12 items-center justify-center rounded-[10px] bg-verde text-[15px] font-bold text-white hover:bg-verde-escuro">Vender senhas</Link>
                                    <button
                                        type="button"
                                        class="h-12 rounded-[10px] text-[15px] font-bold"
                                        :class="[
                                            caixasPorPonto[ponto] ? 'border border-linha-forte bg-white text-tinta hover:bg-fundo' : 'bg-verde text-white hover:bg-verde-escuro',
                                            caixasPorPonto[ponto]?.estado === 'aberta' ? '' : 'col-span-2',
                                        ]"
                                        @click="prepararAbertura(ponto)"
                                    >{{ !caixasPorPonto[ponto] ? 'Abrir caixa' : caixasPorPonto[ponto].estado === 'fechada' ? 'Reabrir' : 'Ajustar fundo' }}</button>
                                    <button v-if="caixasPorPonto[ponto]?.estado === 'aberta'" type="button" class="h-12 rounded-[10px] bg-escuro-2 text-[15px] font-bold text-white hover:bg-escuro" @click="prepararFecho(caixasPorPonto[ponto])">Fechar caixa</button>
                                </div>

                                <form v-if="caixasPorPonto[ponto] && caixaAFechar === caixasPorPonto[ponto].id" class="flex flex-col gap-3 rounded-[10px] bg-perigo-claro p-3.5" @submit.prevent="fecharCaixa(caixasPorPonto[ponto])">
                                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Valor contado
                                        <span class="flex h-14 items-center rounded-[10px] border border-linha-forte bg-white px-3.5 focus-within:border-verde">
                                            <input v-model.number="fecharForm.valor_contado" type="number" min="0" step="0.01" inputmode="decimal" class="w-full min-w-0 border-0 bg-transparent p-0 text-2xl font-extrabold text-tinta focus:ring-0">
                                            <span class="font-bold text-suave-2">€</span>
                                        </span>
                                    </label>
                                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Observações
                                        <textarea v-model="fecharForm.observacoes_fecho" rows="2" class="w-full rounded-[10px] border border-linha-forte bg-white p-3 text-base text-tinta focus:border-verde focus:ring-verde" placeholder="Opcional"></textarea>
                                    </label>
                                    <p class="text-sm text-perigo-texto">Esperado: <strong>{{ euros(caixasPorPonto[ponto].esperado_caixa) }}</strong></p>
                                    <div v-if="Object.keys(fecharForm.errors).length" role="alert" class="rounded-[10px] bg-white p-3 text-sm font-semibold text-perigo-texto">
                                        <div v-for="erro in fecharForm.errors" :key="erro">{{ erro }}</div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" class="h-12 rounded-[10px] border border-linha-forte bg-white text-[15px] font-bold text-tinta" @click="cancelarFecho">Cancelar</button>
                                        <button class="h-12 rounded-[10px] bg-perigo text-[15px] font-extrabold text-white disabled:opacity-50" :disabled="fecharForm.processing">Confirmar</button>
                                    </div>
                                </form>
                            </article>
                        </div>
                    </section>
                </div>

                <!-- Abertura -->
                <form ref="formAbertura" class="flex flex-col gap-4 rounded-[14px] border border-linha bg-white p-5 xl:sticky xl:top-6" @submit.prevent="abrirCaixa">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[13px] font-bold uppercase tracking-[.08em] text-suave-2">Abertura</span>
                        <h2 class="text-xl font-extrabold">Abrir / reabrir caixa</h2>
                    </div>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Ponto
                        <input v-model="form.ponto" list="pontos-caixa" class="h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-[17px] font-bold text-tinta focus:border-verde focus:ring-verde">
                    </label>
                    <datalist id="pontos-caixa"><option v-for="ponto in pontos_padrao" :key="ponto" :value="ponto" /></datalist>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Fundo de maneio
                        <span class="flex h-14 items-center rounded-[10px] border border-linha-forte bg-white px-3.5 focus-within:border-verde">
                            <input v-model.number="form.fundo_maneio" type="number" min="0" step="0.01" inputmode="decimal" class="w-full min-w-0 border-0 bg-transparent p-0 text-2xl font-extrabold text-tinta focus:ring-0" placeholder="0,00">
                            <span class="font-bold text-suave-2">€</span>
                        </span>
                    </label>
                    <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-semibold text-perigo-texto">
                        <div v-for="erro in form.errors" :key="erro">{{ erro }}</div>
                    </div>
                    <button class="h-[52px] rounded-[10px] bg-verde text-base font-extrabold text-white hover:bg-verde-escuro disabled:opacity-50" :disabled="form.processing">
                        {{ form.processing ? 'A guardar...' : 'Guardar abertura' }}
                    </button>
                    <p class="text-[13px] leading-snug text-suave-2">Se uma caixa estiver fechada, reabrir limpa o fecho e permite continuar a trabalhar nesse ponto.</p>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
