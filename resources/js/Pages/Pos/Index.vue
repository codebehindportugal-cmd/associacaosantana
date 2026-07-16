<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ChamarComissaoModal from '@/Components/ChamarComissaoModal.vue';
import ChamadaFuncionarioAlert from '@/Components/ChamadaFuncionarioAlert.vue';
import ComissaoChamadasAlert from '@/Components/ComissaoChamadasAlert.vue';

const props = defineProps({
    posNome: String,
    pontoBar: String,
    caixaAberta: Boolean,
    produtos: Array,
    senhasHoje: Array,
});

const agora = ref(new Date());
const carrinho = ref([]);
const recebido = ref('');
const trocoEntregue = ref('');
const form = useForm({ items: [], valor_recebido: 0, troco: 0 });
let relogio = null;
let refresh = null;

// Agrupar produtos por categoria (igual ao restaurante)
const secoes = computed(() => {
    const map = new Map();
    (props.produtos ?? []).forEach((p) => {
        const nome = p.categoria?.nome ?? 'Outros';
        if (!map.has(nome)) map.set(nome, { nome, produtos: [] });
        map.get(nome).produtos.push(p);
    });
    return [...map.values()];
});

const secaoAtiva = ref(null);
const secaoAtivaKey = computed(() => secaoAtiva.value ?? secoes.value[0]?.nome ?? null);
const produtosVisiveis = computed(() => {
    if (!secaoAtivaKey.value) return props.produtos ?? [];
    return secoes.value.find((s) => s.nome === secaoAtivaKey.value)?.produtos ?? [];
});
const tituloAtivo = computed(() => secaoAtivaKey.value ?? 'Produtos');

// Cor do botão por secção (igual ao restaurante)
const secaoClasse = (produto) => ({
    bebidas: 'bg-blue-600',
    frango: 'bg-red-700',
    cozinha: 'bg-orange-600',
    comida: 'bg-orange-600',
    acompanhamentos: 'bg-emerald-700',
    sobremesas: 'bg-purple-600',
}[produto.categoria?.secao] || 'bg-gray-700');

// Estilo com imagem de fundo quando disponível
const btnStyle = (produto) => {
    if (!produto.imagem) return {};
    return {
        backgroundImage: 'linear-gradient(rgba(0,0,0,0.42),rgba(0,0,0,0.58)),url(/storage/' + produto.imagem + ')',
        backgroundSize: 'cover',
        backgroundPosition: 'center',
    };
};

const total = computed(() => carrinho.value.reduce((soma, item) => soma + Number(item.preco) * item.quantidade, 0));
const cartQty = computed(() => Object.fromEntries(carrinho.value.map((i) => [i.produto_id, i.quantidade])));
const troco = computed(() => Math.max(0, Number(recebido.value || 0) - total.value));
const trocoRegistado = computed(() => trocoEntregue.value === '' ? troco.value : Number(trocoEntregue.value || 0));
const doacao = computed(() => Math.max(0, troco.value - trocoRegistado.value));
const euros = (valor) => Number(valor ?? 0).toFixed(2) + '€';
const hora = (data) => new Date(data).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
const logout = () => router.post(route('pos.logout'));
const chamandoComissao = ref(false);

const adicionar = (produto) => {
    const item = carrinho.value.find((linha) => linha.produto_id === produto.id);
    item ? item.quantidade++ : carrinho.value.push({ produto_id: produto.id, nome: produto.nome, preco: produto.preco, quantidade: 1 });
};

const alterar = (item, delta) => {
    item.quantidade += delta;
    carrinho.value = carrinho.value.filter((linha) => linha.quantidade > 0);
};

const cobrar = () => {
    form.items = carrinho.value.map(({ produto_id, quantidade }) => ({ produto_id, quantidade }));
    form.valor_recebido = recebido.value || total.value;
    form.troco = trocoRegistado.value;
    form.post(route('pos.prepago.store'));
};

onMounted(() => {
    relogio = setInterval(() => (agora.value = new Date()), 1000);
    refresh = setInterval(() => router.reload({ only: ['caixaAberta', 'senhasHoje'], preserveScroll: true }), 20000);
});

onBeforeUnmount(() => {
    clearInterval(relogio);
    clearInterval(refresh);
});
</script>

<template>
    <ChamadaFuncionarioAlert />
    <ComissaoChamadasAlert />
    <main class="pos-screen flex h-screen overflow-hidden bg-gray-900 p-3 text-white sm:p-4">
        <div class="flex min-h-0 w-full flex-col">
            <header class="pos-header mb-3 flex shrink-0 flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="pos-title text-2xl font-black sm:text-3xl">POS {{ pontoBar }}</h1>
                    <p class="pos-subtitle font-bold text-gray-300">{{ posNome }} · {{ agora.toLocaleTimeString('pt-PT') }}</p>
                </div>
                <div class="flex gap-2">
                    <button class="rounded-lg bg-amber-500 px-3 py-2 text-sm font-black text-black sm:px-4 sm:py-3" @click="chamandoComissao = true">🎉 COMISSÃO</button>
                    <button class="pos-logout rounded-lg bg-red-600 px-4 py-2 font-black sm:px-5 sm:py-3" @click="logout">LOGOUT</button>
                </div>
            </header>

            <div v-if="!caixaAberta" class="pos-alert mb-3 shrink-0 rounded-lg bg-red-700 p-3 text-center text-lg font-black sm:p-4">
                Caixa fechada para {{ pontoBar }}. Abre a caixa no backoffice antes de vender.
            </div>

            <div class="pos-layout grid min-h-0 flex-1 gap-3 lg:grid-cols-[minmax(0,1fr)_380px] xl:grid-cols-[minmax(0,1fr)_410px]">
                <div class="pos-left flex min-h-0 flex-col gap-3">
                    <section class="pos-panel flex min-h-0 flex-1 flex-col rounded-lg bg-gray-800 p-3 sm:p-4">
                        <!-- Abas de categoria -->
                        <div v-if="secoes.length > 1" class="pos-tabs mb-3 flex shrink-0 gap-2 overflow-x-auto pb-1">
                            <button
                                v-for="s in secoes"
                                :key="s.nome"
                                class="pos-tab shrink-0 rounded-lg px-4 py-2 text-sm font-black uppercase tracking-wide transition"
                                :class="secaoAtivaKey === s.nome ? 'bg-emerald-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600'"
                                @click="secaoAtiva = s.nome"
                            >
                                {{ s.nome }}
                            </button>
                        </div>
                        <h2 class="pos-section-title mb-3 shrink-0 text-xl font-black sm:text-2xl">{{ tituloAtivo.toUpperCase() }}</h2>
                        <div v-if="produtosVisiveis.length === 0" class="flex flex-1 items-center justify-center text-gray-400 font-bold">
                            Sem produtos nesta secção.
                        </div>
                        <div v-else class="pos-product-grid grid min-h-0 flex-1 auto-rows-min grid-cols-3 gap-2 overflow-y-auto pr-1 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                            <button
                                v-for="produto in produtosVisiveis"
                                :key="produto.id"
                                class="pos-product-btn min-h-20 rounded-lg p-3 text-left font-black disabled:opacity-50 sm:min-h-24 relative overflow-hidden"
                                :class="produto.imagem ? 'bg-gray-900' : secaoClasse(produto)"
                                :style="btnStyle(produto)"
                                :disabled="!caixaAberta"
                                @click="adicionar(produto)"
                            >
                                <span v-if="cartQty[produto.id]" class="absolute top-1 right-1 flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs font-black text-gray-900 z-10">{{ cartQty[produto.id] }}</span>
                                <span class="pos-product-name block text-lg relative z-10">{{ produto.nome }}</span>
                                <span class="pos-product-price mt-1 block text-xl sm:text-2xl relative z-10">{{ euros(produto.preco) }}</span>
                            </button>
                        </div>
                    </section>

                    <section class="pos-sales-panel shrink-0 rounded-lg bg-gray-800 p-3">
                        <h2 class="pos-sales-title mb-2 text-lg font-black">ÚLTIMAS SENHAS</h2>
                        <div class="pos-sales-grid grid max-h-32 gap-2 overflow-y-auto md:grid-cols-3 xl:grid-cols-4">
                            <div v-for="pedido in senhasHoje" :key="pedido.id" class="pos-sale-card rounded-lg bg-gray-900 p-2">
                                <div class="pos-sale-number text-xl font-black">#{{ pedido.numero_senha }}</div>
                                <div class="pos-sale-time text-xs text-gray-400">{{ hora(pedido.created_at) }}</div>
                                <div class="pos-sale-items mt-1 truncate text-xs">{{ pedido.items.map((item) => `${item.quantidade}x ${item.produto?.nome}`).join(', ') }}</div>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="pos-cart flex min-h-0 flex-col rounded-lg bg-gray-800 p-3 sm:p-4">
                    <h2 class="pos-section-title mb-2 shrink-0 text-xl font-black sm:text-2xl">SENHA</h2>
                    <div class="pos-cart-list min-h-0 flex-1 overflow-y-auto pr-1">
                        <div v-if="!carrinho.length" class="pos-empty rounded-lg bg-gray-900 p-5 text-center font-bold text-gray-300">Escolhe os produtos.</div>
                        <div v-for="item in carrinho" :key="item.produto_id" class="pos-cart-item mb-2 rounded-lg bg-gray-900 p-3">
                            <div class="pos-cart-name font-black">{{ item.nome }}</div>
                            <div class="pos-cart-row mt-2 flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <button class="pos-qty-btn h-11 w-11 rounded bg-gray-700 text-xl font-black" @click="alterar(item, -1)">-</button>
                                    <strong class="pos-qty text-xl">{{ item.quantidade }}</strong>
                                    <button class="pos-qty-btn h-11 w-11 rounded bg-emerald-600 text-xl font-black" @click="alterar(item, 1)">+</button>
                                </div>
                                <strong class="pos-line-total font-mono text-xl">{{ euros(item.preco * item.quantidade) }}</strong>
                            </div>
                        </div>
                    </div>
                    <div class="pos-total my-3 shrink-0 rounded-lg bg-emerald-700 p-3">
                        <div class="pos-total-label font-bold">Total</div>
                        <div class="pos-total-value text-3xl font-black sm:text-4xl">{{ euros(total) }}</div>
                    </div>
                    <input v-model="recebido" inputmode="decimal" class="pos-input w-full shrink-0 rounded-lg border-gray-700 bg-gray-900 p-3 text-xl font-black text-white" placeholder="Recebido">
                    <input v-model="trocoEntregue" inputmode="decimal" class="pos-input mt-2 w-full shrink-0 rounded-lg border-gray-700 bg-gray-900 p-3 text-xl font-black text-white" :placeholder="`Troco entregue ${euros(troco)}`">
                    <div class="pos-change-grid mt-2 grid shrink-0 grid-cols-2 gap-2 text-base font-black">
                        <div class="rounded-lg bg-gray-900 p-2 text-emerald-400">Troco: {{ euros(trocoRegistado) }}</div>
                        <div class="rounded-lg bg-gray-900 p-2 text-amber-300">Doação: {{ euros(doacao) }}</div>
                    </div>
                    <button type="button" class="pos-donate mt-2 w-full shrink-0 rounded-lg bg-amber-500 p-3 font-black text-gray-950" @click="trocoEntregue = 0">CLIENTE DOA O TROCO</button>
                    <div v-if="form.errors.ponto_bar || form.errors.valor_recebido || form.errors.troco" class="pos-error mt-2 shrink-0 rounded bg-red-700 p-2 font-bold">{{ form.errors.ponto_bar || form.errors.valor_recebido || form.errors.troco }}</div>
                    <button class="pos-pay mt-3 w-full shrink-0 rounded-lg bg-emerald-600 p-4 text-lg font-black disabled:opacity-50" :disabled="!caixaAberta || !carrinho.length || form.processing" @click="cobrar">
                        COBRAR E TIRAR SENHA
                    </button>
                </aside>
            </div>
        </div>

        <ChamarComissaoModal
            v-if="chamandoComissao"
            :operador-nome="posNome"
            @fechar="chamandoComissao = false"
        />
    </main>
</template>

<style scoped>
.pos-screen {
    height: 100dvh;
    padding: clamp(0.45rem, 1.1vh, 1rem);
}

.pos-header,
.pos-layout,
.pos-left {
    gap: clamp(0.4rem, 1vh, 0.75rem);
}

.pos-header {
    margin-bottom: clamp(0.35rem, 1vh, 0.75rem);
}

.pos-title {
    font-size: clamp(1.25rem, 2.6vw, 1.875rem);
    line-height: 1.05;
}

.pos-subtitle,
.pos-logout,
.pos-alert,
.pos-donate,
.pos-error,
.pos-pay {
    font-size: clamp(0.78rem, 1.45vw, 1rem);
}

.pos-logout,
.pos-donate,
.pos-pay,
.pos-input {
    padding-block: clamp(0.45rem, 1.15vh, 0.9rem);
}

.pos-alert {
    margin-bottom: clamp(0.35rem, 1vh, 0.75rem);
    padding: clamp(0.45rem, 1.1vh, 1rem);
}

.pos-panel,
.pos-sales-panel,
.pos-cart {
    padding: clamp(0.55rem, 1.3vh, 1rem);
}

.pos-section-title {
    margin-bottom: clamp(0.35rem, 0.9vh, 0.75rem);
    font-size: clamp(1rem, 1.9vw, 1.5rem);
    line-height: 1.1;
}

.pos-product-grid {
    gap: clamp(0.35rem, 0.9vh, 0.5rem);
}

.pos-product-btn {
    min-height: clamp(3.5rem, 9vh, 6rem);
    padding: clamp(0.35rem, 1vh, 0.75rem);
}

.pos-product-name {
    font-size: clamp(0.9rem, 1.6vw, 1.125rem);
    line-height: 1.1;
}

.pos-product-price {
    font-size: clamp(1.05rem, 2vw, 1.5rem);
    line-height: 1.05;
}

.pos-sales-panel {
    max-height: clamp(5rem, 17vh, 8.5rem);
}

.pos-sales-title {
    margin-bottom: clamp(0.25rem, 0.7vh, 0.5rem);
    font-size: clamp(0.8rem, 1.4vw, 1.125rem);
}

.pos-sales-grid {
    max-height: clamp(3.3rem, 11vh, 8rem);
    gap: clamp(0.3rem, 0.8vh, 0.5rem);
}

.pos-sale-card {
    padding: clamp(0.35rem, 0.9vh, 0.5rem);
}

.pos-sale-number {
    font-size: clamp(1rem, 1.8vw, 1.25rem);
    line-height: 1;
}

.pos-sale-time,
.pos-sale-items {
    font-size: clamp(0.65rem, 1.1vw, 0.75rem);
}

.pos-cart-item,
.pos-empty {
    padding: clamp(0.45rem, 1vh, 0.75rem);
}

.pos-cart-name,
.pos-line-total,
.pos-qty,
.pos-input {
    font-size: clamp(0.95rem, 1.65vw, 1.25rem);
}

.pos-cart-row {
    margin-top: clamp(0.3rem, 0.8vh, 0.5rem);
}

.pos-qty-btn {
    width: clamp(2.25rem, 5.8vh, 2.75rem);
    height: clamp(2.25rem, 5.8vh, 2.75rem);
    font-size: clamp(1rem, 2vw, 1.25rem);
}

.pos-total {
    margin-block: clamp(0.4rem, 1vh, 0.75rem);
    padding: clamp(0.5rem, 1.1vh, 0.75rem);
}

.pos-total-label,
.pos-change-grid {
    font-size: clamp(0.78rem, 1.35vw, 1rem);
}

.pos-total-value {
    font-size: clamp(1.7rem, 4vw, 2.25rem);
    line-height: 1;
}

.pos-input + .pos-input,
.pos-change-grid,
.pos-donate,
.pos-error {
    margin-top: clamp(0.35rem, 0.85vh, 0.5rem);
}

.pos-pay {
    margin-top: clamp(0.45rem, 1vh, 0.75rem);}

@media (max-height: 700px) {
    .pos-screen {
        padding: 0.4rem;
    }

    .pos-sales-panel {
        max-height: 4.7rem;
    }

    .pos-sale-items {
        display: none;
    }

    .pos-product-btn {
        min-height: 3.2rem;
    }
}

@media (max-height: 600px) {
    .pos-subtitle,
    .pos-sales-panel {
        display: none;
    }

    .pos-product-btn {
        min-height: 2.8rem;
    }

    .pos-total-label {
        display: none;
    }
}

@media (max-width: 1023px) {
    .pos-layout {
        grid-template-rows: minmax(0, 1fr) minmax(16rem, 38vh);
    }

    .pos-cart {
        min-height: 0;
    }

    .pos-sales-panel {
        display: none;
    }
}
</style>

