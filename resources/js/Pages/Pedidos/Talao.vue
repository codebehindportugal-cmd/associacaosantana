<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

const props = defineProps({ pedido: Object });

const total = computed(() => Number(props.pedido?.total ?? props.pedido?.total_calculado ?? 0));
const valorRecebido = computed(() => Number(props.pedido?.valor_recebido ?? total.value));
const troco = computed(() => Number(props.pedido?.troco ?? 0));
const doacao = computed(() => Number(props.pedido?.doacao ?? 0));
const operador = computed(() => props.pedido?.operador_nome ?? props.pedido?.user?.name ?? props.pedido?.pos?.nome ?? 'Sem operador');
const mesaLabel = computed(() => props.pedido?.mesa?.designacao ?? 'Para levar');
// Número da mesa em grande (sem o prefixo "Mesa"); nome livre ou "Para levar" em tamanho menor
const mesaGrande = computed(() => props.pedido?.mesa?.nome || props.pedido?.mesa?.numero || mesaLabel.value);
const mesaCurta = computed(() => String(mesaGrande.value).length <= 4);

const agruparItens = (items) => Object.values((items ?? []).reduce((grupos, item) => {
    const chave = [item.produto?.id, item.produto?.nome, item.preco_unitario, item.observacoes ?? ''].join('|');
    grupos[chave] ??= { ...item, id: chave, quantidade: 0 };
    grupos[chave].quantidade += Number(item.quantidade ?? 0);
    return grupos;
}, {}));

const itensAgrupados = computed(() => agruparItens(props.pedido?.items ?? []));
const itensComValor = computed(() => itensAgrupados.value.filter((item) => Number(item.preco_unitario) > 0));
const servicos = computed(() => itensAgrupados.value.filter((item) => Number(item.preco_unitario) === 0));

const formatarPreco = (valor) => `${Number(valor ?? 0).toFixed(2)} EUR`;
const subtotal = (item) => formatarPreco(Number(item.preco_unitario) * Number(item.quantidade));
const agora = new Date().toLocaleString('pt-PT');
const imprimir = () => window.print();

onMounted(() => {
    setTimeout(() => window.print(), 300);
});
</script>

<template>
    <main class="min-h-screen bg-fundo p-4 text-black tabular-nums print:bg-white print:p-0">
        <div class="mx-auto max-w-[302px] bg-white px-3 pb-10 pt-3.5 font-mono shadow print:shadow-none">
            <div class="text-center">
                <h1 class="text-[15px] font-bold uppercase">Associação de Santana</h1>
                <div class="mt-0.5 text-xs">{{ pedido.mesa ? 'Talão de mesa' : 'Talão para levar' }}</div>
            </div>
            <div class="my-2.5 border-y border-black py-1.5 text-center text-[11px] font-bold uppercase">
                Este documento não serve de fatura
            </div>

            <div class="pb-2.5 pt-0.5 text-center">
                <div class="text-[11px] uppercase tracking-[.12em]">{{ pedido.mesa ? 'Mesa' : 'Tipo' }}</div>
                <div class="font-sans font-extrabold leading-none" :class="mesaCurta ? 'text-[80px]' : 'text-[32px]'">{{ mesaGrande }}</div>
            </div>

            <div class="border-t border-dashed border-black py-2 text-xs leading-relaxed">
                <div class="flex justify-between gap-2"><span>Data</span><span class="font-bold">{{ agora }}</span></div>
                <div class="flex justify-between gap-2"><span>Operador</span><span class="font-bold">{{ operador }}</span></div>
                <div class="flex justify-between gap-2"><span>Estado</span><span class="font-bold">{{ pedido.estado }}</span></div>
            </div>

            <div class="border-t border-dashed border-black pt-2 text-[13px]">
                <div v-for="item in itensComValor" :key="item.id" class="mb-1.5">
                    <div class="flex justify-between gap-2">
                        <span>{{ item.quantidade }}x {{ item.produto?.nome }}</span>
                        <span class="font-bold">{{ subtotal(item) }}</span>
                    </div>
                    <div class="text-[11px] text-[#444444]">{{ formatarPreco(item.preco_unitario) }} cada</div>
                </div>
                <div v-if="!itensComValor.length" class="py-2 text-center text-xs text-[#444444]">Sem artigos cobrados.</div>
            </div>

            <div v-if="servicos.length" class="border-t border-dashed border-black py-2 text-xs leading-normal">
                <div class="mb-0.5 font-bold">Serviço</div>
                <div v-for="item in servicos" :key="item.id" class="flex justify-between gap-2">
                    <span>{{ item.quantidade }}x {{ item.produto?.nome }}</span>
                    <span>0.00 EUR</span>
                </div>
            </div>

            <div class="border-t border-black pt-2">
                <div class="flex items-baseline justify-between text-xl font-bold">
                    <span>TOTAL</span>
                    <span>{{ formatarPreco(total) }}</span>
                </div>
                <div class="mt-1.5 text-[13px] leading-relaxed">
                    <div class="flex justify-between"><span>Recebido</span><span>{{ formatarPreco(valorRecebido) }}</span></div>
                    <div class="flex justify-between font-bold"><span>Troco</span><span>{{ formatarPreco(troco) }}</span></div>
                    <div v-if="doacao > 0" class="flex justify-between"><span>Doação associação</span><span>{{ formatarPreco(doacao) }}</span></div>
                </div>
            </div>

            <div class="mt-[18px] text-center text-[11px]">
                Obrigado pela preferência.
            </div>
        </div>

        <div class="mx-auto mt-4 flex max-w-[302px] gap-2 font-sans print:hidden">
            <button class="flex h-11 flex-1 items-center justify-center gap-2 rounded-[10px] bg-escuro px-4 text-sm font-bold text-white" @click="imprimir">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" /></svg>
                Imprimir
            </button>
            <Link :href="route('pedidos.show', pedido.id)" class="flex h-11 items-center rounded-[10px] border border-linha-forte bg-white px-4 text-sm font-bold text-tinta">Voltar</Link>
        </div>
    </main>
</template>
