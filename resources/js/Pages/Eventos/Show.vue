<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    evento: Object,
});

const activeMedia = ref(null);
const mediaList = computed(() => props.evento.media ?? []);
const activeMediaIndex = computed(() => mediaList.value.findIndex((media) => media.id === activeMedia.value?.id));

const dataEvento = (evento) => {
    if (!evento.data_inicio) return evento.periodo || 'Sem data definida';
    if (evento.data_fim && evento.data_fim !== evento.data_inicio) return `${evento.data_inicio} a ${evento.data_fim}`;
    return evento.data_inicio;
};

const estadoLabel = (estado) => ({ publicado: 'Publicado', rascunho: 'Rascunho' }[estado] ?? estado);

const uploadMedia = (event) => {
    const ficheiros = Array.from(event.target.files ?? []);
    if (!ficheiros.length) return;

    const data = new FormData();
    ficheiros.forEach((ficheiro) => data.append('ficheiros[]', ficheiro));

    router.post(route('eventos.media.store', props.evento.id), data, {
        preserveScroll: true,
        onFinish: () => {
            event.target.value = '';
        },
    });
};

const apagarMedia = (media) => {
    router.delete(route('eventos.media.destroy', media.id), { preserveScroll: true });
};

const abrirMedia = (media) => {
    activeMedia.value = media;
};

const fecharMedia = () => {
    activeMedia.value = null;
};

const mediaAnterior = () => {
    if (!mediaList.value.length) return;
    const index = activeMediaIndex.value <= 0 ? mediaList.value.length - 1 : activeMediaIndex.value - 1;
    activeMedia.value = mediaList.value[index];
};

const mediaSeguinte = () => {
    if (!mediaList.value.length) return;
    const index = activeMediaIndex.value >= mediaList.value.length - 1 ? 0 : activeMediaIndex.value + 1;
    activeMedia.value = mediaList.value[index];
};
</script>

<template>
    <Head :title="evento.titulo" />

    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-6 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex min-w-0 flex-col gap-1">
                    <Link :href="route('eventos.index')" class="inline-flex items-center gap-1 text-sm font-bold text-verde hover:text-verde-escuro">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                        Eventos
                    </Link>
                    <p class="text-[13px] font-extrabold uppercase tracking-[0.1em] text-suave-2">Ficha do evento</p>
                    <h1 class="text-[30px] font-extrabold leading-tight">{{ evento.titulo }}</h1>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <Link :href="route('eventos.inscricoes', evento.id)" class="btn-sec h-12">Inscrições</Link>
                    <Link :href="route('eventos.edit', evento.id)" class="btn-pri h-12">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16z" /></svg>
                        Editar
                    </Link>
                </div>
            </div>

            <section class="grid overflow-hidden rounded-[14px] border border-linha bg-white lg:grid-cols-[300px_minmax(0,1fr)]">
                <img v-if="evento.cartaz" :src="evento.cartaz" :alt="evento.titulo" class="h-full max-h-[620px] w-full bg-[#E3E6E1] object-cover">
                <div v-else class="grid min-h-48 place-items-center bg-[#E3E6E1] text-sm font-bold text-suave-2 lg:min-h-96">Sem cartaz</div>

                <div class="flex min-w-0 flex-col gap-4 p-5 lg:p-7">
                    <div class="flex flex-wrap gap-1.5">
                        <span class="pill" :class="evento.estado === 'publicado' ? 'bg-verde-claro text-verde-escuro' : 'bg-fundo text-suave'">{{ estadoLabel(evento.estado) }}</span>
                        <span v-if="evento.destaque" class="pill bg-laranja-claro text-laranja-texto">Destaque</span>
                        <span class="pill bg-fundo text-suave">{{ evento.badge || 'Evento' }}</span>
                    </div>

                    <h2 v-if="evento.subtitulo || evento.localizacao" class="text-[22px] font-extrabold leading-snug">{{ evento.subtitulo || evento.localizacao }}</h2>
                    <p class="max-w-3xl text-[15px] leading-relaxed text-suave">{{ evento.descricao || 'Sem descrição registada.' }}</p>

                    <a
                        v-if="evento.facebook_post_url"
                        :href="evento.facebook_post_url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex h-11 w-fit items-center gap-2 rounded-[10px] bg-azul px-4 text-[15px] font-bold text-white hover:opacity-90"
                    >
                        Ver post do Facebook
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" /><path d="M18 14v6H4V6h6" /></svg>
                    </a>

                    <div class="grid gap-2.5 sm:grid-cols-3">
                        <div class="rounded-[10px] bg-fundo px-4 py-3">
                            <p class="text-xs font-extrabold uppercase tracking-[0.08em] text-suave-2">Data</p>
                            <p class="mt-0.5 font-extrabold">{{ dataEvento(evento) }}</p>
                        </div>
                        <div class="rounded-[10px] bg-fundo px-4 py-3">
                            <p class="text-xs font-extrabold uppercase tracking-[0.08em] text-suave-2">Local</p>
                            <p class="mt-0.5 font-extrabold">{{ evento.localizacao || 'Por definir' }}</p>
                        </div>
                        <div class="rounded-[10px] bg-fundo px-4 py-3">
                            <p class="text-xs font-extrabold uppercase tracking-[0.08em] text-suave-2">Galeria</p>
                            <p class="mt-0.5 font-extrabold">{{ evento.media?.length || 0 }} ficheiros</p>
                        </div>
                    </div>

                    <div v-if="evento.programa?.length" class="flex flex-col gap-2.5">
                        <h3 class="text-[17px] font-extrabold">Programa</h3>
                        <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-3">
                            <div v-for="grupo in evento.programa" :key="grupo.label || grupo.day" class="rounded-[10px] border border-linha p-4">
                                <p class="text-xs font-extrabold uppercase tracking-[0.08em] text-laranja">{{ grupo.label || grupo.day }}</p>
                                <ul class="mt-2 list-disc space-y-1 pl-4 text-sm text-suave">
                                    <li v-for="item in grupo.items" :key="item">{{ item }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-4 rounded-[14px] border border-linha bg-white p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-extrabold">Como correu o evento</h2>
                        <p class="text-sm text-suave-2">Guarda aqui as fotos e vídeos recolhidos durante o evento.</p>
                    </div>
                    <label class="btn-pri h-12 cursor-pointer">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 20h16" /></svg>
                        Adicionar fotos/vídeos
                        <input type="file" multiple accept="image/*,video/mp4,video/webm,video/quicktime" class="hidden" @change="uploadMedia">
                    </label>
                </div>

                <template v-if="evento.media?.length">
                    <div class="grid grid-cols-1 gap-4 min-[480px]:grid-cols-2 lg:grid-cols-4">
                        <figure v-for="media in evento.media" :key="media.id" class="relative overflow-hidden rounded-[14px] border border-linha bg-white">
                            <button type="button" class="block w-full bg-[#E3E6E1] text-left" :aria-label="`Ver em grande: ${media.titulo || 'ficheiro do evento'}`" @click="abrirMedia(media)">
                                <img v-if="media.tipo === 'foto'" :src="media.miniatura || media.caminho" :alt="media.titulo" loading="lazy" class="aspect-[16/10] w-full object-cover">
                                <video v-else :src="media.caminho" preload="none" class="aspect-[16/10] w-full bg-black object-cover"></video>
                            </button>
                            <span class="pointer-events-none absolute left-2 top-2 rounded-full bg-tinta px-2 py-0.5 text-[11px] font-extrabold text-white">{{ media.tipo === 'foto' ? 'Foto' : 'Vídeo' }}</span>
                            <button type="button" class="absolute right-2 top-2 grid h-11 w-11 place-items-center rounded-[10px] border border-linha-forte bg-white text-perigo hover:bg-perigo-claro" aria-label="Remover ficheiro" title="Remover" @click="apagarMedia(media)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                            </button>
                            <figcaption class="flex items-center justify-between gap-2 px-3 py-2.5 text-sm">
                                <span class="truncate font-bold">{{ media.titulo || 'Ficheiro do evento' }}</span>
                                <button type="button" class="shrink-0 text-xs font-bold text-verde underline" @click="abrirMedia(media)">Ver grande</button>
                            </figcaption>
                        </figure>
                    </div>
                    <p class="text-[13px] text-suave-2">Toca numa foto para a ver em grande e passar para a seguinte.</p>
                </template>
                <div v-else class="rounded-[10px] bg-fundo p-8 text-center">
                    <p class="font-extrabold text-suave">Ainda não há registos deste evento.</p>
                    <p class="mt-1 text-sm text-suave-2">Quando tiveres fotos ou vídeos, carrega-os aqui para criar a memória do evento.</p>
                </div>
            </section>
        </div>

        <div v-if="activeMedia" class="fixed inset-0 z-50 grid place-items-center bg-tinta/95 p-4 font-sans" @click.self="fecharMedia">
            <button type="button" class="absolute right-4 top-4 h-11 rounded-[10px] bg-white px-4 text-sm font-extrabold text-tinta" @click="fecharMedia">Fechar</button>
            <button v-if="mediaList.length > 1" type="button" class="absolute left-4 top-1/2 grid h-14 w-14 -translate-y-1/2 place-items-center rounded-full border border-white/20 bg-white/10 text-white hover:bg-white hover:text-tinta" aria-label="Anterior" @click="mediaAnterior">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
            </button>
            <button v-if="mediaList.length > 1" type="button" class="absolute right-4 top-1/2 grid h-14 w-14 -translate-y-1/2 place-items-center rounded-full border border-white/20 bg-white/10 text-white hover:bg-white hover:text-tinta" aria-label="Seguinte" @click="mediaSeguinte">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
            </button>

            <div class="w-full max-w-6xl">
                <img v-if="activeMedia.tipo === 'foto'" :src="activeMedia.caminho" :alt="activeMedia.titulo" class="mx-auto max-h-[82vh] w-auto rounded-[14px] object-contain shadow-2xl">
                <video v-else :src="activeMedia.caminho" controls autoplay class="mx-auto max-h-[82vh] w-full rounded-[14px] bg-black shadow-2xl"></video>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-white">
                    <div>
                        <p class="font-extrabold">{{ activeMedia.titulo || 'Ficheiro do evento' }}</p>
                        <p class="text-sm font-semibold text-white/60 tabular-nums">{{ activeMediaIndex + 1 }} / {{ mediaList.length }}</p>
                    </div>
                    <a :href="activeMedia.caminho" target="_blank" rel="noreferrer" class="inline-flex h-11 items-center rounded-[10px] border border-white/25 px-4 text-sm font-extrabold hover:bg-white hover:text-tinta">Abrir ficheiro</a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.pill { @apply rounded-full px-3 py-1 text-[13px] font-extrabold; }
</style>
