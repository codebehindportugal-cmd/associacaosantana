<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
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

const euros = (valor) => `${Number(valor ?? 0).toFixed(2)} EUR`;
</script>

<template>
    <PublicShell>
        <main class="min-h-screen px-4 py-10" style="background:#0d0a05;color:#fffdf8">
            <section class="mx-auto max-w-2xl">
                <header class="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em]" style="color:#C9A84C">ARDC Santana</p>
                        <h1 class="mt-1 text-3xl font-bold" style="color:#fffdf8">Preçário</h1>
                    </div>
                    <Link
                        href="/"
                        class="rounded-md px-3 py-2 text-sm font-semibold shadow-sm transition"
                        style="border:1px solid rgba(212,175,55,0.25);background:rgba(212,175,55,0.08);color:#C9A84C"
                    >
                        Inicio
                    </Link>
                </header>

                <div class="sticky top-[84px] z-10 -mx-4 mb-5 overflow-x-auto px-4 py-3 backdrop-blur" style="background:rgba(13,10,5,0.88);border-block:1px solid rgba(212,175,55,0.08)">
                    <div class="flex min-w-max gap-2">
                        <button
                            v-for="secao in secoes"
                            :key="secao"
                            type="button"
                            class="rounded-full px-4 py-2 text-sm font-bold transition"
                            :style="categoriaAtual === secao
                                ? 'background:#D4AF37;color:#0d0a05;box-shadow:0 12px 30px rgba(212,175,55,0.22)'
                                : 'border:1px solid rgba(212,175,55,0.2);background:rgba(255,253,248,0.05);color:rgba(255,253,248,0.72)'"
                            @click="categoriaAtual = secao"
                        >
                            {{ secao === 'todos' ? 'Todos' : secao }}
                        </button>
                    </div>
                </div>

                <div v-if="!categorias.length" class="rounded-xl p-8 text-center font-semibold shadow-sm" style="border:1px solid rgba(212,175,55,0.18);background:rgba(255,253,248,0.05);color:rgba(255,253,248,0.55)">
                    Ainda nao existem produtos disponiveis.
                </div>

                <div v-else class="space-y-4">
                    <section
                        v-for="(items, categoria) in produtosVisiveis"
                        :key="categoria"
                        class="overflow-hidden rounded-xl shadow-sm"
                        style="border:1px solid rgba(212,175,55,0.16);background:rgba(255,253,248,0.055);backdrop-filter:blur(12px)"
                    >
                        <div class="px-5 py-3" style="border-bottom:1px solid rgba(212,175,55,0.12);background:rgba(212,175,55,0.06)">
                            <h2 class="text-lg font-bold" style="color:#fffdf8">{{ categoria }}</h2>
                        </div>
                        <div class="px-5">
                            <div
                                v-for="produto in items"
                                :key="produto.id"
                                class="flex items-center justify-between gap-4 py-3.5"
                                style="border-bottom:1px solid rgba(212,175,55,0.06)"
                            >
                                <div class="min-w-0">
                                    <div class="font-semibold" style="color:#fffdf8">{{ produto.nome }}</div>
                                    <div class="mt-0.5 text-xs font-medium uppercase tracking-wide" style="color:rgba(255,253,248,0.38)">{{ produto.categoria?.secao || 'produto' }}</div>
                                </div>
                                <div class="shrink-0 rounded-full px-3 py-1 text-sm font-bold" style="background:#D4AF37;color:#0d0a05">
                                    {{ euros(produto.preco) }}
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </section>
        </main>
    </PublicShell>
</template>
