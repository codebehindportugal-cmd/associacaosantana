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
        <main class="bg-fundo pb-16 pt-10 sm:pb-20 sm:pt-14">
            <article class="mx-auto w-full max-w-[808px] px-4 sm:px-6">
                <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Legal</p>
                <h1 class="m-0 mt-3 text-[clamp(32px,4vw,44px)] font-extrabold leading-[1.1] tracking-[-0.01em]">{{ content.hero_titulo || 'Termos e Condições' }}</h1>
                <p class="m-0 mt-3 text-sm font-semibold text-suave">{{ content.hero_subtitulo || 'Última atualização: 15/06/2026' }}</p>

                <div v-if="paragraphs.length" class="mt-8 space-y-5 rounded-[14px] border border-linha bg-white p-5 text-[17px] leading-relaxed text-suave sm:p-9">
                    <p v-for="paragraph in paragraphs" :key="paragraph" class="m-0">{{ paragraph }}</p>
                </div>
            </article>
        </main>
    </PublicShell>
</template>
