<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({ pedido: Object });

const total = computed(() => Number(props.pedido?.total ?? props.pedido?.total_calculado ?? 0));
const valorRecebido = computed(() => Number(props.pedido?.valor_recebido ?? total.value));
const troco = computed(() => Number(props.pedido?.troco ?? 0));
const doacao = computed(() => Number(props.pedido?.doacao ?? 0));
const operador = computed(() => props.pedido?.operador_nome ?? props.pedido?.user?.name ?? props.pedido?.pos?.nome ?? 'Sem operador');
const itensComValor = computed(() => (props.pedido?.items ?? []).filter((item) => Number(item.preco_unitario) > 0));
const servicos = computed(() => (props.pedido?.items ?? []).filter((item) => Number(item.preco_unitario) === 0));

const formatarPreco = (valor) => `${Number(valor ?? 0).toFixed(2)}€`;
const subtotal = (item) => formatarPreco(Number(item.preco_unitario) * Number(item.quantidade));
const agora = new Date().toLocaleString('pt-PT');
const imprimir = () => window.print();

onMounted(() => {
    setTimeout(() => window.print(), 300);
});
</script>

<template>
    <main class="min-h-screen bg-slate-100 p-4 text-slate-900 print:bg-white print:p-0">
        <div class="mx-auto max-w-sm rounded-lg bg-white p-5 shadow-sm print:shadow-none">
            <div class="mb-4 text-center">
                <h1 class="text-lg font-black uppercase">Associação de Santana</h1>
                <div class="mt-1 text-sm">{{ pedido.mesa ? 'Talão de mesa' : 'Talão para levar' }}</div>
                <div class="mt-2 border-y border-slate-300 py-2 text-sm font-bold uppercase">
                    Este documento não é fatura
                </div>
            </div>

            <div class="mb-4 text-sm">
                <div class="flex justify-between"><span>Pedido</span><strong>#{{ pedido.id }}</strong></div>
                <div class="flex justify-between"><span>{{ pedido.mesa ? 'Mesa' : 'Tipo' }}</span><strong>{{ pedido.mesa?.designacao ?? 'Para levar' }}</strong></div>
                <div class="flex justify-between"><span>Data</span><strong>{{ agora }}</strong></div>
                <div class="flex justify-between"><span>Operador</span><strong>{{ operador }}</strong></div>
                <div class="flex justify-between"><span>Estado</span><strong>{{ pedido.estado }}</strong></div>
            </div>

            <div class="border-t border-slate-300 py-3">
                <div v-for="item in itensComValor" :key="item.id" class="mb-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <span>{{ item.quantidade }}x {{ item.produto?.nome }}</span>
                        <strong>{{ subtotal(item) }}</strong>
                    </div>
                    <div class="text-xs text-slate-500">{{ formatarPreco(item.preco_unitario) }} cada</div>
                </div>
                <div v-if="!itensComValor.length" class="py-3 text-center text-sm text-slate-500">Sem artigos cobrados.</div>
            </div>

            <div v-if="servicos.length" class="border-t border-slate-300 py-3 text-sm">
                <div class="mb-2 font-bold">Serviço</div>
                <div v-for="item in servicos" :key="item.id" class="flex justify-between">
                    <span>{{ item.quantidade }}x {{ item.produto?.nome }}</span>
                    <span>0.00€</span>
                </div>
            </div>

            <div class="border-t border-slate-300 pt-3">
                <div class="flex justify-between text-xl font-black">
                    <span>Total</span>
                    <span>{{ formatarPreco(total) }}</span>
                </div>
                <div class="mt-3 space-y-1 text-sm">
                    <div class="flex justify-between"><span>Recebido</span><strong>{{ formatarPreco(valorRecebido) }}</strong></div>
                    <div class="flex justify-between"><span>Troco</span><strong>{{ formatarPreco(troco) }}</strong></div>
                    <div v-if="doacao > 0" class="flex justify-between"><span>Doação associação</span><strong>{{ formatarPreco(doacao) }}</strong></div>
                </div>
            </div>

            <div class="mt-5 text-center text-xs text-slate-500">
                Obrigado pela preferência.
            </div>
        </div>

        <div class="mx-auto mt-4 flex max-w-sm gap-2 print:hidden">
            <button class="flex-1 rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white" @click="imprimir">Imprimir</button>
            <Link :href="route('pedidos.show', pedido.id)" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold">Voltar</Link>
        </div>
    </main>
</template>
