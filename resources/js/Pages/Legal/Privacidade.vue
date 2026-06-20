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
        <main class="py-20 bg-amber-50">
            <article class="mx-auto max-w-4xl px-5 lg:px-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Legal</p>
                <h1 class="mt-3 text-4xl font-bold text-stone-800">{{ content.hero_titulo || 'Política de Privacidade' }}</h1>
                <p class="mt-3 text-sm font-semibold text-stone-400">{{ content.hero_subtitulo || 'Última atualização: 15/06/2026' }}</p>

                <div class="mt-8 space-y-5 leading-relaxed text-stone-600">
                    <p v-for="paragraph in paragraphs" :key="paragraph">{{ paragraph }}</p>
                    <p>Pode contactar a associação através de <a href="mailto:ardcsantana@outlook.com" class="font-semibold text-amber-700 hover:text-amber-900 transition underline">ardcsantana@outlook.com</a> e reclamar junto da CNPD em <a href="https://www.cnpd.pt" target="_blank" rel="noopener" class="font-semibold text-amber-700 hover:text-amber-900 transition underline">www.cnpd.pt</a>.</p>
                </div>

                <div class="mt-10 rounded-xl border border-amber-200 bg-white p-6">
                    <h2 class="text-2xl font-bold text-stone-800">Cookies</h2>
                    <p class="mt-3 text-stone-600">Para mais informação consulte a <Link :href="route('legal.cookies')" class="font-semibold text-amber-700 hover:text-amber-900 transition underline">Política de Cookies</Link>.</p>
                </div>
            </article>
        </main>
    </PublicShell>
</template>
