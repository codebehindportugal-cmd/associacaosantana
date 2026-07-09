<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PublicShell from '@/Components/PublicShell.vue';
import SponsorsSlider from '@/Components/SponsorsSlider.vue';

const props = defineProps({
    page: Object,
    patrocinadores: { type: Array, default: () => [] },
});

const inertiaPage = usePage();
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
            <!-- Hero -->
            <section class="relative isolate overflow-hidden bg-stone-800 px-5 py-24 text-white lg:px-8">
                <div class="absolute inset-0 -z-10 bg-gradient-to-r from-stone-900/90 to-stone-800/60" />
                <div class="mx-auto max-w-6xl">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-400">Festa anual</p>
                    <h1 class="mt-4 max-w-3xl text-5xl font-bold leading-tight text-white sm:text-6xl">
                        {{ content.hero_titulo || 'Apoia a Festa de Santa Ana' }}
                    </h1>
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-stone-200">
                        {{ content.hero_subtitulo || 'Ajude-nos a manter viva uma festa feita pela comunidade. Cada contributo conta e a visibilidade é combinada consigo.' }}
                    </p>
                </div>
            </section>

            <div class="h-0.5 bg-gradient-to-r from-transparent via-amber-400 to-transparent" />

            <!-- Conteúdo + Formulário -->
            <section class="py-20 bg-amber-50">
                <div class="mx-auto grid max-w-7xl gap-12 px-5 lg:grid-cols-[0.95fr_1.05fr] lg:items-start lg:px-8">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Como funciona</p>
                        <h2 class="mt-3 text-4xl font-bold text-stone-800 leading-tight">
                            {{ content.introducao || 'Patrocínio simples, direto e adaptado.' }}
                        </h2>
                        <p class="mt-5 text-lg leading-relaxed text-stone-600">
                            {{ content.corpo || 'Cada patrocinador contribui com o que lhe for possível. Em troca, trabalhamos consigo para dar a máxima visibilidade à vossa marca: no recinto com lonas, nas nossas redes sociais e aqui no nosso site.' }}
                        </p>
                        <div class="mt-8 grid gap-4">
                            <article v-for="benefit in benefits" :key="benefit[0]" class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
                                <h3 class="text-lg font-bold text-stone-800">{{ benefit[0] }}</h3>
                                <p class="mt-2 text-stone-600">{{ benefit[1] }}</p>
                            </article>
                        </div>
                    </div>

                    <!-- Form -->
                    <form class="rounded-xl border border-amber-200 bg-white p-8 shadow-md" @submit.prevent="submit">
                        <h2 class="text-2xl font-bold text-stone-800">Proposta de patrocínio</h2>
                        <p class="mt-2 text-sm text-stone-500">Diga-nos como gostaria de apoiar. Entraremos em contacto para combinar os detalhes.</p>

                        <p v-if="inertiaPage.props.flash?.success" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">
                            {{ inertiaPage.props.flash.success }}
                        </p>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <label class="field">
                                Nome
                                <input v-model="form.nome" type="text" class="pub-input">
                                <span v-if="form.errors.nome" class="field-error">{{ form.errors.nome }}</span>
                            </label>
                            <label class="field">
                                Empresa
                                <input v-model="form.empresa" type="text" class="pub-input">
                                <span v-if="form.errors.empresa" class="field-error">{{ form.errors.empresa }}</span>
                            </label>
                            <label class="field">
                                Email
                                <input v-model="form.email" type="email" class="pub-input">
                                <span v-if="form.errors.email" class="field-error">{{ form.errors.email }}</span>
                            </label>
                            <label class="field">
                                Telefone
                                <input v-model="form.telefone" type="tel" class="pub-input">
                                <span v-if="form.errors.telefone" class="field-error">{{ form.errors.telefone }}</span>
                            </label>
                            <label class="field sm:col-span-2">
                                Mensagem / proposta livre
                                <textarea v-model="form.mensagem" rows="5" class="pub-input" />
                                <span v-if="form.errors.mensagem" class="field-error">{{ form.errors.mensagem }}</span>
                            </label>
                        </div>

                        <label class="mt-5 flex gap-3 text-sm text-stone-600">
                            <input v-model="form.aceita_contacto" type="checkbox" class="mt-1 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                            <span>Aceito ser contactado pela ARDC Santana para dar seguimento a esta proposta.</span>
                        </label>
                        <span v-if="form.errors.aceita_contacto" class="mt-1 block text-xs text-red-600">{{ form.errors.aceita_contacto }}</span>

                        <button type="submit" class="mt-6 w-full rounded-md bg-amber-600 px-5 py-3 font-semibold text-white shadow-sm transition hover:bg-amber-700 disabled:opacity-60" :disabled="form.processing">
                            {{ form.processing ? 'A enviar...' : 'Enviar proposta' }}
                        </button>
                    </form>
                </div>
            </section>

            <SponsorsSlider :patrocinadores="patrocinadores" />
        </main>
    </PublicShell>
</template>

<style scoped>
.field { display: grid; gap: 0.35rem; font-size: 0.875rem; font-weight: 600; color: #57534e; }
.pub-input {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 0.5rem;
    color: #1c1917;
    font-size: 0.875rem;
    padding: 0.625rem 0.875rem;
    transition: border-color 200ms;
    width: 100%;
}
.pub-input:focus {
    border-color: #d97706;
    box-shadow: 0 0 0 3px rgb(217 119 6 / 0.15);
    outline: none;
}
.field-error { color: #dc2626; font-size:0.75rem; }
</style>

