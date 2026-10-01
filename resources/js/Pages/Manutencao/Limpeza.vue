<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    filters: Object,
    preview: Object,
});

const filtros = reactive({
    data_inicio: props.filters?.data_inicio,
    data_fim: props.filters?.data_fim,
    tipo: props.filters?.tipo ?? 'ambos',
    manter_pedido_id: props.filters?.manter_pedido_id ?? '',
    apenas_relatorios: props.filters?.apenas_relatorios ?? true,
});

const form = useForm({
    ...filtros,
    confirmacao: '',
});

watch(filtros, () => {
    form.data_inicio = filtros.data_inicio;
    form.data_fim = filtros.data_fim;
    form.tipo = filtros.tipo;
    form.manter_pedido_id = filtros.manter_pedido_id;
    form.apenas_relatorios = filtros.apenas_relatorios;
}, { deep: true });

const euros = (valor) => Number(valor ?? 0).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });

const tiposApagar = [
    ['ambos', 'Pedidos e caixas'],
    ['pedidos', 'Só pedidos'],
    ['caixas', 'Só caixas'],
];
const confirmado = computed(() => form.confirmacao === 'APAGAR DADOS');
const textoBotaoApagar = computed(() => {
    const partes = [];
    if (filtros.tipo !== 'caixas') partes.push(`${preview.value?.pedidos?.count ?? 0} pedidos`);
    if (filtros.tipo !== 'pedidos') partes.push(`${preview.value?.caixas?.count ?? 0} caixas`);
    return `Apagar ${partes.join(' e ')}`;
});
const preview = computed(() => props.preview);

const atualizarPreview = () => {
    router.get(route('manutencao.limpeza.index'), filtros, {
        preserveState: true,
        preserveScroll: true,
    });
};

const apagar = () => {
    form.delete(route('manutencao.limpeza.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            form.confirmacao = '';
            atualizarPreview();
        },
    });
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <h1 class="text-[30px] font-extrabold leading-tight">Manutenção</h1>
            <nav aria-label="Manutenção" class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-[12px] bg-linha-fraca p-1">
                <Link :href="route('manutencao.limpeza.index')" class="tab bg-white text-tinta shadow-sm" aria-current="page">Limpeza de dados</Link>
                <Link :href="route('manutencao.logs.index')" class="tab text-suave hover:text-tinta">Logs de alterações</Link>
            </nav>
            <p class="max-w-3xl text-[15px] text-suave">
                Remove dados operacionais que alimentam relatórios. Usa primeiro a pré-visualização e mantém o pedido correto pelo número.
            </p>

            <div class="flex items-start gap-3 rounded-[14px] border border-laranja-claro bg-laranja-claro p-4 text-sm text-laranja-texto">
                <svg class="mt-0.5 shrink-0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l10 18H2z" /><path d="M12 10v4M12 17.5v.5" /></svg>
                <p>Esta ação é destrutiva em produção. Faz backup antes de apagar e confirma que o pedido correto está indicado no campo “Manter pedido”.</p>
            </div>

            <section class="grid items-start gap-5 lg:grid-cols-[380px_minmax(0,1fr)]">
                <!-- 1. Filtros -->
                <form class="cartao flex flex-col gap-4 p-4 sm:p-5" @submit.prevent="atualizarPreview">
                    <h2 class="passo"><span class="num bg-tinta text-white">1</span>Escolher o que apagar</h2>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="rotulo">Data início<input v-model="filtros.data_inicio" type="date" class="campo px-2.5"></label>
                        <label class="rotulo">Data fim<input v-model="filtros.data_fim" type="date" class="campo px-2.5"></label>
                    </div>

                    <fieldset>
                        <legend class="mb-1.5 text-sm font-semibold text-suave">O que apagar</legend>
                        <div class="grid grid-cols-3 gap-2">
                            <label v-for="[valor, rotulo] in tiposApagar" :key="valor" class="cursor-pointer">
                                <input v-model="filtros.tipo" type="radio" :value="valor" class="peer sr-only">
                                <span class="flex min-h-12 items-center justify-center rounded-[10px] border-2 px-2 text-center text-[13px] font-bold leading-tight transition peer-focus-visible:ring-2 peer-focus-visible:ring-verde" :class="filtros.tipo === valor ? 'border-verde bg-verde-claro text-verde-escuro' : 'border-linha-forte bg-white'">{{ rotulo }}</span>
                            </label>
                        </div>
                    </fieldset>

                    <label class="rotulo">Manter pedido
                        <span class="relative block">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-suave">#</span>
                            <input v-model="filtros.manter_pedido_id" type="number" min="1" placeholder="Ex.: 23" class="campo pl-8 font-bold">
                        </span>
                        <span class="text-xs font-normal text-suave">Este pedido não será apagado, mesmo estando dentro do período.</span>
                    </label>

                    <label class="flex cursor-pointer items-start gap-3 rounded-[10px] bg-fundo p-3 text-sm">
                        <input v-model="filtros.apenas_relatorios" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-linha-forte text-verde focus:ring-verde">
                        <span>
                            <strong class="block font-extrabold">Apagar apenas pedidos que entram nos relatórios</strong>
                            <span class="text-suave">Recomendado: limita aos pedidos entregues ou pré-pagos.</span>
                        </span>
                    </label>

                    <button type="submit" class="h-[52px] w-full rounded-[10px] bg-verde px-4 text-base font-bold text-white transition hover:bg-verde-escuro">
                        Atualizar pré-visualização
                    </button>
                </form>

                <div class="flex min-w-0 flex-col gap-4">
                    <!-- 2. Pré-visualização -->
                    <h2 class="passo"><span class="num bg-tinta text-white">2</span>Ver o que vai desaparecer</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <article class="cartao p-4 sm:p-5">
                            <div class="text-sm font-semibold text-suave">Pedidos a apagar</div>
                            <div class="mt-1 text-[36px] font-extrabold leading-none">{{ preview?.pedidos?.count ?? 0 }}</div>
                            <div class="mt-2 text-sm text-suave">{{ preview?.pedidos?.items ?? 0 }} itens · {{ euros(preview?.pedidos?.total) }}</div>
                        </article>
                        <article class="cartao p-4 sm:p-5">
                            <div class="text-sm font-semibold text-suave">Caixas a apagar</div>
                            <div class="mt-1 text-[36px] font-extrabold leading-none">{{ preview?.caixas?.count ?? 0 }}</div>
                            <div class="mt-2 text-sm text-suave">Fundo de maneio: {{ euros(preview?.caixas?.fundo_maneio) }}</div>
                        </article>
                    </div>

                    <section class="cartao overflow-hidden">
                        <div class="flex items-center justify-between border-b border-linha-fraca px-4 py-3.5 sm:px-5">
                            <h3 class="text-base font-extrabold">Pedidos encontrados</h3>
                            <span v-if="preview?.pedidos?.samples?.length" class="text-[13px] text-suave">Amostra</span>
                        </div>
                        <div v-if="!preview?.pedidos?.samples?.length" class="px-4 py-4 text-sm text-suave-2 sm:px-5">Nenhum pedido neste filtro.</div>
                        <div v-for="pedido in preview?.pedidos?.samples" :key="pedido.id" class="grid grid-cols-[4rem_minmax(0,1fr)_auto] gap-x-3 gap-y-0.5 border-t border-linha-fraca px-4 py-2.5 text-sm first-of-type:border-t-0 sm:px-5 md:grid-cols-[4rem_minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <strong class="font-extrabold">#{{ pedido.id }}</strong>
                            <span class="truncate">{{ pedido.created_at }} · {{ pedido.ponto }}</span>
                            <span class="col-start-2 row-start-2 truncate text-suave md:col-start-auto md:row-start-auto">{{ pedido.tipo }} · {{ pedido.estado }}</span>
                            <strong class="col-start-3 row-start-1 text-right md:col-start-auto">{{ euros(pedido.total) }}</strong>
                        </div>
                        <div v-if="filtros.manter_pedido_id" class="flex items-center gap-2 border-t border-linha-fraca bg-verde-claro px-4 py-2.5 text-sm font-bold text-verde-escuro sm:px-5">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6z" /></svg>
                            Pedido #{{ filtros.manter_pedido_id }} fica guardado.
                        </div>
                    </section>

                    <section class="cartao overflow-hidden">
                        <h3 class="border-b border-linha-fraca px-4 py-3.5 text-base font-extrabold sm:px-5">Caixas encontradas</h3>
                        <div v-if="!preview?.caixas?.samples?.length" class="px-4 py-4 text-sm text-suave-2 sm:px-5">Nenhuma caixa neste filtro.</div>
                        <div v-for="caixa in preview?.caixas?.samples" :key="caixa.id" class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-0.5 border-t border-linha-fraca px-4 py-2.5 text-sm first-of-type:border-t-0 sm:px-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <strong class="font-extrabold">{{ caixa.data }}</strong>
                            <span class="row-start-2 text-suave md:row-start-auto md:text-tinta">{{ caixa.ponto }} <span class="md:hidden">· {{ caixa.estado }}</span></span>
                            <span class="hidden text-suave md:block">{{ caixa.estado }}</span>
                            <strong class="col-start-2 row-start-1 text-right md:col-start-auto">{{ euros(caixa.fundo_maneio) }}</strong>
                        </div>
                    </section>

                    <!-- 3. Confirmar -->
                    <form class="flex flex-col gap-3 rounded-[14px] border-2 border-perigo bg-perigo-claro p-4 sm:p-5" @submit.prevent="apagar">
                        <h2 class="passo text-perigo-texto"><span class="num bg-perigo text-white">3</span>Confirmar apagamento</h2>
                        <p class="text-[15px] text-perigo-texto">
                            Para apagar os dados desta pré-visualização, escreve exatamente <strong class="font-extrabold">APAGAR DADOS</strong>.
                        </p>
                        <label class="sr-only" for="confirmacao-apagar">Confirmação</label>
                        <input id="confirmacao-apagar" v-model="form.confirmacao" type="text" autocomplete="off" class="h-[52px] w-full rounded-[10px] border border-perigo/40 bg-white px-3.5 text-lg font-bold tracking-[0.06em] text-tinta focus:border-perigo focus:ring-perigo" placeholder="APAGAR DADOS">
                        <div v-if="form.errors.confirmacao" class="text-sm font-bold text-perigo-texto">{{ form.errors.confirmacao }}</div>
                        <button type="submit" class="h-[52px] w-full rounded-[10px] px-4 text-base font-extrabold text-white transition disabled:opacity-50" :class="confirmado ? 'bg-perigo hover:opacity-90' : 'bg-perigo/50'" :disabled="form.processing">
                            {{ form.processing ? 'A apagar...' : textoBotaoApagar }}
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.tab { @apply inline-flex h-11 shrink-0 items-center whitespace-nowrap rounded-[10px] px-4 text-sm font-bold transition; }
.cartao { @apply rounded-[14px] border border-linha bg-white; }
.passo { @apply flex items-center gap-3 text-lg font-extrabold; }
.num { @apply grid h-8 w-8 shrink-0 place-items-center rounded-full text-sm; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta focus:border-verde focus:ring-verde; }
</style>
