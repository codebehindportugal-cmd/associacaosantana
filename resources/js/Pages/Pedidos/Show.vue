<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ pedido: Object, mesas: Array, produtos: Array });

const pedidoForm = useForm({
    mesa_id: props.mesas?.[0]?.id ?? '',
    lugares_ocupados: '',
    observacoes: '',
});
const itemForm = useForm({ pedido_id: props.pedido?.id, produto_id: '', quantidade: 1, prioridade: false, observacoes: '' });
const estadoForm = useForm({ estado: props.pedido?.estado ?? 'pendente' });
const cancelamentoForm = useForm({ estado: 'cancelado' });
const fecharContaForm = useForm({ metodo_pagamento: 'dinheiro', valor_recebido: '', troco: 0 });
const quantidade = ref(1);
const termo = ref('');

const secoes = [
    ['todos', 'Todos'],
    ['bebidas', 'Bebidas'],
    ['comida', 'Comida'],
    ['sobremesas', 'Sobremesas'],
];
const secaoAtiva = ref('todos');

const produtosFiltrados = computed(() => {
    const pesquisa = termo.value.trim().toLowerCase();

    return (props.produtos ?? []).filter((produto) => {
        const secao = produto.categoria?.secao;
        const passaSecao = secaoAtiva.value === 'todos' || secao === secaoAtiva.value;
        const passaPesquisa = !pesquisa || produto.nome.toLowerCase().includes(pesquisa);

        return passaSecao && passaPesquisa;
    });
});

const totalPedido = computed(() => Number(props.pedido?.total ?? props.pedido?.total_calculado ?? 0));
const pedidoFechado = computed(() => ['entregue', 'cancelado'].includes(props.pedido?.estado));
const valorRecebido = computed(() => Number(fecharContaForm.valor_recebido || totalPedido.value));
const valorTroco = computed(() => Number(fecharContaForm.troco || 0));
const doacaoEstimada = computed(() => Math.max(0, valorRecebido.value - totalPedido.value - valorTroco.value));

const adicionarProduto = (produto) => {
    if (pedidoFechado.value) {
        return;
    }

    itemForm.produto_id = produto.id;
    itemForm.quantidade = quantidade.value || 1;
    itemForm.post(route('pedido-items.store'), {
        preserveScroll: true,
        onSuccess: () => {
            itemForm.produto_id = '';
            quantidade.value = 1;
            itemForm.prioridade = false;
            itemForm.observacoes = '';
        },
    });
};

const alternarUrgente = (item) => {
    router.patch(route('pedido-items.update', item.id), { prioridade: !item.prioridade }, { preserveScroll: true });
};

const cancelarPedido = () => {
    if (!confirm('Cancelar este pedido e libertar a mesa?')) {
        return;
    }

    cancelamentoForm.patch(route('pedidos.estado', props.pedido.id), { preserveScroll: true });
};

const fecharConta = () => {
    if (!confirm('Fechar a conta, libertar a mesa e imprimir o talão?')) {
        return;
    }

    fecharContaForm
        .transform((dados) => ({
            metodo_pagamento: dados.metodo_pagamento,
            valor_recebido: dados.valor_recebido || totalPedido.value,
            troco: dados.troco || 0,
        }))
        .patch(route('pedidos.fecharConta', props.pedido.id), {
            onFinish: () => fecharContaForm.transform((dados) => dados),
        });
};

const formatarPreco = (valor) => `${Number(valor ?? 0).toFixed(2)}€`;
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">{{ pedido ? `Pedido #${pedido.id}` : 'Novo pedido' }}</h1>
                <p v-if="pedido" class="mt-1 text-sm text-slate-500">{{ pedido.mesa?.designacao }} · {{ pedido.estado }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link v-if="pedido" :href="route('pedidos.talao', pedido.id)" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold">Talão</Link>
                <Link :href="route('pedidos.index')" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold">Voltar</Link>
            </div>
        </div>

        <form v-if="!pedido" class="max-w-xl space-y-4 rounded-lg bg-white p-6 shadow-sm" @submit.prevent="pedidoForm.post(route('pedidos.store'))">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-700">Mesa ou submesa</span>
                <select v-model="pedidoForm.mesa_id" class="w-full rounded-md border-slate-300">
                    <option v-for="mesa in mesas" :key="mesa.id" :value="mesa.id">
                        {{ mesa.designacao }}{{ mesa.lugares ? ` · lugares ${mesa.lugares}` : '' }} · {{ mesa.capacidade }} pessoas
                    </option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-700">Lugares ocupados</span>
                <input v-model="pedidoForm.lugares_ocupados" type="number" min="1" class="w-full rounded-md border-slate-300" placeholder="Vazio = mesa completa">
            </label>

            <textarea v-model="pedidoForm.observacoes" class="w-full rounded-md border-slate-300" placeholder="Observações"></textarea>
            <button class="rounded-md bg-slate-900 px-4 py-2 text-white">Criar pedido</button>
        </form>

        <div v-else class="grid gap-6 xl:grid-cols-[1fr_420px]">
            <section class="space-y-4">
                <div v-if="!pedidoFechado" class="rounded-lg bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-sm text-slate-500">Mesa</div>
                            <div class="text-xl font-bold">{{ pedido.mesa?.designacao }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm text-slate-500">Total</div>
                            <div class="text-2xl font-bold">{{ formatarPreco(totalPedido) }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4 font-semibold">Itens do pedido</div>
                    <div v-if="pedido.items?.length" class="divide-y divide-slate-100">
                        <div v-for="item in pedido.items" :key="item.id" class="flex items-center justify-between gap-4 px-5 py-4" :class="item.prioridade ? 'bg-red-50' : ''">
                            <div>
                                <div class="font-semibold">{{ item.quantidade }}x {{ item.produto?.nome }} <span v-if="item.prioridade" class="ml-2 rounded-full bg-red-600 px-2 py-1 text-xs font-black text-white">⚡ URGENTE</span></div>
                                <div class="text-sm text-slate-500">{{ item.produto?.categoria?.nome }} · {{ formatarPreco(item.preco_unitario) }} cada</div>
                                <div v-if="item.observacoes" class="mt-2 rounded-md bg-amber-100 px-3 py-2 text-sm font-bold text-amber-900">
                                    Info: {{ item.observacoes }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="rounded-full px-3 py-2 text-xs font-black" :class="item.prioridade ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-700'" @click="alternarUrgente(item)">⚡ Urgente</button>
                                <div class="rounded-full px-3 py-1 text-xs font-semibold" :class="item.estado === 'pronto' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ item.estado }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-slate-500">Ainda não há comida ou bebidas neste pedido.</div>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="rounded-lg bg-white p-5 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="font-semibold">Adicionar produtos</h2>
                        <input v-model.number="quantidade" type="number" min="1" class="w-20 rounded-md border-slate-300 text-center">
                    </div>

                    <label class="mb-4 flex items-center gap-3 rounded-lg bg-red-50 p-3 font-bold text-red-700">
                        <input v-model="itemForm.prioridade" type="checkbox" class="rounded border-red-300 text-red-600">
                        ⚡ Marcar novo item como urgente
                    </label>

                    <label class="mb-4 block rounded-lg bg-amber-50 p-3">
                        <span class="mb-1 block text-sm font-black text-amber-900">Informação para a secção</span>
                        <textarea v-model="itemForm.observacoes" rows="2" class="w-full rounded-md border-amber-200 text-sm" placeholder="Ex.: sem picante, alergia, sem molho..."></textarea>
                    </label>

                    <div class="mb-3 flex flex-wrap gap-2">
                        <button
                            v-for="[valor, label] in secoes"
                            :key="valor"
                            type="button"
                            class="rounded-md border px-3 py-2 text-sm font-semibold"
                            :class="secaoAtiva === valor ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700'"
                            @click="secaoAtiva = valor"
                        >
                            {{ label }}
                        </button>
                    </div>

                    <input v-model="termo" class="mb-4 w-full rounded-md border-slate-300" placeholder="Procurar produto">

                    <div class="grid max-h-[58vh] gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                        <button
                            v-for="produto in produtosFiltrados"
                            :key="produto.id"
                            type="button"
                            class="rounded-lg border border-slate-200 p-3 text-left hover:border-emerald-500 hover:bg-emerald-50"
                            @click="adicionarProduto(produto)"
                        >
                            <div class="font-semibold">{{ produto.nome }}</div>
                            <div class="mt-1 flex items-center justify-between text-sm text-slate-500">
                                <span>{{ produto.categoria?.nome }}</span>
                                <span class="font-semibold text-slate-900">{{ formatarPreco(produto.preco) }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <form class="rounded-lg bg-white p-5 shadow-sm" @submit.prevent="estadoForm.patch(route('pedidos.estado', pedido.id))">
                    <label class="block">
                        <span class="mb-1 block text-sm font-semibold text-slate-700">Estado do pedido</span>
                        <select v-model="estadoForm.estado" class="w-full rounded-md border-slate-300">
                            <option>pendente</option>
                            <option>preparacao</option>
                            <option>pronto</option>
                            <option>entregue</option>
                            <option>cancelado</option>
                        </select>
                    </label>
                    <button class="mt-3 rounded-md bg-emerald-700 px-4 py-2 text-white">Mudar estado</button>
                </form>

                <form v-if="!pedidoFechado" class="rounded-lg bg-white p-5 shadow-sm" @submit.prevent="fecharConta">
                    <h2 class="mb-3 font-semibold">Fechar conta</h2>
                    <div class="grid gap-3">
                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-slate-700">Método de pagamento</span>
                            <select v-model="fecharContaForm.metodo_pagamento" class="w-full rounded-md border-slate-300">
                                <option value="dinheiro">Dinheiro</option>
                                <option value="mbway">MBWay</option>
                                <option value="multibanco">Multibanco</option>
                                <option value="transferencia">Transferência</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-slate-700">Valor recebido</span>
                            <input v-model.number="fecharContaForm.valor_recebido" type="number" min="0" step="0.01" class="w-full rounded-md border-slate-300" :placeholder="formatarPreco(totalPedido)">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-sm font-semibold text-slate-700">Troco entregue</span>
                            <input v-model.number="fecharContaForm.troco" type="number" min="0" step="0.01" class="w-full rounded-md border-slate-300">
                        </label>
                    </div>
                    <div class="mt-3 rounded-md bg-slate-50 p-3 text-sm">
                        <div class="flex justify-between"><span>Total</span><strong>{{ formatarPreco(totalPedido) }}</strong></div>
                        <div class="flex justify-between"><span>Doação</span><strong>{{ formatarPreco(doacaoEstimada) }}</strong></div>
                    </div>
                    <div v-if="Object.keys(fecharContaForm.errors).length" class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700">
                        <div v-for="erro in fecharContaForm.errors" :key="erro">{{ erro }}</div>
                    </div>
                    <button
                        type="submit"
                        class="mt-3 w-full rounded-lg bg-slate-900 p-4 text-sm font-bold text-white shadow-sm hover:bg-slate-800 disabled:opacity-60"
                        :disabled="fecharContaForm.processing"
                    >
                        {{ fecharContaForm.processing ? 'A fechar...' : 'Fechar conta e imprimir talão' }}
                    </button>
                </form>

                <button
                    v-if="!pedidoFechado"
                    type="button"
                    class="w-full rounded-lg border border-red-300 bg-white p-4 text-sm font-bold text-red-700 shadow-sm hover:bg-red-50"
                    @click="cancelarPedido"
                >
                    Cancelar pedido
                </button>
            </aside>
        </div>
    </AppLayout>
</template>

