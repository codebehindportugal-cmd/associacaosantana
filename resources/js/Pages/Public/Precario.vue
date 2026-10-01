<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicShell from '@/Components/PublicShell.vue';

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

const euros = (valor) => `${Number(valor ?? 0).toFixed(2)} €`;

// Cor da secção (sempre acompanhada do nome escrito)
const corSecao = (secao) => ({
    grelhados: 'text-secao-grelhados',
    cozinha: 'text-secao-cozinha',
    bebidas: 'text-secao-bar',
    bar: 'text-secao-bar',
    sobremesas: 'text-secao-sobremesas',
    acompanhamentos: 'text-secao-acompanhamentos',
    servico: 'text-secao-servico',
}[secao] || 'text-suave-2');
</script>

<template>
    <Head title="Preçário da Festa" />
    <PublicShell>
        <main class="min-h-screen pb-16 pt-10 sm:pt-14">
            <section class="mx-auto w-full max-w-[760px] px-4 sm:px-6">
                <header class="mb-6">
                    <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">ARDC Santana</p>
                    <h1 class="m-0 mt-2 text-[clamp(32px,4vw,44px)] font-extrabold leading-[1.1] tracking-[-0.01em]">Preçário da Festa</h1>
                    <p class="m-0 mt-2 text-[17px] text-suave">Preços praticados no bar e restaurante durante a festa de Santana.</p>
                </header>

                <div class="sticky top-[72px] z-10 -mx-4 mb-5 overflow-x-auto border-y border-linha bg-fundo/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 max-[900px]:top-[128px]">
                    <div class="flex min-w-max gap-2" role="tablist" aria-label="Categorias">
                        <button
                            v-for="secao in secoes"
                            :key="secao"
                            type="button"
                            role="tab"
                            :aria-selected="categoriaAtual === secao"
                            class="flex h-11 items-center rounded-full border px-[18px] text-[15px] font-bold transition"
                            :class="categoriaAtual === secao
                                ? 'border-verde bg-verde text-white'
                                : 'border-linha-forte bg-white text-tinta hover:border-verde hover:text-verde'"
                            @click="categoriaAtual = secao"
                        >
                            {{ secao === 'todos' ? 'Todos' : secao }}
                        </button>
                    </div>
                </div>

                <div v-if="!categorias.length" class="rounded-[14px] border border-linha bg-white p-8 text-center text-[17px] font-semibold text-suave">
                    Ainda não existem produtos disponíveis.
                </div>

                <TransitionGroup v-else tag="div" class="space-y-4" enter-active-class="transition duration-200" enter-from-class="opacity-0 translate-y-1">
                    <section
                        v-for="(items, categoria) in produtosVisiveis"
                        :key="categoria"
                        class="overflow-hidden rounded-[14px] border border-linha bg-white"
                    >
                        <div class="flex items-center justify-between gap-3 border-b border-linha bg-fundo px-5 py-3.5">
                            <h2 class="m-0 text-xl font-extrabold">{{ categoria }}</h2>
                            <span class="text-sm text-suave-2">{{ items.length }} {{ items.length === 1 ? 'produto' : 'produtos' }}</span>
                        </div>
                        <div class="px-5">
                            <div
                                v-for="produto in items"
                                :key="produto.id"
                                class="flex min-h-[72px] items-center justify-between gap-4 border-b border-linha-fraca py-3.5 last:border-b-0"
                            >
                                <div class="min-w-0">
                                    <div class="text-[17px] font-bold">{{ produto.nome }}</div>
                                    <div class="mt-0.5 flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.08em]" :class="corSecao(produto.categoria?.secao)">
                                        <span class="h-2 w-2 rounded-full bg-current" aria-hidden="true"></span>
                                        {{ produto.categoria?.secao || 'produto' }}
                                    </div>
                                </div>
                                <div class="shrink-0 text-lg font-extrabold tabular-nums">
                                    {{ euros(produto.preco) }}
                                </div>
                            </div>
                        </div>
                    </section>
                </TransitionGroup>
            </section>
        </main>
    </PublicShell>
</template>
