<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({ produtos: Array, pontos: { type: Array, default: () => [] } });
// Nomes dos postos POS ativos (Impressoras > Postos POS)
const pontosPadrao = computed(() => props.pontos ?? []);
const carrinho = ref([]);
const pontoBar = ref('');
const form = useForm({ observacoes: '', items: [], ponto_bar: '' });
const porCategoria = computed(() => (props.produtos ?? []).reduce((acc, p) => { const k = p.categoria?.nome ?? 'Outros'; if (!acc[k]) acc[k] = []; acc[k].push(p); return acc; }, {}));
const total = computed(() => carrinho.value.reduce((s, i) => s + Number(i.preco) * i.quantidade, 0));
const euros = (v) => Number(v ?? 0).toFixed(2).replace('.', ',') + '€';
const add = (produto) => { const item = carrinho.value.find((i) => i.produto_id === produto.id); item ? item.quantidade++ : carrinho.value.push({ produto_id: produto.id, nome: produto.nome, preco: produto.preco, quantidade: 1 }); };
const inc = (item, delta) => { item.quantidade += delta; carrinho.value = carrinho.value.filter((i) => i.quantidade > 0); };
const guardarPonto = () => localStorage.setItem('santana_ponto_bar', pontoBar.value || '');
const abrir = () => { guardarPonto(); form.ponto_bar = pontoBar.value; form.items = carrinho.value.map(({ produto_id, quantidade }) => ({ produto_id, quantidade })); form.post(route('bar.store-conta')); };
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    pontoBar.value = params.get('ponto') || localStorage.getItem('santana_ponto_bar') || '';
});
</script>

<template>
    <main class="min-h-screen bg-fundo font-sans text-tinta tabular-nums">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
            <Link :href="route('bar.index', pontoBar ? { ponto: pontoBar } : {})" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[15px] font-bold text-verde hover:text-verde-escuro">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                Voltar
            </Link>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-[30px] font-extrabold leading-tight">Nova Conta Bar</h1>
                    <p class="text-[15px] text-suave">Modo conta: paga no final.</p>
                </div>
                <label class="flex w-full flex-col gap-1.5 sm:w-80">
                    <span class="text-sm font-bold text-suave">Ponto de venda</span>
                    <input v-model="pontoBar" list="pontos-bar-conta" class="h-12 rounded-[10px] border-linha-forte bg-white px-3.5 text-base font-bold text-tinta focus:border-verde focus:ring-verde" placeholder="Nome do ponto (ex: Café, Bar 1)" @change="guardarPonto">
                </label>
                <datalist id="pontos-bar-conta"><option v-for="ponto in pontosPadrao" :key="ponto" :value="ponto" /></datalist>
            </div>

            <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
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

                <form aria-labelledby="conta-titulo" class="flex flex-col gap-3.5 rounded-[14px] border border-linha bg-white p-[18px] lg:sticky lg:top-4" @submit.prevent="abrir">
                    <h2 id="conta-titulo" class="text-xl font-extrabold">Conta</h2>
                    <div v-if="!pontoBar" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto">Define o ponto de venda antes de abrir conta.</div>
                    <div v-if="form.errors.ponto_bar" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto">{{ form.errors.ponto_bar }}</div>
                    <label class="flex flex-col gap-1.5">
                        <span class="text-sm font-bold text-suave">Nome/identificação</span>
                        <input v-model="form.observacoes" class="h-12 rounded-[10px] border-linha-forte px-3.5 text-base focus:border-verde focus:ring-verde" placeholder="Mesa 3, João, balcão...">
                    </label>
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
                        <p v-if="!carrinho.length" class="py-3.5 text-[15px] text-suave-2">Toca nos produtos para os juntar à conta.</p>
                    </div>
                    <div class="flex items-baseline justify-between rounded-[10px] bg-fundo px-3.5 py-3">
                        <span class="font-bold text-suave">Total atual</span>
                        <span class="text-[30px] font-extrabold">{{ euros(total) }}</span>
                    </div>
                    <button type="submit" class="h-[60px] rounded-[10px] bg-verde text-lg font-extrabold text-white transition hover:bg-verde-escuro disabled:opacity-50" :disabled="!carrinho.length || !pontoBar || form.processing">ABRIR CONTA</button>
                </form>
            </div>
        </div>
    </main>
</template>
