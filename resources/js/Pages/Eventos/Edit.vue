<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    evento: Object,
});

const linhasPrograma = (evento) => (evento.programa ?? [])
    .flatMap((grupo) => grupo.items ?? [])
    .join('\n');

const form = useForm({
    titulo: props.evento.titulo ?? '',
    subtitulo: props.evento.subtitulo ?? '',
    data_inicio: props.evento.data_inicio ?? '',
    data_fim: props.evento.data_fim ?? '',
    periodo: props.evento.periodo ?? '',
    localizacao: props.evento.localizacao ?? '',
    badge: props.evento.badge ?? '',
    descricao: props.evento.descricao ?? '',
    facebook_post_url: props.evento.facebook_post_url ?? '',
    estado: props.evento.estado ?? 'publicado',
    destaque: Boolean(props.evento.destaque),
    ordem: props.evento.ordem ?? 0,
    programa_texto: linhasPrograma(props.evento),
    cartaz: null,
});

const guardar = () => {
    form
        .transform((data) => ({
            ...data,
            destaque: data.destaque ? 1 : 0,
            _method: 'put',
        }))
        .post(route('eventos.update', props.evento.id), {
            forceFormData: true,
            preserveScroll: true,
        });
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
</script>

<template>
    <Head :title="`Editar ${evento.titulo}`" />

    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.14em] text-slate-400">Editar evento</p>
                <h1 class="text-2xl font-black">{{ evento.titulo }}</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('eventos.show', evento.id)" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-white">Abrir evento</Link>
                <Link :href="route('eventos.index')" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-white">Voltar</Link>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[320px_1fr]">
            <aside class="rounded-lg bg-white p-5 shadow-sm">
                <img v-if="evento.cartaz" :src="evento.cartaz" :alt="evento.titulo" class="aspect-[4/5] w-full rounded-md bg-slate-100 object-cover">
                <div v-else class="grid aspect-[4/5] place-items-center rounded-md bg-slate-100 text-sm font-bold text-slate-400">Sem cartaz</div>

                <label class="mt-4 block cursor-pointer rounded-md border border-slate-300 px-4 py-3 text-center text-sm font-black hover:bg-slate-50">
                    Trocar cartaz
                    <input type="file" accept="image/*" class="hidden" @change="form.cartaz = $event.target.files[0]">
                </label>
            </aside>

            <form class="rounded-lg bg-white p-5 shadow-sm" @submit.prevent="guardar">
                <div class="grid gap-3 md:grid-cols-4">
                    <input v-model="form.titulo" required placeholder="Titulo" class="rounded-md border-slate-300 md:col-span-2">
                    <input v-model="form.subtitulo" placeholder="Subtitulo" class="rounded-md border-slate-300">
                    <input v-model="form.localizacao" placeholder="Local" class="rounded-md border-slate-300">
                    <input v-model="form.data_inicio" type="date" class="rounded-md border-slate-300">
                    <input v-model="form.data_fim" type="date" class="rounded-md border-slate-300">
                    <input v-model="form.periodo" placeholder="Periodo" class="rounded-md border-slate-300">
                    <input v-model="form.badge" placeholder="Etiqueta" class="rounded-md border-slate-300">
                    <input v-model="form.facebook_post_url" type="url" placeholder="URL do post do Facebook" class="rounded-md border-slate-300 md:col-span-2">
                    <select v-model="form.estado" class="rounded-md border-slate-300">
                        <option value="publicado">Publicado</option>
                        <option value="rascunho">Rascunho</option>
                    </select>
                    <label class="flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-bold">
                        <input v-model="form.destaque" type="checkbox" class="rounded border-slate-300">
                        Destaque
                    </label>
                    <input v-model.number="form.ordem" type="number" min="0" class="rounded-md border-slate-300">
                    <textarea v-model="form.descricao" placeholder="Descricao" rows="5" class="rounded-md border-slate-300 md:col-span-4"></textarea>
                    <textarea v-model="form.programa_texto" placeholder="Programa: uma linha por item" rows="5" class="rounded-md border-slate-300 md:col-span-4"></textarea>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button class="rounded-md bg-emerald-700 px-5 py-3 font-black text-white disabled:opacity-60" :disabled="form.processing">Guardar alteracoes</button>
                    <Link :href="route('eventos.index')" class="rounded-md border border-slate-300 px-5 py-3 font-black hover:bg-slate-50">Cancelar</Link>
                </div>
            </form>
        </div>

        <section class="mt-6 rounded-lg bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black">Fotos e videos do evento</h2>
                    <p class="text-sm text-slate-500">Carrega aqui os ficheiros para aparecerem na ficha do evento e na home.</p>
                </div>
                <label class="cursor-pointer rounded-md bg-slate-900 px-4 py-2 text-sm font-black text-white">
                    Adicionar fotos/videos
                    <input type="file" multiple accept="image/*,video/mp4,video/webm,video/quicktime" class="hidden" @change="uploadMedia">
                </label>
            </div>

            <div v-if="evento.media?.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="media in evento.media" :key="media.id" class="rounded-md border border-slate-200 p-2">
                    <img v-if="media.tipo === 'foto'" :src="media.caminho" :alt="media.titulo" class="aspect-video w-full rounded object-cover">
                    <video v-else :src="media.caminho" controls class="aspect-video w-full rounded bg-black object-cover"></video>
                    <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                        <span class="truncate font-bold">{{ media.titulo }}</span>
                        <button type="button" class="text-rose-700 underline" @click="apagarMedia(media)">Remover</button>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-md bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">
                Ainda nao ha fotos ou videos neste evento.
            </div>
        </section>
    </AppLayout>
</template>
