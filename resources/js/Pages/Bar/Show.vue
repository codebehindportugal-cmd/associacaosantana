<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ pedido: Object, produtos: Array });
const mostrarProdutos = ref(true);
const valorRecebido = ref('');
const troco = ref('');
const itemForm = useForm({ pedido_id: props.pedido.id, produto_id: '', quantidade: 1 });
const fecharForm = useForm({ valor_recebido: '', troco: 0 });
const porCategoria = computed(() => (props.produtos ?? []).reduce((acc, p) => { const k = p.categoria?.nome ?? 'Outros'; if (!acc[k]) acc[k] = []; acc[k].push(p); return acc; }, {}));
const total = computed(() => Number(props.pedido.total_calculado ?? props.pedido.total ?? 0));
const trocoCalc = computed(() => Math.max(0, Number(valorRecebido.value || total.value) - total.value));
const euros = (v) => Number(v ?? 0).toFixed(2).replace('.', ',') + '€';
const add = (produto) => { itemForm.produto_id = produto.id; itemForm.post(route('pedido-items.store'), { preserveScroll: true }); };
const fechar = () => { fecharForm.valor_recebido = valorRecebido.value || total.value; fecharForm.troco = troco.value === '' ? trocoCalc.value : troco.value; fecharForm.patch(route('bar.fechar', props.pedido.id)); };
</script>

<template>
    <AppLayout>
    <div class="font-sans text-tinta tabular-nums">
        <div class="flex flex-col gap-5">
            <Link :href="route('bar.index')" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[15px] font-bold text-verde hover:text-verde-escuro">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                Voltar
            </Link>
            <div class="flex flex-col gap-1.5">
                <h1 class="text-[30px] font-extrabold leading-tight">Conta Bar #{{ pedido.id }}</h1>
                <p class="text-[15px] text-suave">{{ pedido.observacoes || 'Sem identificação' }} · {{ pedido.estado }}</p>
            </div>

            <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="flex min-w-0 flex-col gap-5">
                    <section aria-labelledby="itens-ja" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                        <h2 id="itens-ja" class="border-b border-linha-fraca px-5 py-4 text-lg font-extrabold">Itens já adicionados</h2>
                        <div v-if="!pedido.items?.length" class="p-6 text-center text-[15px] text-suave-2">Ainda sem itens nesta conta.</div>
                        <div v-for="item in pedido.items" :key="item.id" class="flex items-center gap-3 border-b border-linha-fraca px-5 py-3 last:border-b-0">
                            <div class="flex min-w-0 flex-grow flex-col gap-0.5">
                                <span class="text-[17px] font-extrabold">{{ item.quantidade }}x {{ item.produto?.nome }}</span>
                                <span class="text-sm text-suave-2">{{ item.produto?.categoria?.nome }}</span>
                            </div>
                            <span class="text-[17px] font-extrabold">{{ euros(item.quantidade * item.preco_unitario) }}</span>
                        </div>
                    </section>

                    <section aria-labelledby="adicionar" class="flex flex-col gap-4 rounded-[14px] border border-linha bg-white p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 id="adicionar" class="text-lg font-extrabold">Adicionar Itens</h2>
                            <button type="button" :aria-expanded="mostrarProdutos" class="h-11 rounded-[10px] border border-linha-forte bg-white px-3.5 text-sm font-bold text-tinta hover:bg-fundo" @click="mostrarProdutos = !mostrarProdutos">
                                {{ mostrarProdutos ? 'Esconder produtos' : 'Mostrar produtos' }}
                            </button>
                        </div>
                                <AvisoErros :errors="itemForm.errors" />
                        <template v-if="mostrarProdutos">
                            <p class="text-sm text-suave-2">Um toque num produto junta 1 unidade à conta.</p>
                            <div v-for="(lista, categoria) in porCategoria" :key="categoria" class="flex flex-col gap-2.5">
                                <h3 class="text-sm font-extrabold uppercase tracking-[.06em] text-secao-bar">{{ categoria }}</h3>
                                <div class="grid grid-cols-2 gap-2.5 md:grid-cols-3 xl:grid-cols-4">
                                    <button v-for="produto in lista" :key="produto.id" type="button" :disabled="itemForm.processing"
                                        class="flex min-h-[72px] flex-col justify-between gap-1.5 rounded-[10px] border border-l-[5px] border-linha-forte border-l-secao-bar bg-white p-3 text-left text-tinta transition hover:bg-fundo active:scale-[.98] disabled:opacity-60"
                                        @click="add(produto)">
                                        <span class="text-[15px] font-bold">{{ produto.nome }}</span>
                                        <span class="text-[15px] font-extrabold">{{ euros(produto.preco) }}</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </section>
                </div>

                <form aria-labelledby="fechar-titulo" class="flex flex-col overflow-hidden rounded-[14px] border-2 border-laranja bg-white lg:sticky lg:top-4" @submit.prevent="fechar">
                    <div class="flex flex-col gap-0.5 bg-laranja-claro px-[18px] py-4">
                        <span id="fechar-titulo" class="text-sm font-bold text-laranja-texto">Total atual</span>
                        <span class="text-[40px] font-extrabold leading-tight text-laranja-texto">{{ euros(total) }}</span>
                    </div>
                    <div class="flex flex-col gap-3.5 p-[18px]">
                        <label class="flex flex-col gap-1.5">
                            <span class="text-sm font-bold text-suave">Valor recebido</span>
                            <span class="flex h-[52px] items-center rounded-[10px] border border-linha-forte px-3.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                                <input v-model.number="valorRecebido" type="number" min="0" step="0.01" inputmode="decimal" class="w-full flex-grow border-0 p-0 text-right text-[22px] font-extrabold focus:ring-0" :placeholder="Number(total).toFixed(2).replace('.', ',')">
                                <span class="pl-1.5 text-suave-2">€</span>
                            </span>
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-sm font-bold text-suave">Troco entregue</span>
                            <span class="flex h-12 items-center rounded-[10px] border border-linha-forte px-3.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                                <input v-model.number="troco" type="number" min="0" step="0.01" inputmode="decimal" class="w-full flex-grow border-0 p-0 text-right text-lg font-bold focus:ring-0" :placeholder="euros(trocoCalc)">
                                <span class="pl-1.5 text-suave-2">€</span>
                            </span>
                            <span class="text-[13px] text-suave-2">Vazio = troco calculado ({{ euros(trocoCalc) }})</span>
                        </label>
                        <div v-if="fecharForm.errors.valor_recebido || fecharForm.errors.troco" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto">{{ fecharForm.errors.valor_recebido || fecharForm.errors.troco }}</div>
                        <button type="submit" :disabled="fecharForm.processing" class="h-[60px] rounded-[10px] bg-laranja text-lg font-extrabold text-white transition hover:brightness-95 disabled:opacity-60">FECHAR E IMPRIMIR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </AppLayout>
</template>
