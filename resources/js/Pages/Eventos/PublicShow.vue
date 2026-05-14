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
const slides = computed(() => {
    const eventMedia = media.value.map((item) => ({
        ...item,
        source: item.caminho,
    }));

    if (eventMedia.length) return eventMedia;

    return props.evento.cartaz
        ? [{
            id: 'cartaz',
            tipo: 'foto',
            source: props.evento.cartaz,
            caminho: props.evento.cartaz,
            titulo: props.evento.titulo,
        }]
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
const openLightbox = (item) => {
    activeLightbox.value = item;
};
const closeLightbox = () => {
    activeLightbox.value = null;
};
</script>

<template>
    <Head :title="`${evento.titulo} | ARDC Santana`">
        <meta
            head-key="description"
            name="description"
            :content="evento.descricao || `Vê fotografias e vídeos do evento ${evento.titulo} da ARDC Santana.`"
        >
    </Head>

    <main class="min-h-screen bg-[#fbfaf4] text-[#18231d]">
        <header class="border-b border-black/5 bg-white/90 backdrop-blur">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8">
                <Link href="/" class="text-sm font-black uppercase tracking-[0.16em] text-[#214c38]">ARDC Santana</Link>
                <Link href="/#eventos" class="rounded-md border border-[#214c38]/20 px-4 py-2 text-sm font-black text-[#214c38] hover:bg-[#eef4ea]">
                    Voltar aos eventos
                </Link>
            </nav>
        </header>

        <section class="bg-[#214c38] text-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-10 lg:grid-cols-[0.9fr_1.1fr] lg:px-8 lg:py-14">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-[#f0c85b]">{{ evento.badge || 'Evento' }}</p>
                    <h1 class="mt-3 text-4xl font-black leading-none sm:text-6xl">{{ evento.titulo }}</h1>
                    <p class="mt-4 text-xl font-black text-white/80">{{ evento.subtitulo || evento.localizacao }}</p>
                    <p class="mt-5 max-w-2xl leading-relaxed text-white/80">{{ evento.descricao }}</p>

                    <div class="mt-8 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-md bg-white/10 p-4">
                            <p class="text-xs font-black uppercase text-white/55">Data</p>
                            <p class="mt-1 font-black">{{ dataEvento }}</p>
                        </div>
                        <div class="rounded-md bg-white/10 p-4">
                            <p class="text-xs font-black uppercase text-white/55">Local</p>
                            <p class="mt-1 font-black">{{ evento.localizacao || 'Por definir' }}</p>
                        </div>
                        <div class="rounded-md bg-white/10 p-4">
                            <p class="text-xs font-black uppercase text-white/55">Memórias</p>
                            <p class="mt-1 font-black">{{ media.length }} ficheiros</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg bg-black/25 shadow-2xl shadow-black/25">
                    <button v-if="activeSlide" type="button" class="block w-full bg-black" @click="openLightbox(activeSlide)">
                        <img v-if="activeSlide.tipo === 'foto'" :src="activeSlide.source" :alt="activeSlide.titulo || evento.titulo" class="aspect-[16/10] w-full object-contain">
                        <video v-else :src="activeSlide.source" controls class="aspect-[16/10] w-full bg-black object-contain"></video>
                    </button>
                    <div v-else class="grid aspect-[16/10] place-items-center bg-black/30 p-6 text-center font-black text-white/70">
                        Este evento ainda não tem fotografias ou vídeos publicados.
                    </div>

                    <div v-if="slides.length > 1" class="flex items-center justify-between gap-3 bg-white p-3 text-[#17241d]">
                        <button type="button" class="rounded-md bg-[#214c38] px-4 py-2 font-black text-white" @click="previousSlide">Anterior</button>
                        <p class="text-sm font-black">{{ activeIndex + 1 }} / {{ slides.length }}</p>
                        <button type="button" class="rounded-md bg-[#214c38] px-4 py-2 font-black text-white" @click="nextSlide">Seguinte</button>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="slides.length > 1" class="bg-white py-5">
            <div class="mx-auto flex max-w-7xl gap-3 overflow-x-auto px-5 lg:px-8">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.id || slide.caminho"
                    type="button"
                    class="h-24 w-32 shrink-0 overflow-hidden rounded-md border-2 bg-[#214c38]"
                    :class="activeIndex === index ? 'border-[#e5b84b]' : 'border-transparent opacity-70 hover:opacity-100'"
                    @click="selectSlide(index)"
                >
                    <img v-if="slide.tipo === 'foto'" :src="slide.source" :alt="slide.titulo || evento.titulo" class="h-full w-full object-cover">
                    <video v-else :src="slide.source" class="h-full w-full object-cover"></video>
                </button>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-5 py-14 lg:px-8">
            <div class="mb-8">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-[#9a7621]">Fotografias</p>
                <h2 class="mt-2 text-4xl font-black text-[#17241d]">Momentos registados durante o evento.</h2>
            </div>

            <div v-if="photos.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <button
                    v-for="photo in photos"
                    :key="photo.id"
                    type="button"
                    class="group relative overflow-hidden rounded-lg bg-[#214c38]"
                    @click="openLightbox(photo)"
                >
                    <img :src="photo.caminho" :alt="photo.titulo || evento.titulo" class="aspect-square w-full object-cover transition duration-700 group-hover:scale-105">
                    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-3 pt-12 text-left text-sm font-black text-white">
                        {{ photo.titulo || evento.titulo }}
                    </span>
                </button>
            </div>
            <div v-else class="rounded-lg border border-[#dfe7dc] bg-white p-6 text-[#536458]">
                Ainda não existem fotografias publicadas para este evento.
            </div>
        </section>

        <section class="bg-white">
            <div class="mx-auto max-w-7xl px-5 py-14 lg:px-8">
                <div class="mb-8">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-[#9a7621]">Vídeos</p>
                    <h2 class="mt-2 text-4xl font-black text-[#17241d]">Vídeos do evento.</h2>
                </div>

                <div v-if="videos.length" class="grid gap-5 lg:grid-cols-2">
                    <figure v-for="video in videos" :key="video.id" class="overflow-hidden rounded-lg border border-[#dfe7dc] bg-[#fbfaf4]">
                        <video :src="video.caminho" controls class="aspect-video w-full bg-black object-contain"></video>
                        <figcaption class="p-4 font-black text-[#214c38]">{{ video.titulo || evento.titulo }}</figcaption>
                    </figure>
                </div>
                <div v-else class="rounded-lg border border-[#dfe7dc] bg-[#fbfaf4] p-6 text-[#536458]">
                    Ainda não existem vídeos publicados para este evento.
                </div>
            </div>
        </section>

        <div v-if="activeLightbox" class="fixed inset-0 z-50 grid place-items-center bg-black/90 p-5" @click.self="closeLightbox">
            <button type="button" class="absolute right-4 top-4 rounded-md bg-white px-4 py-2 text-sm font-black text-[#17241d]" @click="closeLightbox">Fechar</button>
            <div class="w-full max-w-6xl">
                <img v-if="activeLightbox.tipo === 'foto'" :src="activeLightbox.caminho" :alt="activeLightbox.titulo || evento.titulo" class="mx-auto max-h-[82vh] rounded-lg object-contain">
                <video v-else :src="activeLightbox.caminho" controls autoplay class="mx-auto max-h-[82vh] w-full rounded-lg bg-black object-contain"></video>
                <p class="mt-4 text-center font-black text-white">{{ activeLightbox.titulo || evento.titulo }}</p>
            </div>
        </div>
    </main>
</template>
