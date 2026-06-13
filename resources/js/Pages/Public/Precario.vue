<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    produtos: Object,
});

const categoriaAtual = ref('todos');
const categorias = computed(() => Object.keys(props.produtos ?? {}));
const secoes = computed(() => ['todos', ...categorias.value]);
const produtosVisiveis = computed(() => {
    if (categoriaAtual.value === 'todos') {
        return props.produtos ?? {};
    }

    return {
        [categoriaAtual.value]: props.produtos?.[categoriaAtual.value] ?? [],
    };
});

const euros = (valor) => `${Number(valor ?? 0).toFixed(2)}€`;
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-4 py-5 text-white">
        <section class="mx-auto max-w-2xl">
            <header class="mb-5">
                <div class="text-xs font-black uppercase text-emerald-300">ARDC Santana</div>
                <h1 class="mt-1 text-3xl font-black">Preçário</h1>
            </header>

            <div class="sticky top-0 z-10 -mx-4 mb-5 overflow-x-auto bg-slate-950 px-4 py-3">
                <div class="flex min-w-max gap-2">
                    <button
                        v-for="secao in secoes"
                        :key="secao"
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-black"
                        :class="categoriaAtual === secao ? 'bg-emerald-500 text-slate-950' : 'bg-white/10 text-white'"
                        @click="categoriaAtual = secao"
                    >
                        {{ secao === 'todos' ? 'Todos' : secao }}
                    </button>
                </div>
            </div>

            <div v-if="!categorias.length" class="rounded-2xl bg-white p-5 text-center font-bold text-slate-600">
                Ainda nao existem produtos disponiveis.
            </div>

            <div v-else class="space-y-5">
                <section v-for="(items, categoria) in produtosVisiveis" :key="categoria" class="rounded-2xl bg-white p-4 text-slate-950 shadow-sm">
                    <h2 class="mb-3 text-xl font-black">{{ categoria }}</h2>
                    <div class="divide-y divide-slate-100">
                        <div v-for="produto in items" :key="produto.id" class="flex items-start justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <div class="break-words text-base font-black">{{ produto.nome }}</div>
                                <div class="mt-1 text-xs font-semibold uppercase text-slate-500">{{ produto.categoria?.secao || 'produto' }}</div>
                            </div>
                            <div class="shrink-0 rounded-full bg-slate-950 px-3 py-1 text-sm font-black text-white">
                                {{ euros(produto.preco) }}
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </main>
</template>
