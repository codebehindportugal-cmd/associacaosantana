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
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.14em] text-slate-400">Ficha do evento</p>
                <h1 class="text-2xl font-black">{{ evento.titulo }}</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('eventos.edit', evento.id)" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white">Editar</Link>
                <Link :href="route('eventos.index')" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-white">Voltar</Link>
            </div>
        </div>

        <section class="overflow-hidden rounded-lg bg-slate-950 text-white shadow-sm">
            <div class="grid lg:grid-cols-[360px_1fr]">
                <img v-if="evento.cartaz" :src="evento.cartaz" :alt="evento.titulo" class="h-full max-h-[620px] w-full object-cover">
                <div v-else class="grid min-h-96 place-items-center bg-slate-900 text-sm font-bold text-white/50">Sem cartaz</div>

                <div class="p-6 lg:p-8">
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded bg-white px-3 py-1 text-xs font-black uppercase text-slate-950">{{ evento.estado }}</span>
                        <span v-if="evento.destaque" class="rounded bg-amber-300 px-3 py-1 text-xs font-black uppercase text-slate-950">Destaque</span>
                        <span class="rounded bg-white/10 px-3 py-1 text-xs font-black uppercase text-white">{{ evento.badge || 'Evento' }}</span>
                    </div>

                    <h2 class="mt-5 text-4xl font-black">{{ evento.titulo }}</h2>
                    <p class="mt-2 text-lg font-bold text-amber-200">{{ evento.subtitulo || evento.localizacao }}</p>
                    <p class="mt-5 max-w-3xl leading-relaxed text-slate-200">{{ evento.descricao || 'Sem descricao registada.' }}</p>

                    <a
                        v-if="evento.facebook_post_url"
                        :href="evento.facebook_post_url"
                        target="_blank"
                        rel="noreferrer"
                        class="mt-5 inline-flex rounded-md bg-blue-500 px-4 py-2 text-sm font-black text-white hover:bg-blue-400"
                    >
                        Ver post do Facebook
                    </a>

                    <div class="mt-8 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-md bg-white/10 p-4">
                            <p class="text-xs font-black uppercase text-slate-400">Data</p>
                            <p class="mt-1 font-black">{{ dataEvento(evento) }}</p>
                        </div>
                        <div class="rounded-md bg-white/10 p-4">
                            <p class="text-xs font-black uppercase text-slate-400">Local</p>
                            <p class="mt-1 font-black">{{ evento.localizacao || 'Por definir' }}</p>
                        </div>
                        <div class="rounded-md bg-white/10 p-4">
                            <p class="text-xs font-black uppercase text-slate-400">Galeria</p>
                            <p class="mt-1 font-black">{{ evento.media?.length || 0 }} ficheiros</p>
                        </div>
                    </div>

                    <div v-if="evento.programa?.length" class="mt-8">
                        <h3 class="font-black">Programa</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div v-for="grupo in evento.programa" :key="grupo.label || grupo.day" class="rounded-md bg-white/10 p-4">
                                <p class="text-xs font-black uppercase text-amber-200">{{ grupo.label || grupo.day }}</p>
                                <ul class="mt-2 space-y-1 text-sm text-slate-100">
                                    <li v-for="item in grupo.items" :key="item">{{ item }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-6 rounded-lg bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-black">Como correu o evento</h2>
                    <p class="text-sm text-slate-500">Guarda aqui as fotos e videos recolhidos durante o evento.</p>
                </div>
                <label class="cursor-pointer rounded-md bg-slate-900 px-4 py-2 text-sm font-black text-white">
                    Adicionar fotos/videos
                    <input type="file" multiple accept="image/*,video/mp4,video/webm,video/quicktime" class="hidden" @change="uploadMedia">
                </label>
            </div>

            <div v-if="evento.media?.length" class="grid gap-5 lg:grid-cols-2">
                <figure v-for="media in evento.media" :key="media.id" class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-sm">
                    <button type="button" class="block w-full bg-slate-950 text-left" @click="abrirMedia(media)">
                        <img v-if="media.tipo === 'foto'" :src="media.caminho" :alt="media.titulo" class="aspect-[16/10] w-full object-cover transition duration-500 hover:scale-[1.02]">
                        <video v-else :src="media.caminho" class="aspect-[16/10] w-full bg-black object-cover"></video>
                    </button>
                    <figcaption class="flex items-center justify-between gap-2 p-3 text-sm">
                        <span class="truncate font-bold">{{ media.titulo || 'Ficheiro do evento' }}</span>
                        <div class="flex items-center gap-3">
                            <button type="button" class="text-xs font-bold text-slate-700 underline" @click="abrirMedia(media)">Ver grande</button>
                            <button type="button" class="text-xs font-bold text-rose-700 underline" @click="apagarMedia(media)">Remover</button>
                        </div>
                    </figcaption>
                </figure>
            </div>
            <div v-else class="rounded-md bg-slate-50 p-8 text-center">
                <p class="font-black text-slate-700">Ainda nao ha registos deste evento.</p>
                <p class="mt-1 text-sm text-slate-500">Quando tiveres fotos ou videos, carrega-os aqui para criar a memoria do evento.</p>
            </div>
        </section>

        <div v-if="activeMedia" class="fixed inset-0 z-50 grid place-items-center bg-slate-950/95 p-4" @click.self="fecharMedia">
            <button type="button" class="absolute right-4 top-4 rounded-md bg-white px-4 py-2 text-sm font-black text-slate-950" @click="fecharMedia">Fechar</button>
            <button v-if="mediaList.length > 1" type="button" class="absolute left-4 top-1/2 rounded-full border border-white/20 bg-white/10 px-4 py-3 text-3xl font-black text-white hover:bg-white hover:text-slate-950" @click="mediaAnterior">&lsaquo;</button>
            <button v-if="mediaList.length > 1" type="button" class="absolute right-4 top-1/2 rounded-full border border-white/20 bg-white/10 px-4 py-3 text-3xl font-black text-white hover:bg-white hover:text-slate-950" @click="mediaSeguinte">&rsaquo;</button>

            <div class="w-full max-w-6xl">
                <img v-if="activeMedia.tipo === 'foto'" :src="activeMedia.caminho" :alt="activeMedia.titulo" class="mx-auto max-h-[82vh] w-auto rounded-lg object-contain shadow-2xl">
                <video v-else :src="activeMedia.caminho" controls autoplay class="mx-auto max-h-[82vh] w-full rounded-lg bg-black shadow-2xl"></video>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-white">
                    <div>
                        <p class="font-black">{{ activeMedia.titulo || 'Ficheiro do evento' }}</p>
                        <p class="text-sm font-semibold text-white/60">{{ activeMediaIndex + 1 }} / {{ mediaList.length }}</p>
                    </div>
                    <a :href="activeMedia.caminho" target="_blank" rel="noreferrer" class="rounded-md border border-white/25 px-4 py-2 text-sm font-black hover:bg-white hover:text-slate-950">Abrir ficheiro</a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
