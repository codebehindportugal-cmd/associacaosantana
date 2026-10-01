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
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-[30px] font-extrabold leading-tight">Páginas do site</h1>
                    <p class="text-[15px] text-suave">Edita os textos públicos sem mexer no código.</p>
                </div>
                <a :href="route('home')" target="_blank" class="btn-sec h-12">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" /><path d="M18 14v6H4V6h6" /></svg>
                    Ver site
                </a>
            </div>

            <ul class="divide-y divide-linha-fraca overflow-hidden rounded-[14px] border border-linha bg-white">
                <li v-if="!paginas.length" class="p-6 text-center font-bold text-suave-2">Ainda não há páginas.</li>
                <li v-for="pagina in paginas" :key="pagina.id" class="flex flex-wrap items-center gap-3 px-4 py-3.5 sm:px-5">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[10px] bg-verde-claro text-verde" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h8l4 4v14H6z" /><path d="M14 3v4h4M9 12h6M9 16h6" /></svg>
                    </span>
                    <div class="min-w-0 flex-1 basis-48">
                        <h2 class="truncate text-lg font-extrabold">{{ pagina.titulo }}</h2>
                        <p class="text-[13px] text-suave">
                            <span class="font-semibold tracking-wide">/{{ pagina.slug }}</span>
                            <span v-if="pagina.updated_at"> · Atualizada em {{ pagina.updated_at }}</span>
                        </p>
                    </div>
                    <div class="flex w-full gap-2 sm:w-auto">
                        <a :href="urlPublica(pagina.slug)" target="_blank" class="btn-sec h-11 flex-1 sm:flex-none">Ver página</a>
                        <Link :href="route('paginas.edit', pagina.id)" class="btn-pri h-11 flex-1 sm:flex-none">Editar</Link>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
</style>
