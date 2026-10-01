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
    <Head :title="`${page?.titulo || 'Política de Cookies'} | ARDC Santana`" />

    <PublicShell>
        <main class="bg-fundo pb-16 pt-10 sm:pb-20 sm:pt-14">
            <article class="mx-auto w-full max-w-[808px] px-4 sm:px-6">
                <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Legal</p>
                <h1 class="m-0 mt-3 text-[clamp(32px,4vw,44px)] font-extrabold leading-[1.1] tracking-[-0.01em]">{{ content.hero_titulo || 'Política de Cookies' }}</h1>
                <p class="m-0 mt-3 text-sm font-semibold text-suave">{{ content.hero_subtitulo || 'Última atualização: 15/06/2026' }}</p>

                <div v-if="paragraphs.length" class="mt-8 space-y-5 rounded-[14px] border border-linha bg-white p-5 text-[17px] leading-relaxed text-suave sm:p-9">
                    <p v-for="paragraph in paragraphs" :key="paragraph" class="m-0">{{ paragraph }}</p>
                </div>

                <Link :href="route('legal.privacidade')" class="group mt-6 flex items-center justify-between gap-4 rounded-[14px] border border-verde-claro2 bg-verde-claro p-5 text-tinta no-underline transition hover:border-verde sm:px-6">
                    <span class="min-w-0">
                        <h2 class="m-0 text-[21px] font-extrabold text-verde-escuro">Privacidade</h2>
                        <p class="m-0 mt-1 text-[15px] text-suave">Consulte também a <span class="font-bold text-verde underline">Política de Privacidade</span>.</p>
                    </span>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-verde-escuro transition group-hover:translate-x-1"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </Link>
            </article>
        </main>
    </PublicShell>
</template>
