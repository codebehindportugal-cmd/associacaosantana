<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    produtos: Object,
});

const categoriaAtual = ref('todos');
const categorias = computed(() => Object.keys(props.produtos ?? {}));
const secoes = computed(() => ['todos', ...categorias.value]);
const produtosVisiveis = computed(() => {
    if (categoriaAtual.value === 'todos') return props.produtos ?? {};
    return { [categoriaAtual.value]: props.produtos?.[categoriaAtual.value] ?? [] };
});

const euros = (valor) => `${Number(valor ?? 0).toFixed(2)}€`;
</script>

<template>
    <main class="min-h-screen bg-amber-50 px-4 py-6 text-stone-800">
        <section class="mx-auto max-w-2xl">
            <header class="mb-6 flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">ARDC Santana</p>
                    <h1 class="mt-1 text-3xl font-bold text-stone-800">Preçário</h1>
                </div>
                <Link href="/" class="rounded-md border border-amber-200 bg-white px-3 py-2 text-sm font-semibold text-stone-700 shadow-sm transition hover:bg-amber-50">
                    ← Início
                </Link>
            </header>

            <!-- Filtros -->
            <div class="sticky top-0 z-10 -mx-4 mb-5 overflow-x-auto bg-amber-50/95 px-4 py-3 backdrop-blur">
                <div class="flex min-w-max gap-2">
                    <button
                        v-for="secao in secoes"
                        :key="secao"
                        type="button"
                        class="rounded-full px-4 py-2 text-sm font-bold transition"
                        :class="categoriaAtual === secao
                            ? 'bg-amber-600 text-white shadow-sm'
                            : 'border border-amber-200 bg-white text-stone-700 hover:bg-amber-50'"
                        @click="categoriaAtual = secao"
                    >
                        {{ secao === 'todos' ? 'Todos' : secao }}
                    </button>
                </div>
            </div>

            <div v-if="!categorias.length" class="rounded-xl border border-amber-200 bg-white p-8 text-center font-semibold text-stone-500 shadow-sm">
                Ainda não existem produtos disponíveis.
            </div>

            <div v-else class="space-y-4">
                <section
                    v-for="(items, categoria) in produtosVisiveis"
                    :key="categoria"
                    class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm"
                >
                    <div class="border-b border-amber-100 bg-amber-50 px-5 py-3">
                        <h2 class="text-lg font-bold text-stone-800">{{ categoria }}</h2>
                    </div>
                    <div class="divide-y divide-amber-50 px-5">
                        <div v-for="produto in items" :key="produto.id" class="flex items-center justify-between gap-4 py-3.5">
                            <div class="min-w-0">
                                <div class="font-semibold text-stone-800">{{ produto.nome }}</div>
                                <div class="mt-0.5 text-xs font-medium uppercase tracking-wide text-stone-400">{{ produto.categoria?.secao || 'produto' }}</div>
                            </div>
                            <div class="shrink-0 rounded-full bg-amber-600 px-3 py-1 text-sm font-bold text-white">
                                {{ euros(produto.preco) }}
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </main>
</template>
