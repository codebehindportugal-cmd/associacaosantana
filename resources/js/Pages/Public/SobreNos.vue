<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PublicShell from '@/Components/PublicShell.vue';

const props = defineProps({
    page: Object,
});

const lightbox = ref(null);
const content = computed(() => props.page?.conteudo || {});
const paragraphs = computed(() => (content.value.corpo || '')
    .split(/\n\s*\n/)
    .map((item) => item.trim())
    .filter(Boolean));

const stats = computed(() => (content.value.extra || '')
    .split('\n')
    .map((line) => line.split('|').map((part) => part.trim()))
    .filter((parts) => parts[0] && parts[1]));

const gallery = [
    ['/images/santana-logo.png', 'Símbolo da associação'],
    ['/images/santa-ana.png', 'Santa Ana'],
    ['/images/santana-logo.png', 'Momentos da festa'],
    ['/images/santa-ana.png', 'Comunidade'],
];
</script>

<template>
    <Head :title="`${page?.titulo || 'Sobre Nós'} | ARDC Santana`" />

    <PublicShell>
        <main>
            <!-- Hero -->
            <section class="relative isolate overflow-hidden border-b border-linha bg-white py-14 sm:py-20">
                <img src="/images/santa-ana.png" alt="" class="pointer-events-none absolute -right-10 top-1/2 -z-10 hidden h-[130%] -translate-y-1/2 object-contain opacity-[0.08] md:block">
                <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                    <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Sobre Nós</p>
                    <h1 class="m-0 mt-4 max-w-3xl text-[clamp(36px,5vw,60px)] font-extrabold leading-[1.05] tracking-[-0.02em]">
                        {{ content.hero_titulo || 'A nossa história, a nossa gente' }}
                    </h1>
                    <p class="m-0 mt-5 max-w-2xl text-lg text-suave">
                        {{ content.hero_subtitulo || 'A ARDC Santana é uma casa de cultura, desporto e convívio, construída pela dedicação de várias gerações.' }}
                    </p>
                </div>
            </section>

            <!-- História -->
            <section class="border-b border-linha bg-fundo py-14 sm:py-20">
                <div class="mx-auto grid w-full max-w-[1120px] gap-8 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:items-start lg:gap-12">
                    <div>
                        <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">História</p>
                        <h2 class="m-0 mt-3 text-[clamp(28px,3.2vw,40px)] font-extrabold leading-[1.15]">
                            {{ content.introducao || 'Uma associação com raízes locais.' }}
                        </h2>
                    </div>
                    <div class="space-y-5 text-lg leading-relaxed text-suave">
                        <p v-for="paragraph in paragraphs" :key="paragraph" class="m-0">{{ paragraph }}</p>
                        <p v-if="!paragraphs.length" class="m-0">
                            A ARDC Santana nasceu da vontade de criar um ponto de encontro para a comunidade de Santana, e continua ativa desde 1991 com eventos culturais, desportivos e momentos de convívio que aproximam gerações.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Números -->
            <section v-if="stats.length" class="border-b border-linha bg-white py-10 sm:py-12">
                <div class="mx-auto grid w-full max-w-[1120px] grid-cols-2 gap-3 px-4 sm:px-6 lg:grid-cols-[repeat(auto-fit,minmax(0,1fr))] lg:gap-4">
                    <article v-for="stat in stats" :key="stat[1]" class="rounded-[14px] border border-linha bg-fundo p-5 text-center sm:p-6">
                        <div class="text-[32px] font-extrabold leading-none text-verde sm:text-[38px]">{{ stat[0] }}</div>
                        <p class="m-0 mt-2 text-sm font-semibold text-suave">{{ stat[1] }}</p>
                    </article>
                </div>
            </section>

            <!-- Galeria -->
            <section class="border-b border-linha bg-fundo py-14 sm:py-20">
                <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                    <div class="mb-8">
                        <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Galeria</p>
                        <h2 class="m-0 mt-3 text-[clamp(28px,3.2vw,40px)] font-extrabold leading-[1.15]">Memórias da Festa de Santa Ana.</h2>
                    </div>
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <button
                            v-for="item in gallery"
                            :key="item[1]"
                            type="button"
                            class="group flex flex-col overflow-hidden rounded-[14px] border border-linha bg-white text-left transition hover:border-verde focus:outline-none focus-visible:ring-2 focus-visible:ring-verde"
                            @click="lightbox = item"
                        >
                            <span class="block bg-linha-fraca">
                                <img :src="item[0]" :alt="item[1]" class="aspect-[4/3] w-full object-contain p-6 transition duration-300 group-hover:scale-105" loading="lazy">
                            </span>
                            <span class="flex min-h-11 items-center justify-between gap-2 border-t border-linha px-3.5 py-2.5 text-sm font-bold text-tinta">
                                {{ item[1] }}
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-suave-2 group-hover:text-verde"><path d="M15 3h6v6M21 3l-7 7M9 21H3v-6M3 21l7-7" /></svg>
                            </span>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Equipa -->
            <section class="bg-white py-14 sm:py-20">
                <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                    <div class="mb-8">
                        <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Equipa diretiva</p>
                        <h2 class="m-0 mt-3 text-[clamp(28px,3.2vw,40px)] font-extrabold leading-[1.15]">Pessoas ao serviço da associação.</h2>
                    </div>
                    <div class="overflow-hidden rounded-[14px] border border-linha bg-linha">
                        <img
                            src="/images/grupo-recortado.jpg"
                            alt="Grupo ao serviço da associação"
                            class="aspect-[16/9] w-full object-cover"
                            loading="lazy"
                        >
                    </div>
                </div>
            </section>
        </main>

        <!-- Lightbox -->
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="lightbox" class="fixed inset-0 z-50 grid place-items-center bg-escuro/85 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" :aria-label="lightbox[1]" @click.self="lightbox = null">
                <div class="w-full max-w-3xl overflow-hidden rounded-[14px] bg-white shadow-2xl">
                    <img :src="lightbox[0]" :alt="lightbox[1]" class="max-h-[70vh] w-full bg-fundo object-contain p-4">
                    <div class="flex items-center justify-between gap-3 border-t border-linha p-4">
                        <p class="m-0 font-bold text-tinta">{{ lightbox[1] }}</p>
                        <button type="button" class="inline-flex h-11 items-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo" @click="lightbox = null">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </PublicShell>
</template>
