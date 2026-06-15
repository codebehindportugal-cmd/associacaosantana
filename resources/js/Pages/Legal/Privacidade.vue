<script setup>
import { Head, Link } from '@inertiajs/vue3';
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
    <Head :title="`${page?.titulo || 'Política de Privacidade'} | ARDC Santana`" />

    <PublicShell>
        <main class="py-16">
            <article class="mx-auto max-w-4xl px-5 leading-relaxed text-slate-700 lg:px-8">
                <h1 class="text-4xl font-bold text-slate-900">{{ content.hero_titulo || 'Política de Privacidade' }}</h1>
                <p class="mt-3 text-sm font-semibold text-slate-500">{{ content.hero_subtitulo || 'Última atualização: 15/06/2026' }}</p>

                <p v-for="paragraph in paragraphs" :key="paragraph" class="mt-6">{{ paragraph }}</p>

                <p class="mt-6">Pode contactar a associação através de <a href="mailto:ardcsantana@outlook.com" class="font-semibold underline">ardcsantana@outlook.com</a> e reclamar junto da CNPD em <a href="https://www.cnpd.pt" target="_blank" rel="noopener" class="font-semibold underline">www.cnpd.pt</a>.</p>

                <h2 class="mt-8 text-2xl font-bold text-slate-900">Cookies</h2>
                <p class="mt-3">Para mais informação consulte a <Link :href="route('legal.cookies')" class="font-semibold underline">Política de Cookies</Link>.</p>
            </article>
        </main>
    </PublicShell>
</template>
