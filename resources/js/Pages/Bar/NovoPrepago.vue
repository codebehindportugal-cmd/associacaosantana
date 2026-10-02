<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({ produtos: Array, pontos: { type: Array, default: () => [] } });
// Nomes dos postos POS ativos (Impressoras > Postos POS)
const pontosPadrao = computed(() => props.pontos ?? []);
const carrinho = ref([]);
const valorRecebido = ref('');
const trocoEntregue = ref('');
const pontoBar = ref('');
const form = useForm({ items: [], valor_recebido: 0, troco: 0, ponto_bar: '' });
const porCategoria = computed(() => (props.produtos ?? []).reduce((acc, p) => { const k = p.categoria?.nome ?? 'Outros'; if (!acc[k]) acc[k] = []; acc[k].push(p); return acc; }, {}));
const total = computed(() => carrinho.value.reduce((s, i) => s + Number(i.preco) * i.quantidade, 0));
const troco = computed(() => Math.max(0, Number(valorRecebido.value || 0) - total.value));
const trocoRegistado = computed(() => trocoEntregue.value === '' ? troco.value : Number(trocoEntregue.value || 0));
const doacao = computed(() => Math.max(0, troco.value - trocoRegistado.value));
const euros = (v) => Number(v ?? 0).toFixed(2).replace('.', ',') + '€';
const add = (produto) => { const item = carrinho.value.find((i) => i.produto_id === produto.id); item ? item.quantidade++ : carrinho.value.push({ produto_id: produto.id, nome: produto.nome, preco: produto.preco, quantidade: 1 }); };
const inc = (item, delta) => { item.quantidade += delta; carrinho.value = carrinho.value.filter((i) => i.quantidade > 0); };
const guardarPonto = () => localStorage.setItem('santana_ponto_bar', pontoBar.value || '');
const cobrar = () => {
    guardarPonto();
    form.ponto_bar = pontoBar.value;
    form.items = carrinho.value.map(({ produto_id, quantidade }) => ({ produto_id, quantidade }));
    form.valor_recebido = valorRecebido.value || total.value;
    form.troco = trocoRegistado.value;
    form.post(route('bar.store-prepago'));
};
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    pontoBar.value = params.get('ponto') || localStorage.getItem('santana_ponto_bar') || '';
});
</script>

<template>
    <AppLayout>
    <div class="font-sans text-tinta tabular-nums">
        <div class="flex flex-col gap-5">
            <Link :href="route('bar.index', pontoBar ? { ponto: pontoBar } : {})" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[15px] font-bold text-verde hover:text-verde-escuro">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                Voltar
            </Link>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-[30px] font-extrabold leading-tight">Pré-Pagamento</h1>
                    <p class="text-[15px] text-suave">O cliente paga ANTES de levantar.</p>
                </div>
                <label class="flex w-full flex-col gap-1.5 sm:w-80">
                    <span class="text-sm font-bold text-suave">Ponto de venda</span>
                    <input v-model="pontoBar" list="pontos-bar-prepago" class="h-12 rounded-[10px] border-linha-forte bg-white px-3.5 text-base font-bold text-tinta focus:border-verde focus:ring-verde" placeholder="Nome do ponto (ex: Café, Bar 1)" @change="guardarPonto">
                </label>
                <datalist id="pontos-bar-prepago"><option v-for="ponto in pontosPadrao" :key="ponto" :value="ponto" /></datalist>
            </div>

            <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                <section aria-label="Produtos" class="flex min-w-0 flex-col gap-[18px] rounded-[14px] border border-linha bg-white p-5">
                    <p v-if="!Object.keys(porCategoria).length" class="text-[15px] text-suave-2">Sem produtos disponíveis.</p>
                    <div v-for="(lista, categoria) in porCategoria" :key="categoria" class="flex flex-col gap-2.5">
                        <h2 class="text-sm font-extrabold uppercase tracking-[.06em] text-secao-bar">{{ categoria }}</h2>
                        <div class="grid grid-cols-2 gap-2.5 md:grid-cols-3 xl:grid-cols-4">
                            <button v-for="produto in lista" :key="produto.id" type="button"
                                class="flex min-h-[76px] flex-col justify-between gap-1.5 rounded-[10px] border border-l-[5px] border-linha-forte border-l-secao-bar bg-white p-3 text-left text-tinta transition hover:bg-fundo active:scale-[.98]"
                                @click="add(produto)">
                                <span class="text-[15px] font-bold">{{ produto.nome }}</span>
                                <span class="text-[15px] font-extrabold">{{ euros(produto.preco) }}</span>
                            </button>
                        </div>
                    </div>
                </section>

                <form aria-labelledby="carrinho-titulo" class="flex flex-col overflow-hidden rounded-[14px] border-2 border-laranja bg-white lg:sticky lg:top-4" @submit.prevent="cobrar">
                    <div class="flex flex-col gap-2 px-[18px] pt-[18px]">
                        <h2 id="carrinho-titulo" class="text-xl font-extrabold">Carrinho</h2>
                        <div v-if="!pontoBar" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto">Define o ponto de venda antes de cobrar.</div>
                    <div v-if="form.errors.ponto_bar" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto">{{ form.errors.ponto_bar }}</div>
                    <AvisoErros :errors="form.errors" :excluir="['ponto_bar', 'valor_recebido', 'troco']" />
                        <div class="flex flex-col border-t border-linha-fraca">
                            <div v-for="item in carrinho" :key="item.produto_id" class="flex items-center gap-2.5 border-b border-linha-fraca py-2.5">
                            <span class="min-w-0 flex-grow text-[15px] font-bold">{{ item.nome }}</span>
                            <div class="flex items-center gap-1">
                                <button type="button" :aria-label="'Menos ' + item.nome" class="h-11 w-11 rounded-[10px] border border-linha-forte bg-white text-xl font-bold text-tinta hover:bg-fundo" @click="inc(item, -1)">−</button>
                                <strong class="min-w-7 text-center text-[17px]">{{ item.quantidade }}</strong>
                                <button type="button" :aria-label="'Mais ' + item.nome" class="h-11 w-11 rounded-[10px] border border-linha-forte bg-white text-xl font-bold text-tinta hover:bg-fundo" @click="inc(item, 1)">+</button>
                            </div>
                            <strong class="min-w-16 text-right text-base">{{ euros(item.preco * item.quantidade) }}</strong>
                        </div>
                            <p v-if="!carrinho.length" class="py-3.5 text-[15px] text-suave-2">Escolhe produtos.</p>
                        </div>
                    </div>
                    <div class="mx-[18px] mt-3.5 flex items-baseline justify-between rounded-[10px] bg-laranja-claro px-3.5 py-3 text-laranja-texto">
                        <span class="font-bold">Total</span>
                        <span class="text-[34px] font-extrabold">{{ euros(total) }}</span>
                    </div>
                    <div class="flex flex-col gap-3 px-[18px] pb-[18px] pt-3.5">
                        <label class="flex flex-col gap-1.5">
                            <span class="text-sm font-bold text-suave">Valor recebido</span>
                            <span class="flex h-[52px] items-center rounded-[10px] border border-linha-forte px-3.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                                <input v-model.number="valorRecebido" type="number" min="0" step="0.01" inputmode="decimal" class="w-full flex-grow border-0 p-0 text-right text-[22px] font-extrabold focus:ring-0">
                                <span class="pl-1.5 text-suave-2">€</span>
                            </span>
                        </label>
                        <label class="flex flex-col gap-1.5">
                            <span class="text-sm font-bold text-suave">Troco entregue</span>
                            <span class="flex h-12 items-center rounded-[10px] border border-linha-forte px-3.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                                <input v-model.number="trocoEntregue" type="number" min="0" step="0.01" inputmode="decimal" class="w-full flex-grow border-0 p-0 text-right text-lg font-bold focus:ring-0" :placeholder="euros(troco)">
                                <span class="pl-1.5 text-suave-2">€</span>
                            </span>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="flex flex-col rounded-[10px] bg-fundo px-3 py-2.5"><span class="text-[13px] font-semibold text-suave">Troco</span><span class="text-xl font-extrabold">{{ euros(trocoRegistado) }}</span></div>
                            <div class="flex flex-col rounded-[10px] bg-verde-claro px-3 py-2.5"><span class="text-[13px] font-semibold text-verde-escuro">Doação</span><span class="text-xl font-extrabold text-verde-escuro">{{ euros(doacao) }}</span></div>
                        </div>
                        <button type="button" class="h-12 rounded-[10px] border border-dashed border-verde bg-white text-[15px] font-extrabold text-verde-escuro hover:bg-verde-claro" @click="trocoEntregue = 0">CLIENTE DOA O TROCO</button>
                        <div v-if="form.errors.valor_recebido || form.errors.troco" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto">{{ form.errors.valor_recebido || form.errors.troco }}</div>
                        <button type="submit" class="flex h-16 items-center justify-center gap-2.5 rounded-[10px] bg-laranja text-lg font-extrabold text-white transition hover:brightness-95 disabled:opacity-50" :disabled="!carrinho.length || !pontoBar || form.processing">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M3 9h18v8H3zM6 14h12v7H6z" /></svg>
                            COBRAR E IMPRIMIR SENHA
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </AppLayout>
</template>
