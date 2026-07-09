<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    paginas: {
        type: Array,
        default: () => [],
    },
});

const urlPublica = (slug) => ({
    'sobre-nos': route('pages.sobre-nos'),
    patrocinios: route('patrocinios.index'),
    privacidade: route('legal.privacidade'),
    termos: route('legal.termos'),
    cookies: route('legal.cookies'),
}[slug] || route('home'));
</script>

<template>
    <Head title="Páginas do site" />

    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black">Páginas do site</h1>
                <p class="mt-1 text-sm text-slate-500">Edita os textos públicos sem mexer no código.</p>
            </div>
            <a :href="route('home')" target="_blank" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-white">Ver site</a>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <article v-for="pagina in paginas" :key="pagina.id" class="rounded-lg bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">{{ pagina.slug }}</p>
                        <h2 class="mt-2 text-xl font-black">{{ pagina.titulo }}</h2>
                        <p v-if="pagina.updated_at" class="mt-1 text-sm text-slate-500">Atualizada em {{ pagina.updated_at }}</p>
                    </div>
                    <span class="rounded bg-slate-100 px-2 py-1 text-xs font-bold text-slate-500">Site</span>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <Link :href="route('paginas.edit', pagina.id)" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white">Editar</Link>
                    <a :href="urlPublica(pagina.slug)" target="_blank" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-slate-50">Ver página</a>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
