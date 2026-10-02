<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    produtosOptions: Array,
    faturasRecentes: Array,
});

const hoje = new Date().toISOString().slice(0, 10);

// Filtros da lista de faturas
const pesquisaFatura = ref('');
const filtroPago = ref('');
const faturasFiltradas = computed(() => (props.faturasRecentes ?? []).filter((fatura) => {
    if (filtroPago.value === 'pagas' && !fatura.pago) return false;
    if (filtroPago.value === 'por_pagar' && fatura.pago) return false;
    const termo = pesquisaFatura.value.trim().toLowerCase();
    if (termo) {
        const texto = `${fatura.fornecedor ?? ''} ${fatura.numero ?? ''} ${(fatura.items ?? []).map((item) => item.produto?.nome ?? '').join(' ')}`.toLowerCase();
        if (!texto.includes(termo)) return false;
    }
    return true;
}));

const linhaNova = () => ({
    produto_id: props.produtosOptions?.[0]?.id ?? '',
    quantidade: 1,
    preco_unitario: '',
});

const form = useForm({
    fornecedor: '',
    numero: '',
    data_fatura: hoje,
    items: [linhaNova()],
});

const totalFatura = computed(() => form.items.reduce((total, item) => {
    const quantidade = Number(item.quantidade) || 0;
    const preco = Number(item.preco_unitario) || 0;

    return total + quantidade * preco;
}, 0));

const adicionarLinha = () => {
    form.items.push(linhaNova());
};

const removerLinha = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const registarFatura = () => {
    form
        .transform((dados) => ({
            ...dados,
            data: dados.data_fatura,
        }))
        .post(route('faturas-compras.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('fornecedor', 'numero');
                form.data_fatura = hoje;
                form.items = [linhaNova()];
            },
            onFinish: () => form.transform((dados) => dados),
        });
};

// --- Pago ---
const toggling = ref({});

const togglePago = (fatura) => {
    if (toggling.value[fatura.id]) return;
    toggling.value[fatura.id] = true;
    router.patch(route('faturas-compras.pagar', fatura.id), {}, {
        preserveScroll: true,
        onFinish: () => { toggling.value[fatura.id] = false; },
    });
};

// --- Devoluções ---
const paineisAbertos = reactive({});
const devolucoesState = reactive({});
const submitting = ref({});

const abrirPainelDevolucao = (fatura) => {
    if (!paineisAbertos[fatura.id]) {
        // Inicializar com os valores existentes
        devolucoesState[fatura.id] = {};
        fatura.items.forEach((item) => {
            devolucoesState[fatura.id][item.id] = Number(item.quantidade_devolvida) || 0;
        });
    }
    paineisAbertos[fatura.id] = !paineisAbertos[fatura.id];
};

const errosDevolucao = ref({});
const submeterDevolucao = (fatura) => {
    if (submitting.value[fatura.id]) return;
    submitting.value[fatura.id] = true;
    errosDevolucao.value[fatura.id] = {};

    const items = fatura.items.map((item) => ({
        id: item.id,
        quantidade_devolvida: Number(devolucoesState[fatura.id]?.[item.id] ?? 0),
    }));

    router.post(route('faturas-compras.devolver', fatura.id), { items }, {
        preserveScroll: true,
        onSuccess: () => { paineisAbertos[fatura.id] = false; },
        onError: (erros) => { errosDevolucao.value[fatura.id] = erros; },
        onFinish: () => { submitting.value[fatura.id] = false; },
    });
};

// --- Formatação ---
const formatarMoeda = (valor, casas = 2) => Number(valor || 0).toLocaleString('pt-PT', { useGrouping: 'always',
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: casas,
    maximumFractionDigits: casas,
});

const formatarQuantidade = (valor) => Number(valor || 0).toLocaleString('pt-PT', { useGrouping: 'always',
    maximumFractionDigits: 3,
});

const campo = 'h-12 w-full min-w-0 rounded-[10px] border border-linha-forte bg-white px-3 text-base text-tinta focus:border-verde focus:ring-verde';

const formatarData = (data) => {
    const dataNormalizada = String(data).split('T')[0];

    return new Date(`${dataNormalizada}T00:00:00`).toLocaleDateString('pt-PT');
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-col gap-1.5">
                <h1 class="text-[30px] font-extrabold leading-tight">Faturas e stock</h1>
                <p class="text-[15px] text-suave">Registe compras e atualize o stock.</p>
            </div>

            <section class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="flex flex-col gap-1 border-b border-linha-fraca px-5 py-[18px]">
                    <h2 class="text-xl font-extrabold">Nova fatura</h2>
                    <p class="text-[15px] text-suave">Ao gravar, as quantidades entram no stock e contam para as Contas da festa.</p>
                </div>

                <form class="flex flex-col gap-4 p-4 sm:p-5" @submit.prevent="registarFatura">
                    <div class="grid gap-2.5 sm:grid-cols-[minmax(0,1fr)_180px_180px]">
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Fornecedor
                            <input v-model="form.fornecedor" :class="campo" placeholder="Fornecedor">
                        </label>
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">N.º da fatura
                            <input v-model="form.numero" :class="campo" placeholder="N.º da fatura">
                        </label>
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Data
                            <input v-model="form.data_fatura" type="date" :class="[campo, 'px-2.5']">
                        </label>
                    </div>

                    <div class="flex flex-col gap-2">
                        <span class="text-sm font-extrabold">Linhas da fatura</span>
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="grid grid-cols-2 items-end gap-2.5 rounded-[10px] bg-fundo p-3 md:grid-cols-[minmax(0,1fr)_120px_140px_110px_48px]"
                        >
                            <label class="col-span-2 flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave md:col-span-1">Produto
                                <select v-model="item.produto_id" :class="[campo, 'px-2.5 text-[15px]']">
                                    <option v-for="produto in produtosOptions" :key="produto.id" :value="produto.id">
                                        {{ produto.nome }} — stock {{ formatarQuantidade(produto.stock_atual) }}
                                    </option>
                                </select>
                            </label>
                            <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Qtd.
                                <input v-model="item.quantidade" type="number" min="0.001" step="0.001" inputmode="decimal" :class="[campo, 'font-bold']" placeholder="Qtd.">
                            </label>
                            <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Preço unit. (€)
                                <input v-model="item.preco_unitario" type="number" min="0" step="0.01" inputmode="decimal" :class="[campo, 'font-bold']" placeholder="0,00">
                            </label>
                            <div class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Subtotal
                                <span class="flex h-12 items-center text-base font-extrabold text-tinta">{{ formatarMoeda((Number(item.quantidade) || 0) * (Number(item.preco_unitario) || 0)) }}</span>
                            </div>
                            <button
                                type="button"
                                :aria-label="`Remover linha ${index + 1}`"
                                class="flex h-12 w-12 items-center justify-center justify-self-end rounded-[10px] border border-[#F0D3CD] bg-white text-perigo hover:bg-perigo-claro disabled:opacity-40"
                                :disabled="form.items.length === 1"
                                @click="removerLinha(index)"
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                            </button>
                        </div>
                        <button type="button" class="inline-flex h-11 items-center gap-2 self-start rounded-[10px] border border-dashed border-[#B9C1BB] bg-white px-4 text-[15px] font-bold text-verde hover:bg-verde-claro" @click="adicionarLinha">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            Adicionar linha
                        </button>
                    </div>

                    <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-[10px] bg-perigo-claro px-4 py-3 text-sm font-semibold text-perigo-texto">
                        <div v-for="erro in form.errors" :key="erro">{{ erro }}</div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-4 border-t border-linha-fraca pt-4">
                        <div class="flex flex-col items-end">
                            <span class="text-sm font-semibold text-suave-2">Total da fatura</span>
                            <strong class="text-[28px] font-extrabold">{{ formatarMoeda(totalFatura) }}</strong>
                        </div>
                        <button type="submit" class="h-14 rounded-[10px] bg-verde px-7 text-base font-extrabold text-white hover:bg-verde-escuro disabled:opacity-60" :disabled="form.processing || !produtosOptions?.length">
                            {{ form.processing ? 'A registar...' : 'Registar fatura' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="mr-2 text-xl font-extrabold">Faturas recentes</h2>
                    <div role="group" aria-label="Filtrar por pagamento" class="flex flex-wrap gap-2">
                        <button
                            v-for="opcao in [['', 'Todas'], ['pagas', 'Pagas'], ['por_pagar', 'Por pagar']]"
                            :key="opcao[0]"
                            type="button"
                            :aria-pressed="filtroPago === opcao[0]"
                            class="h-11 rounded-full px-4 text-sm font-bold"
                            :class="filtroPago === opcao[0] ? 'bg-tinta text-white' : 'border border-linha-forte bg-white text-tinta hover:bg-fundo'"
                            @click="filtroPago = opcao[0]"
                        >
                            {{ opcao[1] }}
                        </button>
                    </div>
                    <label class="flex h-11 min-w-0 flex-[1_1_100%] items-center gap-2.5 rounded-[10px] border border-linha-forte bg-white px-3.5 text-suave-2 sm:ml-auto sm:flex-[0_1_300px]">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
                        <span class="sr-only">Procurar fatura</span>
                        <input v-model="pesquisaFatura" type="search" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[15px] text-tinta placeholder:text-suave-2 focus:ring-0" placeholder="Fornecedor, n.º ou produto…">
                    </label>
                </div>

                <div v-if="!faturasFiltradas?.length" class="rounded-[14px] border border-linha bg-white p-8 text-center text-suave">
                    {{ faturasRecentes?.length ? 'Nada encontrado com este filtro.' : 'Ainda não há faturas registadas.' }}
                </div>
                <div v-else class="grid items-start gap-3 lg:grid-cols-2">
                    <article v-for="fatura in faturasFiltradas" :key="fatura.id" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                        <div class="flex items-start justify-between gap-3 px-[18px] py-4">
                            <div class="flex min-w-0 flex-col gap-1">
                                <strong class="break-words text-[17px]">{{ fatura.fornecedor || 'Sem fornecedor' }}</strong>
                                <span class="text-sm text-suave-2">{{ fatura.numero || 'Sem número' }} · {{ formatarData(fatura.data) }}</span>
                                <span class="text-sm text-suave">{{ fatura.items.map((item) => `${item.produto?.nome} (${formatarQuantidade(item.quantidade)})`).join(', ') }}</span>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <strong class="whitespace-nowrap text-[19px]">{{ formatarMoeda(fatura.total) }}</strong>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="!!fatura.pago"
                                    class="h-9 rounded-full px-3.5 text-[13px] font-extrabold transition-opacity disabled:opacity-60"
                                    :class="fatura.pago ? 'bg-verde-claro2 text-verde-escuro' : 'bg-laranja-claro text-laranja-texto'"
                                    :disabled="toggling[fatura.id]"
                                    @click="togglePago(fatura)"
                                >
                                    {{ fatura.pago ? 'Pago' : 'Por pagar' }}
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-linha-fraca px-[18px] py-1">
                            <button
                                type="button"
                                :aria-expanded="!!paineisAbertos[fatura.id]"
                                class="h-11 text-sm font-bold text-suave hover:text-tinta"
                                @click="abrirPainelDevolucao(fatura)"
                            >
                                {{ paineisAbertos[fatura.id] ? 'Fechar devoluções' : 'Registar devoluções ao fornecedor' }}
                            </button>
                        </div>

                        <div v-if="paineisAbertos[fatura.id]" class="flex flex-col gap-2.5 border-t border-linha-fraca bg-fundo px-[18px] py-4">
                            <AvisoErros :errors="errosDevolucao[fatura.id] || {}" />
                            <p class="text-sm text-suave-2">Indique a quantidade devolvida por linha. O stock será reduzido correspondentemente.</p>
                            <div v-for="item in fatura.items" :key="item.id" class="flex items-center gap-3 text-[15px]">
                                <span class="min-w-0 flex-1 truncate">{{ item.produto?.nome }}</span>
                                <span class="shrink-0 text-[13px] text-suave-2">comprado: {{ formatarQuantidade(item.quantidade) }}</span>
                                <input
                                    v-if="devolucoesState[fatura.id]"
                                    v-model.number="devolucoesState[fatura.id][item.id]"
                                    type="number"
                                    min="0"
                                    :max="Number(item.quantidade)"
                                    step="0.001"
                                    inputmode="decimal"
                                    :aria-label="`Devolvido de ${item.produto?.nome ?? 'produto'}`"
                                    class="h-11 w-[90px] shrink-0 rounded-[10px] border border-linha-forte bg-white px-2.5 text-[15px] focus:border-verde focus:ring-verde"
                                    placeholder="0"
                                >
                            </div>
                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="h-11 rounded-[10px] bg-escuro-2 px-[18px] text-sm font-bold text-white hover:bg-escuro disabled:opacity-60"
                                    :disabled="submitting[fatura.id]"
                                    @click="submeterDevolucao(fatura)"
                                >
                                    {{ submitting[fatura.id] ? 'A guardar...' : 'Guardar devoluções' }}
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
