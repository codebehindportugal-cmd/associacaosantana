<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    evento: Object,
});

const activeIndex = ref(0);
const activeLightbox = ref(null);

const media = computed(() => props.evento.media ?? []);
const photos = computed(() => media.value.filter((item) => item.tipo === 'foto'));
const videos = computed(() => media.value.filter((item) => item.tipo === 'video'));
const facebookEmbedUrl = computed(() => {
    if (!props.evento.facebook_post_url) return null;
    const url = new URL('https://www.facebook.com/plugins/post.php');
    url.searchParams.set('href', props.evento.facebook_post_url);
    url.searchParams.set('show_text', 'true');
    url.searchParams.set('width', '500');
    return url.toString();
});
const slides = computed(() => {
    const eventMedia = media.value.map((item) => ({ ...item, source: item.caminho }));
    if (eventMedia.length) return eventMedia;
    return props.evento.cartaz
        ? [{ id: 'cartaz', tipo: 'foto', source: props.evento.cartaz, caminho: props.evento.cartaz, titulo: props.evento.titulo }]
        : [];
});
const activeSlide = computed(() => slides.value[activeIndex.value] ?? null);
const dataEvento = computed(() => {
    if (!props.evento.data_inicio) return props.evento.periodo || 'Sem data definida';
    if (props.evento.data_fim && props.evento.data_fim !== props.evento.data_inicio) {
        return `${props.evento.data_inicio} a ${props.evento.data_fim}`;
    }
    return props.evento.data_inicio;
});

const selectSlide = (index) => {
    if (!slides.value.length) return;
    activeIndex.value = (index + slides.value.length) % slides.value.length;
};
const previousSlide = () => selectSlide(activeIndex.value - 1);
const nextSlide = () => selectSlide(activeIndex.value + 1);
</script>

<template>
    <Head :title="`${evento.titulo} | ARDC Santana`">
        <meta head-key="description" name="description" :content="evento.descricao || `Vê fotografias e vídeos do evento ${evento.titulo} da ARDC Santana.`">
    </Head>

    <main class="min-h-screen bg-amber-50 text-stone-800">
        <!-- Nav -->
        <header class="sticky top-0 z-40 border-b border-amber-200/80 bg-amber-50/95 backdrop-blur-xl">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3.5 lg:px-8">
                <Link href="/" class="flex items-center gap-2 text-sm font-bold text-stone-800">
                    <img src="/images/santana-logo.png" alt="" class="h-8 w-8 rounded-full border border-amber-200 bg-white object-contain p-1">
                    ARDC Santana
                </Link>
                <Link href="/#eventos" class="rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 shadow-sm transition hover:bg-amber-50">
                    ← Eventos
                </Link>
            </nav>
        </header>

        <!-- Hero -->
        <section class="relative bg-stone-800 text-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-12 lg:grid-cols-[0.9fr_1.1fr] lg:px-8 lg:py-16">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-400">{{ evento.badge || 'Evento' }}</p>
                    <h1 class="mt-3 text-4xl font-bold leading-tight text-white sm:text-5xl">{{ evento.titulo }}</h1>
                    <p class="mt-3 text-xl font-semibold text-stone-300">{{ evento.subtitulo || evento.localizacao }}</p>
                    <p class="mt-4 max-w-2xl leading-relaxed text-stone-300">{{ evento.descricao }}</p>

                    <div class="mt-8 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border border-white/15 bg-white/10 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-400">Data</p>
                            <p class="mt-1 font-bold text-white">{{ dataEvento }}</p>
                        </div>
                        <div class="rounded-lg border border-white/15 bg-white/10 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-400">Local</p>
                            <p class="mt-1 font-bold text-white">{{ evento.localizacao || 'Por definir' }}</p>
                        </div>
                        <div class="rounded-lg border border-white/15 bg-white/10 p-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-400">Memórias</p>
                            <p class="mt-1 font-bold text-white">{{ facebookEmbedUrl ? 'Facebook' : `${media.length} ficheiros` }}</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl shadow-2xl">
                    <button v-if="activeSlide" type="button" class="block w-full bg-stone-900" @click="activeLightbox = activeSlide">
                        <img v-if="activeSlide.tipo === 'foto'" :src="activeSlide.source" :alt="activeSlide.titulo || evento.titulo" class="aspect-[16/10] w-full object-contain">
                        <video v-else :src="activeSlide.source" controls class="aspect-[16/10] w-full bg-black object-contain" />
                    </button>
                    <div v-else class="grid aspect-[16/10] place-items-center bg-stone-900/60 p-6 text-center font-semibold text-stone-400">
                        Este evento ainda não tem fotografias ou vídeos publicados.
                    </div>

                    <div v-if="slides.length > 1" class="flex items-center justify-between gap-3 bg-white/10 p-3">
                        <button type="button" class="rounded-md border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/15" @click="previousSlide">Anterior</button>
                        <p class="text-sm font-semibold text-stone-300">{{ activeIndex + 1 }} / {{ slides.length }}</p>
                        <button type="button" class="rounded-md border border-white/20 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/15" @click="nextSlide">Seguinte</button>
                    </div>
                </div>
            </div>
        </section>

        <div class="h-0.5 bg-gradient-to-r from-transparent via-amber-400 to-transparent" />

        <!-- Thumbnails -->
        <section v-if="slides.length > 1" class="bg-white py-5">
            <div class="mx-auto flex max-w-7xl gap-3 overflow-x-auto px-5 lg:px-8">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.id || slide.caminho"
                    type="button"
                    class="h-20 w-28 shrink-0 overflow-hidden rounded-lg border-2 transition"
                    :class="activeIndex === index ? 'border-amber-500 opacity-100' : 'border-transparent opacity-60 hover:opacity-90'"
                    @click="selectSlide(index)"
                >
                    <img v-if="slide.tipo === 'foto'" :src="slide.source" :alt="slide.titulo || evento.titulo" class="h-full w-full object-cover">
                    <video v-else :src="slide.source" class="h-full w-full object-cover" />
                </button>
            </div>
        </section>

        <!-- Facebook embed -->
        <section v-if="facebookEmbedUrl" class="bg-amber-50 py-16">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-[0.85fr_1.15fr] lg:px-8">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Facebook</p>
                    <h2 class="mt-3 text-4xl font-bold text-stone-800">Fotos e vídeos no post original.</h2>
                    <p class="mt-4 leading-relaxed text-stone-600">
                        As memórias deste evento estão alojadas no Facebook, para manter o site mais leve e rápido.
                    </p>
                    <a :href="evento.facebook_post_url" target="_blank" rel="noreferrer" class="mt-6 inline-flex rounded-md bg-amber-600 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-amber-700">
                        Abrir no Facebook
                    </a>
                </div>
                <div class="overflow-hidden rounded-xl border border-amber-200 bg-white p-4 shadow-sm">
                    <iframe
                        :src="facebookEmbedUrl"
                        title="Post do Facebook do evento"
                        class="mx-auto min-h-[560px] w-full max-w-[500px] border-0"
                        scrolling="no"
                        frameborder="0"
                        allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"
                        allowfullscreen
                    />
                </div>
            </div>
        </section>

        <!-- Fotografias -->
        <section v-if="!facebookEmbedUrl" class="py-16 bg-amber-50">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="mb-10">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Fotografias</p>
                    <h2 class="mt-3 text-4xl font-bold text-stone-800">Momentos registados durante o evento.</h2>
                </div>

                <div v-if="photos.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <button
                        v-for="photo in photos"
                        :key="photo.id"
                        type="button"
                        class="group relative overflow-hidden rounded-xl border border-amber-200 shadow-sm"
                        @click="activeLightbox = photo"
                    >
                        <img :src="photo.caminho" :alt="photo.titulo || evento.titulo" class="aspect-square w-full object-cover transition duration-500 group-hover:scale-105">
                        <div class="absolute inset-0 bg-amber-900/0 transition group-hover:bg-amber-900/30" />
                        <span class="absolute inset-x-0 bottom-0 translate-y-full bg-gradient-to-t from-stone-900/80 p-3 pt-8 text-left text-sm font-semibold text-white transition group-hover:translate-y-0">
                            {{ photo.titulo || evento.titulo }}
                        </span>
                    </button>
                </div>
                <div v-else class="rounded-xl border border-amber-200 bg-white p-8 text-stone-500">
                    Ainda não existem fotografias publicadas para este evento.
                </div>
            </div>
        </section>

        <!-- Vídeos -->
        <section v-if="!facebookEmbedUrl" class="bg-white py-16">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="mb-10">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Vídeos</p>
                    <h2 class="mt-3 text-4xl font-bold text-stone-800">Vídeos do evento.</h2>
                </div>

                <div v-if="videos.length" class="grid gap-5 lg:grid-cols-2">
                    <figure v-for="video in videos" :key="video.id" class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50 shadow-sm">
                        <video :src="video.caminho" controls class="aspect-video w-full bg-stone-900 object-contain" />
                        <figcaption class="p-4 font-semibold text-amber-800">{{ video.titulo || evento.titulo }}</figcaption>
                    </figure>
                </div>
                <div v-else class="rounded-xl border border-amber-200 bg-amber-50 p-8 text-stone-500">
                    Ainda não existem vídeos publicados para este evento.
                </div>
            </div>
        </section>

        <!-- Lightbox -->
        <div v-if="activeLightbox" class="fixed inset-0 z-50 grid place-items-center bg-stone-900/90 p-5 backdrop-blur-sm" @click.self="activeLightbox = null">
            <button type="button" class="absolute right-4 top-4 rounded-md border border-white/20 bg-white/10 px-4 py-2 text-sm font-semibold text-white backdrop-blur" @click="activeLightbox = null">Fechar</button>
            <div class="w-full max-w-6xl">
                <img v-if="activeLightbox.tipo === 'foto'" :src="activeLightbox.caminho" :alt="activeLightbox.titulo || evento.titulo" class="mx-auto max-h-[82vh] rounded-xl object-contain shadow-2xl">
                <video v-else :src="activeLightbox.caminho" controls autoplay class="mx-auto max-h-[82vh] w-full rounded-xl bg-black object-contain shadow-2xl" />
                <p class="mt-4 text-center font-semibold text-white">{{ activeLightbox.titulo || evento.titulo }}</p>
            </div>
        </div>
    </main>
</template>
