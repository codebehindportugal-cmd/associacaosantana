<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    pagina: Object,
});

const c = props.pagina.conteudo || {};
const form = useForm({
    titulo: props.pagina.titulo || '',
    hero_titulo: c.hero_titulo || '',
    hero_subtitulo: c.hero_subtitulo || '',
    introducao: c.introducao || '',
    corpo: c.corpo || '',
    extra: c.extra || '',
});

const guardar = () => {
    form.put(route('paginas.update', props.pagina.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Editar ${pagina.titulo}`" />

    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.14em] text-slate-400">Página {{ pagina.slug }}</p>
                <h1 class="text-2xl font-black">{{ pagina.titulo }}</h1>
            </div>
            <Link :href="route('paginas.index')" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-white">Voltar</Link>
        </div>

        <form class="rounded-lg bg-white p-5 shadow-sm" @submit.prevent="guardar">
            <div class="grid gap-4">
                <label class="grid gap-1 text-sm font-bold text-slate-700">
                    Nome no backoffice
                    <input v-model="form.titulo" required class="rounded-md border-slate-300">
                    <span v-if="form.errors.titulo" class="text-xs text-rose-600">{{ form.errors.titulo }}</span>
                </label>

                <label class="grid gap-1 text-sm font-bold text-slate-700">
                    Título principal
                    <input v-model="form.hero_titulo" class="rounded-md border-slate-300">
                </label>

                <label class="grid gap-1 text-sm font-bold text-slate-700">
                    Subtítulo / data
                    <textarea v-model="form.hero_subtitulo" rows="2" class="rounded-md border-slate-300"></textarea>
                </label>

                <label class="grid gap-1 text-sm font-bold text-slate-700">
                    Introdução
                    <textarea v-model="form.introducao" rows="3" class="rounded-md border-slate-300"></textarea>
                </label>

                <label class="grid gap-1 text-sm font-bold text-slate-700">
                    Corpo do texto
                    <textarea v-model="form.corpo" rows="12" class="rounded-md border-slate-300"></textarea>
                    <span class="text-xs font-medium text-slate-500">Usa uma linha em branco para separar parágrafos.</span>
                </label>

                <label class="grid gap-1 text-sm font-bold text-slate-700">
                    Conteúdo extra
                    <textarea v-model="form.extra" rows="6" class="rounded-md border-slate-300"></textarea>
                    <span class="text-xs font-medium text-slate-500">Para blocos em lista, usa o formato: Título|Descrição, uma linha por item.</span>
                </label>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <button class="rounded-md bg-emerald-700 px-5 py-3 font-black text-white disabled:opacity-60" :disabled="form.processing">Guardar alterações</button>
                <Link :href="route('paginas.index')" class="rounded-md border border-slate-300 px-5 py-3 font-black hover:bg-slate-50">Cancelar</Link>
            </div>
        </form>
    </AppLayout>
</template>
