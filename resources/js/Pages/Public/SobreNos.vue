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
            <section class="relative isolate overflow-hidden bg-slate-900 px-5 py-24 text-white lg:px-8">
                <img src="/images/santa-ana.png" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-25">
                <div class="mx-auto max-w-5xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-slate-300">Sobre Nós</p>
                    <h1 class="mt-4 max-w-3xl text-5xl font-bold leading-tight sm:text-6xl">{{ content.hero_titulo || 'A nossa história, a nossa gente' }}</h1>
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-200">
                        {{ content.hero_subtitulo || 'A ARDC Santana é uma casa de cultura, desporto e convívio, construída pela dedicação de várias gerações.' }}
                    </p>
                </div>
            </section>

            <section class="py-16">
                <div class="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">História</p>
                        <h2 class="mt-3 text-4xl font-bold text-slate-900">{{ content.introducao || 'Uma associação com raízes locais.' }}</h2>
                    </div>
                    <div class="space-y-5 text-lg leading-relaxed text-slate-600">
                        <p v-for="paragraph in paragraphs" :key="paragraph">{{ paragraph }}</p>
                    </div>
                </div>
            </section>

            <section class="border-y border-slate-200 bg-slate-50 py-14">
                <div class="mx-auto grid max-w-7xl gap-4 px-5 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
                    <article v-for="stat in stats" :key="stat[1]" class="rounded-lg border border-slate-200 bg-white p-6 text-center">
                        <div class="text-4xl font-bold text-slate-900">{{ stat[0] }}</div>
                        <p class="mt-2 text-sm font-medium text-slate-600">{{ stat[1] }}</p>
                    </article>
                </div>
            </section>

            <section class="py-16">
                <div class="mx-auto max-w-7xl px-5 lg:px-8">
                    <div class="mb-8">
                        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Galeria</p>
                        <h2 class="mt-3 text-4xl font-bold text-slate-900">Memórias da Festa de Santa Ana.</h2>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <button v-for="item in gallery" :key="item[1]" type="button" class="overflow-hidden rounded-lg border border-slate-200 bg-slate-100" @click="lightbox = item">
                            <img :src="item[0]" :alt="item[1]" class="aspect-[4/3] w-full object-contain p-6 transition hover:scale-105" loading="lazy">
                        </button>
                    </div>
                </div>
            </section>

            <section class="border-t border-slate-200 bg-slate-50 py-16">
                <div class="mx-auto max-w-7xl px-5 lg:px-8">
                    <div class="mb-8">
                        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Equipa diretiva</p>
                        <h2 class="mt-3 text-4xl font-bold text-slate-900">Pessoas ao serviço da associação.</h2>
                    </div>
                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
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

        <div v-if="lightbox" class="fixed inset-0 z-50 grid place-items-center bg-black/80 p-5" @click.self="lightbox = null">
            <div class="max-w-3xl rounded-lg bg-white p-4">
                <img :src="lightbox[0]" :alt="lightbox[1]" class="max-h-[75vh] w-full object-contain">
                <button type="button" class="mt-4 rounded-md bg-slate-900 px-4 py-2 font-semibold text-white" @click="lightbox = null">Fechar</button>
            </div>
        </div>
    </PublicShell>
</template>
