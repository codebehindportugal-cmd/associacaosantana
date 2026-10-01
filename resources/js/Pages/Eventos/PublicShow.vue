<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicShell from '@/Components/PublicShell.vue';
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
    const eventMedia = media.value.map((item) => ({ ...item, source: item.caminho, thumb: item.miniatura || item.caminho }));
    if (eventMedia.length) return eventMedia;
    return props.evento.cartaz
        ? [{ id: 'cartaz', tipo: 'foto', source: props.evento.cartaz, thumb: props.evento.cartaz, caminho: props.evento.cartaz, titulo: props.evento.titulo }]
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

    <PublicShell>
    <main class="min-h-screen bg-fundo text-tinta">
        <!-- Hero -->
        <section class="border-b border-linha bg-white">
            <div class="mx-auto grid w-full max-w-[1120px] gap-8 px-4 py-10 sm:px-6 lg:grid-cols-[0.9fr_1.1fr] lg:gap-10 lg:py-12">
                <div class="min-w-0">
                    <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">{{ evento.badge || 'Evento' }}</p>
                    <h1 class="m-0 mt-3 text-[clamp(34px,4.4vw,52px)] font-extrabold leading-[1.05] tracking-[-0.02em]">{{ evento.titulo }}</h1>
                    <p class="m-0 mt-3 text-xl font-semibold text-suave">{{ evento.subtitulo || evento.localizacao }}</p>
                    <p v-if="evento.descricao" class="m-0 mt-3 max-w-2xl text-base leading-relaxed text-suave">{{ evento.descricao }}</p>

                    <div v-if="evento.inscricoes_ativas || evento.link_externo_url" class="mt-6 flex flex-wrap gap-3">
                        <a
                            v-if="evento.inscricoes_ativas"
                            :href="route('inscricoes.index')"
                            class="inline-flex h-14 items-center gap-2.5 rounded-[10px] bg-verde px-6 text-[17px] font-extrabold tracking-[0.04em] text-white no-underline transition hover:bg-verde-escuro hover:text-white"
                        >
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></svg>
                            INSCREVER-ME
                        </a>
                        <a
                            v-if="evento.link_externo_url"
                            :href="evento.link_externo_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-14 items-center gap-2 rounded-[10px] px-6 text-[17px] font-extrabold no-underline transition"
                            :class="evento.inscricoes_ativas ? 'border border-linha-forte bg-white text-tinta hover:border-verde hover:text-verde' : 'bg-verde text-white hover:bg-verde-escuro hover:text-white'"
                        >
                            {{ evento.link_externo_texto || 'Inscrições' }}
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" /></svg>
                        </a>
                    </div>

                    <p v-if="evento.fotos_publico_ativo" class="m-0 mt-4">
                        <Link :href="route('eventos.fotos-publico', evento.id)" class="inline-flex h-12 items-center gap-2.5 rounded-[10px] bg-verde-claro px-4 text-[15px] font-bold text-verde-escuro no-underline transition hover:bg-verde-claro2">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z" /><circle cx="12" cy="13" r="3.5" /></svg>
                            Enviar as minhas fotos
                        </Link>
                    </p>

                    <div class="mt-6 grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                        <div class="rounded-[14px] border border-linha bg-fundo p-4">
                            <p class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-suave-2">Data</p>
                            <p class="m-0 mt-1 font-extrabold">{{ dataEvento }}</p>
                        </div>
                        <div class="rounded-[14px] border border-linha bg-fundo p-4">
                            <p class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-suave-2">Local</p>
                            <p class="m-0 mt-1 font-extrabold">{{ evento.localizacao || 'Por definir' }}</p>
                        </div>
                        <div class="col-span-2 rounded-[14px] border border-linha bg-fundo p-4 sm:col-span-1">
                            <p class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-suave-2">Memórias</p>
                            <p class="m-0 mt-1 font-extrabold">{{ facebookEmbedUrl ? 'Facebook' : `${media.length} ficheiros` }}</p>
                        </div>
                    </div>
                </div>

                <div class="min-w-0 self-start overflow-hidden rounded-[14px] bg-escuro">
                    <button v-if="activeSlide" type="button" class="block w-full bg-escuro-2" aria-label="Ampliar" @click="activeLightbox = activeSlide">
                        <img v-if="activeSlide.tipo === 'foto'" :key="activeSlide.source" :src="activeSlide.source" :alt="activeSlide.titulo || evento.titulo" class="slide-in aspect-[16/10] w-full object-contain">
                        <video v-else :src="activeSlide.source" controls class="aspect-[16/10] w-full bg-black object-contain" />
                    </button>
                    <div v-else class="grid aspect-[16/10] place-items-center bg-escuro-2 p-6 text-center font-semibold text-escuro-inativo">
                        Este evento ainda não tem fotografias ou vídeos publicados.
                    </div>

                    <div v-if="slides.length > 1" class="flex items-center justify-between gap-3 p-2.5">
                        <button type="button" class="inline-flex h-11 items-center gap-1.5 rounded-[10px] border border-escuro-2 px-3.5 text-[15px] font-bold text-white transition hover:bg-escuro-2" @click="previousSlide">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                            Anterior
                        </button>
                        <p class="m-0 text-[15px] font-bold tabular-nums text-escuro-inativo">{{ activeIndex + 1 }} / {{ slides.length }}</p>
                        <button type="button" class="inline-flex h-11 items-center gap-1.5 rounded-[10px] border border-escuro-2 px-3.5 text-[15px] font-bold text-white transition hover:bg-escuro-2" @click="nextSlide">
                            Seguinte
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Miniaturas -->
        <section v-if="slides.length > 1" class="border-b border-linha bg-white py-4">
            <div class="mx-auto flex w-full max-w-[1120px] gap-3 overflow-x-auto px-4 sm:px-6">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.id || slide.caminho"
                    type="button"
                    class="h-20 w-28 shrink-0 overflow-hidden rounded-[10px] border-2 bg-linha transition"
                    :class="activeIndex === index ? 'border-verde opacity-100' : 'border-transparent opacity-60 hover:opacity-90'"
                    :aria-current="activeIndex === index ? 'true' : undefined"
                    @click="selectSlide(index)"
                >
                    <img v-if="slide.tipo === 'foto'" :src="slide.thumb || slide.source" :alt="slide.titulo || evento.titulo" loading="lazy" class="h-full w-full object-cover">
                    <video v-else :src="slide.source" class="h-full w-full object-cover" />
                </button>
            </div>
        </section>

        <!-- Facebook embed -->
        <section v-if="facebookEmbedUrl" class="py-14 sm:py-16">
            <div class="mx-auto grid w-full max-w-[1120px] gap-10 px-4 sm:px-6 lg:grid-cols-[0.85fr_1.15fr]">
                <div>
                    <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Facebook</p>
                    <h2 class="m-0 mt-3 text-[clamp(28px,3.2vw,38px)] font-extrabold leading-[1.15]">Fotos e vídeos no post original.</h2>
                    <p class="m-0 mt-4 text-[17px] leading-relaxed text-suave">
                        As memórias deste evento estão alojadas no Facebook, para manter o site mais leve e rápido.
                    </p>
                    <a :href="evento.facebook_post_url" target="_blank" rel="noreferrer" class="mt-6 inline-flex h-[52px] items-center gap-2 rounded-[10px] bg-verde px-[22px] text-base font-bold text-white no-underline transition hover:bg-verde-escuro hover:text-white">
                        Abrir no Facebook
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" /></svg>
                    </a>
                </div>
                <div class="overflow-hidden rounded-[14px] border border-linha bg-white p-4">
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
        <section v-if="!facebookEmbedUrl" class="py-14 sm:py-16">
            <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                <div class="mb-8">
                    <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Fotografias</p>
                    <h2 class="m-0 mt-3 text-[clamp(28px,3.2vw,38px)] font-extrabold leading-[1.15]">Momentos registados durante o evento.</h2>
                </div>

                <div v-if="photos.length" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <button
                        v-for="photo in photos"
                        :key="photo.id"
                        type="button"
                        class="group flex flex-col overflow-hidden rounded-[14px] border border-linha bg-white text-left transition hover:border-verde"
                        @click="activeLightbox = photo"
                    >
                        <span class="block overflow-hidden bg-linha">
                            <img :src="photo.miniatura || photo.caminho" :alt="photo.titulo || evento.titulo" loading="lazy" decoding="async" class="aspect-square w-full object-cover transition duration-500 group-hover:scale-105">
                        </span>
                        <span class="block truncate border-t border-linha px-3 py-2.5 text-sm font-bold">{{ photo.titulo || evento.titulo }}</span>
                    </button>
                </div>
                <div v-else class="rounded-[14px] border border-linha bg-white p-8 text-[17px] text-suave">
                    Ainda não existem fotografias publicadas para este evento.
                </div>
            </div>
        </section>

        <!-- Vídeos -->
        <section v-if="!facebookEmbedUrl" class="border-t border-linha bg-white py-14 sm:py-16">
            <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                <div class="mb-8">
                    <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Vídeos</p>
                    <h2 class="m-0 mt-3 text-[clamp(28px,3.2vw,38px)] font-extrabold leading-[1.15]">Vídeos do evento.</h2>
                </div>

                <div v-if="videos.length" class="grid gap-5 lg:grid-cols-2">
                    <figure v-for="video in videos" :key="video.id" class="m-0 overflow-hidden rounded-[14px] border border-linha bg-fundo">
                        <video :src="video.caminho" controls preload="none" :poster="video.miniatura || undefined" class="aspect-video w-full bg-escuro object-contain" />
                        <figcaption class="p-4 font-bold">{{ video.titulo || evento.titulo }}</figcaption>
                    </figure>
                </div>
                <div v-else class="rounded-[14px] border border-linha bg-fundo p-8 text-[17px] text-suave">
                    Ainda não existem vídeos publicados para este evento.
                </div>
            </div>
        </section>

        <!-- Lightbox -->
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
            <div v-if="activeLightbox" class="fixed inset-0 z-50 grid place-items-center bg-escuro/90 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" @click.self="activeLightbox = null">
                <button type="button" class="absolute right-4 top-4 inline-flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-bold text-white" @click="activeLightbox = null">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    Fechar
                </button>
                <div class="w-full max-w-6xl">
                    <img v-if="activeLightbox.tipo === 'foto'" :src="activeLightbox.caminho" :alt="activeLightbox.titulo || evento.titulo" class="mx-auto max-h-[82vh] rounded-[14px] object-contain shadow-2xl">
                    <video v-else :src="activeLightbox.caminho" controls autoplay class="mx-auto max-h-[82vh] w-full rounded-[14px] bg-black object-contain shadow-2xl" />
                    <p class="m-0 mt-4 text-center font-bold text-white">{{ activeLightbox.titulo || evento.titulo }}</p>
                </div>
            </div>
        </Transition>
    </main>
    </PublicShell>
</template>

<style scoped>
.slide-in { animation: slide-in 260ms ease-out; }
@keyframes slide-in { from { opacity: 0.4; } to { opacity: 1; } }
@media (prefers-reduced-motion: reduce) { .slide-in { animation: none; } }
</style>
