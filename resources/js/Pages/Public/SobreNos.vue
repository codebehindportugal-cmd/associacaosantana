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
            <section class="relative isolate overflow-hidden bg-stone-800 px-5 py-24 text-white lg:px-8">
                <img src="/images/santa-ana.png" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-20">
                <div class="absolute inset-0 -z-10 bg-gradient-to-r from-stone-900/90 to-stone-800/60" />
                <div class="mx-auto max-w-5xl">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-400">Sobre Nós</p>
                    <h1 class="mt-4 max-w-3xl text-5xl font-bold leading-tight text-white sm:text-6xl">
                        {{ content.hero_titulo || 'A nossa história, a nossa gente' }}
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-stone-200">
                        {{ content.hero_subtitulo || 'A ARDC Santana é uma casa de cultura, desporto e convívio, construída pela dedicação de várias gerações.' }}
                    </p>
                </div>
            </section>

            <!-- Divisor dourado -->
            <div class="h-0.5 bg-gradient-to-r from-transparent via-amber-400 to-transparent" />

            <!-- História -->
            <section class="py-20 bg-amber-50">
                <div class="mx-auto grid max-w-7xl gap-12 px-5 lg:grid-cols-[0.9fr_1.1fr] lg:items-start lg:px-8">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">História</p>
                        <h2 class="mt-3 text-4xl font-bold text-stone-800 leading-tight">
                            {{ content.introducao || 'Uma associação com raízes locais.' }}
                        </h2>
                    </div>
                    <div class="space-y-5 text-lg leading-relaxed text-stone-600">
                        <p v-for="paragraph in paragraphs" :key="paragraph">{{ paragraph }}</p>
                        <p v-if="!paragraphs.length">
                            A ARDC Santana nasceu da vontade de criar um ponto de encontro para a comunidade de Santana, e continua ativa desde 1991 com eventos culturais, desportivos e momentos de convívio que aproximam gerações.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Stats -->
            <section v-if="stats.length" class="border-y border-amber-200 bg-white py-14">
                <div class="mx-auto grid max-w-7xl gap-4 px-5 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
                    <article v-for="stat in stats" :key="stat[1]" class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center shadow-sm">
                        <div class="text-4xl font-bold text-amber-700">{{ stat[0] }}</div>
                        <p class="mt-2 text-sm font-medium text-stone-600">{{ stat[1] }}</p>
                    </article>
                </div>
            </section>

            <!-- Galeria -->
            <section class="py-20 bg-white">
                <div class="mx-auto max-w-7xl px-5 lg:px-8">
                    <div class="mb-10">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Galeria</p>
                        <h2 class="mt-3 text-4xl font-bold text-stone-800">Memórias da Festa de Santa Ana.</h2>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <button
                            v-for="item in gallery"
                            :key="item[1]"
                            type="button"
                            class="group overflow-hidden rounded-xl border border-amber-200 bg-amber-50 shadow-sm transition hover:border-amber-400 hover:shadow-md"
                            @click="lightbox = item"
                        >
                            <img :src="item[0]" :alt="item[1]" class="aspect-[4/3] w-full object-contain p-6 transition duration-300 group-hover:scale-105" loading="lazy">
                        </button>
                    </div>
                </div>
            </section>

            <!-- Equipa -->
            <section class="border-t border-amber-100 bg-amber-50 py-20">
                <div class="mx-auto max-w-7xl px-5 lg:px-8">
                    <div class="mb-10">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Equipa diretiva</p>
                        <h2 class="mt-3 text-4xl font-bold text-stone-800">Pessoas ao serviço da associação.</h2>
                    </div>
                    <div class="overflow-hidden rounded-xl border border-amber-200 shadow-md">
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
        <div v-if="lightbox" class="fixed inset-0 z-50 grid place-items-center bg-stone-900/85 p-5 backdrop-blur-sm" @click.self="lightbox = null">
            <div class="max-w-3xl w-full overflow-hidden rounded-xl border border-amber-200 bg-white shadow-2xl">
                <img :src="lightbox[0]" :alt="lightbox[1]" class="max-h-[70vh] w-full object-contain bg-amber-50 p-4">
                <div class="flex items-center justify-between p-4 border-t border-amber-100">
                    <p class="font-semibold text-stone-700">{{ lightbox[1] }}</p>
                    <button type="button" class="rounded-md border border-amber-200 px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-amber-50 transition" @click="lightbox = null">Fechar</button>
                </div>
            </div>
        </div>
    </PublicShell>
</template>
