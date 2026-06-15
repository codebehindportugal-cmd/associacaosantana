<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicShell from '@/Components/PublicShell.vue';
import SponsorsSlider from '@/Components/SponsorsSlider.vue';

const props = defineProps({
    page: Object,
    patrocinadores: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const content = computed(() => props.page?.conteudo || {});
const form = useForm({
    nome: '',
    empresa: '',
    email: '',
    telefone: '',
    mensagem: '',
    aceita_contacto: false,
});

const benefits = computed(() => (content.value.extra || '')
    .split('\n')
    .map((line) => line.split('|').map((part) => part.trim()))
    .filter((parts) => parts[0] && parts[1]));

const submit = () => {
    form.post(route('patrocinios.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="`${props.page?.titulo || 'Patrocínios'} | ARDC Santana`" />

    <PublicShell>
        <main>
            <section class="bg-slate-900 px-5 py-24 text-white lg:px-8">
                <div class="mx-auto max-w-6xl">
                    <p class="text-sm font-semibold uppercase tracking-wide text-slate-300">Festa anual</p>
                    <h1 class="mt-4 max-w-3xl text-5xl font-bold leading-tight sm:text-6xl">{{ content.hero_titulo || 'Apoia a Festa de Santa Ana' }}</h1>
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-200">
                        {{ content.hero_subtitulo || 'Ajude-nos a manter viva uma festa feita pela comunidade. Cada contributo conta e a visibilidade é combinada consigo.' }}
                    </p>
                </div>
            </section>

            <section class="py-16">
                <div class="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-[0.95fr_1.05fr] lg:px-8">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Como funciona</p>
                        <h2 class="mt-3 text-4xl font-bold text-slate-900">{{ content.introducao || 'Patrocínio simples, direto e adaptado.' }}</h2>
                        <p class="mt-5 text-lg leading-relaxed text-slate-600">
                            {{ content.corpo || 'Cada patrocinador contribui com o que lhe for possível. Em troca, trabalhamos consigo para dar a máxima visibilidade à vossa marca: no recinto com lonas, nas nossas redes sociais e aqui no nosso site.' }}
                        </p>
                        <div class="mt-8 grid gap-4">
                            <article v-for="benefit in benefits" :key="benefit[0]" class="rounded-lg border border-slate-200 bg-slate-50 p-5">
                                <h3 class="text-lg font-bold text-slate-900">{{ benefit[0] }}</h3>
                                <p class="mt-2 text-slate-600">{{ benefit[1] }}</p>
                            </article>
                        </div>
                    </div>

                    <form class="rounded-lg border border-slate-200 bg-white p-6 shadow-md" @submit.prevent="submit">
                        <h2 class="text-2xl font-bold text-slate-900">Proposta de patrocínio</h2>
                        <p class="mt-2 text-sm text-slate-600">Diga-nos como gostaria de apoiar. Entraremos em contacto para combinar os detalhes.</p>

                        <p v-if="page.props.flash?.success" class="mt-4 rounded-md bg-emerald-100 p-3 text-sm font-semibold text-emerald-900">
                            {{ page.props.flash.success }}
                        </p>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <label class="grid gap-1 text-sm font-semibold text-slate-700">
                                Nome
                                <input v-model="form.nome" type="text" class="rounded-md border-slate-300 text-slate-900 focus:border-slate-900 focus:ring-slate-900">
                                <span v-if="form.errors.nome" class="text-xs text-red-600">{{ form.errors.nome }}</span>
                            </label>
                            <label class="grid gap-1 text-sm font-semibold text-slate-700">
                                Empresa
                                <input v-model="form.empresa" type="text" class="rounded-md border-slate-300 text-slate-900 focus:border-slate-900 focus:ring-slate-900">
                                <span v-if="form.errors.empresa" class="text-xs text-red-600">{{ form.errors.empresa }}</span>
                            </label>
                            <label class="grid gap-1 text-sm font-semibold text-slate-700">
                                Email
                                <input v-model="form.email" type="email" class="rounded-md border-slate-300 text-slate-900 focus:border-slate-900 focus:ring-slate-900">
                                <span v-if="form.errors.email" class="text-xs text-red-600">{{ form.errors.email }}</span>
                            </label>
                            <label class="grid gap-1 text-sm font-semibold text-slate-700">
                                Telefone
                                <input v-model="form.telefone" type="tel" class="rounded-md border-slate-300 text-slate-900 focus:border-slate-900 focus:ring-slate-900">
                                <span v-if="form.errors.telefone" class="text-xs text-red-600">{{ form.errors.telefone }}</span>
                            </label>
                            <label class="grid gap-1 text-sm font-semibold text-slate-700 sm:col-span-2">
                                Mensagem / proposta livre
                                <textarea v-model="form.mensagem" rows="5" class="rounded-md border-slate-300 text-slate-900 focus:border-slate-900 focus:ring-slate-900"></textarea>
                                <span v-if="form.errors.mensagem" class="text-xs text-red-600">{{ form.errors.mensagem }}</span>
                            </label>
                        </div>

                        <label class="mt-5 flex gap-3 text-sm text-slate-700">
                            <input v-model="form.aceita_contacto" type="checkbox" class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-900">
                            <span>Aceito ser contactado pela ARDC Santana para dar seguimento a esta proposta.</span>
                        </label>
                        <span v-if="form.errors.aceita_contacto" class="mt-1 block text-xs text-red-600">{{ form.errors.aceita_contacto }}</span>

                        <button type="submit" class="mt-6 w-full rounded-md bg-slate-900 px-5 py-3 font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60" :disabled="form.processing">
                            {{ form.processing ? 'A enviar...' : 'Enviar proposta' }}
                        </button>
                    </form>
                </div>
            </section>

            <SponsorsSlider :patrocinadores="patrocinadores" />
        </main>
    </PublicShell>
</template>
