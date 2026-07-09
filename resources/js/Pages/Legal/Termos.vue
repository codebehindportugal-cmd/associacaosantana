<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicShell from '@/Components/PublicShell.vue';

const props = defineProps({
    page: Object,
});

const content = computed(() => props.page?.conteudo || {});
const paragraphs = computed(() => (content.value.corpo || '')
    .split(/\n\s*\n/)
    .map((item) => item.trim())
    .filter(Boolean));
</script>

<template>
    <Head :title="`${page?.titulo || 'Termos e Condições'} | ARDC Santana`" />

    <PublicShell>
        <main class="py-20 bg-amber-50">
            <article class="mx-auto max-w-4xl px-5 lg:px-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Legal</p>
                <h1 class="mt-3 text-4xl font-bold text-stone-800">{{ content.hero_titulo || 'Termos e Condições' }}</h1>
                <p class="mt-3 text-sm font-semibold text-stone-400">{{ content.hero_subtitulo || 'Última atualização: 15/06/2026' }}</p>
                <div class="mt-8 space-y-5 leading-relaxed text-stone-600">
                    <p v-for="paragraph in paragraphs" :key="paragraph">{{ paragraph }}</p>
                </div>
            </article>
        </main>
    </PublicShell>
</template>
