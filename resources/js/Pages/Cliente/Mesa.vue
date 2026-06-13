<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    token: String,
    pedido: Object,
    produtos: Object,
});

const categoriaAtual = ref(Object.keys(props.produtos ?? {})[0] || '');
const quantidades = ref({});
const observacoes = ref({});
const form = useForm({ produto_id: null, quantidade: 1, observacoes: '' });

const categorias = computed(() => Object.keys(props.produtos ?? {}));
const lista = computed(() => props.produtos?.[categoriaAtual.value] ?? []);

const quantidade = (produto) => Number(quantidades.value[produto.id] ?? 1);
const alterarQuantidade = (produto, delta) => {
    quantidades.value[produto.id] = Math.min(10, Math.max(1, quantidade(produto) + delta));
};

const adicionar = (produto) => {
    if (!props.pedido?.disponivel) {
        return;
    }

    form.produto_id = produto.id;
    form.quantidade = quantidade(produto);
    form.observacoes = observacoes.value[produto.id] || '';
    form.post(route('cliente.item', props.token), {
        preserveScroll: true,
        onSuccess: () => {
            observacoes.value[produto.id] = '';
        },
    });
};
</script>

<template>
    <main class="min-h-screen bg-slate-950 text-white">
        <header class="sticky top-0 z-20 border-b border-white/10 bg-slate-950/95 px-4 py-4 backdrop-blur">
            <div class="mx-auto flex max-w-xl items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-black uppercase tracking-wide text-emerald-300">ARDC Santana</div>
                    <h1 class="text-2xl font-black">Mesa {{ pedido.mesa }}</h1>
                </div>
                <Link :href="route('cliente.confirmacao', token)" class="rounded-full bg-white/10 px-3 py-2 text-xs font-black">Enviados</Link>
            </div>
        </header>

        <section class="mx-auto max-w-xl px-4 py-5">
            <div v-if="!pedido.disponivel" class="rounded-2xl border border-amber-400/40 bg-amber-400/10 p-5 text-center">
                <h2 class="text-xl font-black">Pedido indisponivel</h2>
                <p class="mt-2 text-sm text-amber-100">Este pedido ja foi fechado ou cancelado. Chame um elemento da equipa.</p>
            </div>

            <template v-else>
                <div class="mb-4 overflow-x-auto pb-2">
                    <div class="flex min-w-max gap-2">
                        <button
                            v-for="categoria in categorias"
                            :key="categoria"
                            type="button"
                            class="rounded-full px-4 py-2 text-sm font-black"
                            :class="categoria === categoriaAtual ? 'bg-emerald-500 text-slate-950' : 'bg-white/10 text-white'"
                            @click="categoriaAtual = categoria"
                        >
                            {{ categoria }}
                        </button>
                    </div>
                </div>

                <div v-if="form.errors.pedido" class="mb-4 rounded-xl bg-red-600 p-3 text-sm font-bold">
                    {{ form.errors.pedido }}
                </div>
                <div v-if="form.errors.produto_id || form.errors.quantidade || form.errors.observacoes" class="mb-4 rounded-xl bg-red-600 p-3 text-sm font-bold">
                    Nao foi possivel adicionar esse produto.
                </div>

                <div class="grid gap-3">
                    <article v-for="produto in lista" :key="produto.id" class="rounded-2xl bg-white p-4 text-slate-950 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-lg font-black">{{ produto.nome }}</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500">{{ produto.categoria?.nome || 'Produto' }}</p>
                        </div>

                        <label class="mb-4 block">
                            <span class="text-xs font-black uppercase text-slate-500">Observacoes</span>
                            <textarea
                                v-model="observacoes[produto.id]"
                                rows="2"
                                maxlength="255"
                                class="mt-1 w-full rounded-xl border-slate-200 text-sm"
                                placeholder="Ex.: sem cebola, bem passado"
                            ></textarea>
                        </label>

                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center overflow-hidden rounded-full border border-slate-200">
                                <button type="button" class="h-11 w-12 bg-slate-100 text-xl font-black" @click="alterarQuantidade(produto, -1)">-</button>
                                <span class="w-12 text-center text-lg font-black">{{ quantidade(produto) }}</span>
                                <button type="button" class="h-11 w-12 bg-slate-100 text-xl font-black" @click="alterarQuantidade(produto, 1)">+</button>
                            </div>
                            <button
                                type="button"
                                class="min-h-11 flex-1 rounded-full bg-emerald-500 px-4 py-3 text-sm font-black text-slate-950 disabled:opacity-60"
                                :disabled="form.processing"
                                @click="adicionar(produto)"
                            >
                                {{ form.processing && form.produto_id === produto.id ? 'A enviar...' : 'Adicionar' }}
                            </button>
                        </div>
                    </article>
                </div>
            </template>
        </section>
    </main>
</template>
