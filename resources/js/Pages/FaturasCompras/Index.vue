<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    produtosOptions: Array,
    faturasRecentes: Array,
});

const hoje = new Date().toISOString().slice(0, 10);

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

const submeterDevolucao = (fatura) => {
    if (submitting.value[fatura.id]) return;
    submitting.value[fatura.id] = true;

    const items = fatura.items.map((item) => ({
        id: item.id,
        quantidade_devolvida: Number(devolucoesState[fatura.id]?.[item.id] ?? 0),
    }));

    router.post(route('faturas-compras.devolver', fatura.id), { items }, {
        preserveScroll: true,
        onSuccess: () => { paineisAbertos[fatura.id] = false; },
        onFinish: () => { submitting.value[fatura.id] = false; },
    });
};

// --- Formatação ---
const formatarMoeda = (valor, casas = 2) => Number(valor || 0).toLocaleString('pt-PT', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: casas,
    maximumFractionDigits: casas,
});

const formatarQuantidade = (valor) => Number(valor || 0).toLocaleString('pt-PT', {
    maximumFractionDigits: 3,
});

const formatarData = (data) => {
    const dataNormalizada = String(data).split('T')[0];

    return new Date(`${dataNormalizada}T00:00:00`).toLocaleDateString('pt-PT');
};
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Faturas e stock</h1>
            <p class="mt-1 text-sm text-slate-500">Registe compras e atualize o stock.</p>
        </div>

        <section class="mb-6 rounded-lg bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">Nova fatura</h2>
                    <p class="text-sm text-slate-500">Ao gravar, as quantidades entram no stock e contam para as Contas Festa.</p>
                </div>
                <div class="text-right">
                    <p class="text-xs uppercase text-slate-500">Total</p>
                    <p class="text-xl font-bold">{{ formatarMoeda(totalFatura) }}</p>
                </div>
            </div>

            <form class="space-y-4" @submit.prevent="registarFatura">
                <div class="grid gap-3 md:grid-cols-[1fr_160px_160px]">
                    <input v-model="form.fornecedor" class="rounded-md border-slate-300 text-sm" placeholder="Fornecedor">
                    <input v-model="form.numero" class="rounded-md border-slate-300 text-sm" placeholder="N. da fatura">
                    <input v-model="form.data_fatura" type="date" class="rounded-md border-slate-300 text-sm">
                </div>

                <div class="space-y-2">
                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="grid gap-2 rounded-md border border-slate-200 p-3 md:grid-cols-[1fr_130px_150px_90px]"
                    >
                        <select v-model="item.produto_id" class="rounded-md border-slate-300 text-sm">
                            <option v-for="produto in produtosOptions" :key="produto.id" :value="produto.id">
                                {{ produto.nome }} - stock {{ formatarQuantidade(produto.stock_atual) }}
                            </option>
                        </select>
                        <input v-model="item.quantidade" type="number" min="0.001" step="0.001" class="rounded-md border-slate-300 text-sm" placeholder="Qtd.">
                        <input v-model="item.preco_unitario" type="number" min="0" step="0.01" class="rounded-md border-slate-300 text-sm" placeholder="Preco unit.">
                        <button type="button" class="rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 disabled:opacity-40" :disabled="form.items.length === 1" @click="removerLinha(index)">
                            Remover
                        </button>
                    </div>
                </div>

                <div v-if="Object.keys(form.errors).length" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                    <div v-for="erro in form.errors" :key="erro">{{ erro }}</div>
                </div>

                <div class="flex flex-wrap justify-between gap-3">
                    <button type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="adicionarLinha">
                        Adicionar linha
                    </button>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="form.processing || !produtosOptions?.length">
                        {{ form.processing ? 'A registar...' : 'Registar fatura' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-bold">Faturas recentes</h2>
            <div v-if="!faturasRecentes?.length" class="rounded-md bg-slate-50 p-6 text-center text-sm text-slate-500">
                Ainda nao ha faturas registadas.
            </div>
            <div v-else class="grid gap-3 lg:grid-cols-2">
                <div v-for="fatura in faturasRecentes" :key="fatura.id" class="rounded-md border border-slate-200 text-sm">
                    <!-- Cabecalho do cartao -->
                    <div class="flex flex-wrap items-start justify-between gap-2 p-4">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ fatura.fornecedor || 'Sem fornecedor' }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ fatura.numero || 'Sem numero' }} · {{ formatarData(fatura.data) }}
                            </p>
                            <p class="mt-2 text-xs text-slate-600">
                                {{ fatura.items.map((item) => `${item.produto?.nome} (${formatarQuantidade(item.quantidade)})`).join(', ') }}
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <p class="font-bold">{{ formatarMoeda(fatura.total) }}</p>
                            <button
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold transition-opacity disabled:opacity-60"
                                :class="fatura.pago ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                :disabled="toggling[fatura.id]"
                                @click="togglePago(fatura)"
                            >
                                {{ fatura.pago ? 'Pago' : 'Por pagar' }}
                            </button>
                        </div>
                    </div>

                    <!-- Botao devolucoes -->
                    <div class="border-t border-slate-100 px-4 py-2">
                        <button
                            class="text-xs font-semibold text-slate-500 hover:text-slate-800"
                            @click="abrirPainelDevolucao(fatura)"
                        >
                            {{ paineisAbertos[fatura.id] ? 'Fechar devolucoes' : 'Registar devolucoes ao fornecedor' }}
                        </button>
                    </div>

                    <!-- Painel de devolucoes -->
                    <div v-if="paineisAbertos[fatura.id]" class="border-t border-slate-100 bg-slate-50 p-4">
                        <p class="mb-3 text-xs text-slate-500">Indique a quantidade devolvida por linha. O stock sera reduzido correspondentemente.</p>
                        <div class="space-y-2">
                            <div
                                v-for="item in fatura.items"
                                :key="item.id"
                                class="flex items-center gap-3"
                            >
                                <span class="flex-1 truncate text-xs">{{ item.produto?.nome }}</span>
                                <span class="shrink-0 text-xs text-slate-500">comprado: {{ formatarQuantidade(item.quantidade) }}</span>
                                <input
                                    v-if="devolucoesState[fatura.id]"
                                    v-model.number="devolucoesState[fatura.id][item.id]"
                                    type="number"
                                    min="0"
                                    :max="Number(item.quantidade)"
                                    step="0.001"
                                    class="w-24 rounded-md border-slate-300 text-xs"
                                    placeholder="Devolvido"
                                >
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <button
                                class="rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60"
                                :disabled="submitting[fatura.id]"
                                @click="submeterDevolucao(fatura)"
                            >
                                {{ submitting[fatura.id] ? 'A guardar...' : 'Guardar devolucoes' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
