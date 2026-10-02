<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
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

// Link para a página pública correspondente (mesmo mapa da lista de páginas)
const urlPublica = ({
    'sobre-nos': 'pages.sobre-nos',
    patrocinios: 'patrocinios.index',
    privacidade: 'legal.privacidade',
    termos: 'legal.termos',
    cookies: 'legal.cookies',
}[props.pagina.slug]);
const linkPublico = urlPublica ? route(urlPublica) : route('home');

const guardar = () => {
    form.put(route('paginas.update', props.pagina.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Editar ${pagina.titulo}`" />

    <AppLayout>
        <div class="mx-auto flex max-w-[820px] flex-col gap-5 font-sans text-tinta">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex min-w-0 flex-col gap-1">
                    <Link :href="route('paginas.index')" class="inline-flex w-fit items-center gap-1 text-sm font-bold text-verde hover:text-verde-escuro">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                        Páginas
                    </Link>
                    <p class="text-[13px] font-extrabold uppercase tracking-[0.1em] text-suave-2">Página do site <span class="normal-case tracking-normal">· /{{ pagina.slug }}</span></p>
                    <h1 class="text-[30px] font-extrabold leading-tight">{{ pagina.titulo }}</h1>
                </div>
                <a :href="linkPublico" target="_blank" class="btn-sec h-12">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" /><path d="M18 14v6H4V6h6" /></svg>
                    Ver página
                </a>
            </div>

            <form class="flex flex-col overflow-hidden rounded-[14px] border border-linha bg-white" @submit.prevent="guardar">
                <AvisoErros :errors="form.errors" :excluir="['corpo', 'extra', 'hero_subtitulo', 'hero_titulo', 'introducao', 'titulo']" class="mx-4 mt-4 sm:mx-6" />
                <fieldset class="bloco">
                    <legend class="legenda">Topo da página</legend>
                    <label class="rotulo">
                        Nome no backoffice
                        <input v-model="form.titulo" required class="campo">
                        <span v-if="form.errors.titulo" class="text-xs font-semibold text-perigo">{{ form.errors.titulo }}</span>
                        <span class="ajuda">Só aparece aqui no backoffice.</span>
                    </label>
                    <label class="rotulo">
                        Título principal
                        <input :class="{ '!border-perigo': form.errors.hero_titulo }" v-model="form.hero_titulo" class="campo text-lg font-bold"><span v-if="form.errors.hero_titulo" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.hero_titulo }}</span>
                    </label>
                    <label class="rotulo">
                        Subtítulo / data
                        <textarea :class="{ '!border-perigo': form.errors.hero_subtitulo }" v-model="form.hero_subtitulo" rows="2" class="campo-area"></textarea><span v-if="form.errors.hero_subtitulo" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.hero_subtitulo }}</span>
                    </label>
                </fieldset>

                <fieldset class="bloco">
                    <legend class="legenda">Conteúdo</legend>
                    <label class="rotulo">
                        Introdução
                        <textarea :class="{ '!border-perigo': form.errors.introducao }" v-model="form.introducao" rows="3" class="campo-area"></textarea><span v-if="form.errors.introducao" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.introducao }}</span>
                    </label>
                    <label class="rotulo">
                        Corpo do texto
                        <textarea :class="{ '!border-perigo': form.errors.corpo }" v-model="form.corpo" rows="12" class="campo-area leading-relaxed"></textarea><span v-if="form.errors.corpo" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.corpo }}</span>
                        <span class="ajuda">Usa uma linha em branco para separar parágrafos.</span>
                    </label>
                    <label class="rotulo">
                        Conteúdo extra
                        <textarea :class="{ '!border-perigo': form.errors.extra }" v-model="form.extra" rows="6" class="campo-area"></textarea><span v-if="form.errors.extra" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.extra }}</span>
                        <span class="ajuda">Para blocos em lista, usa o formato: Título|Descrição, uma linha por item.</span>
                    </label>
                </fieldset>

                <div class="flex flex-wrap gap-2.5 px-4 py-4 sm:px-6">
                    <button class="btn-pri h-[52px] px-6 text-base disabled:opacity-60" :disabled="form.processing">Guardar alterações</button>
                    <Link :href="route('paginas.index')" class="btn-sec h-[52px] px-5 text-base">Cancelar</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.bloco { @apply flex flex-col gap-4 border-b border-linha-fraca px-4 py-5 sm:px-6; }
.legenda { @apply float-left mb-1 w-full text-[13px] font-extrabold uppercase tracking-[0.06em] text-suave-2; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-[15px] font-bold text-tinta; }
.ajuda { @apply text-[13px] font-normal text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base font-normal text-tinta focus:border-verde focus:ring-verde; }
.campo-area { @apply w-full rounded-[10px] border border-linha-forte bg-white px-3.5 py-3 text-base font-normal text-tinta focus:border-verde focus:ring-verde; }
</style>
