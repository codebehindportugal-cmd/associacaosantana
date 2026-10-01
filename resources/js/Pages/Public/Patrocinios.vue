<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useRecaptcha } from '@/composables/useRecaptcha';
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
    recaptcha_token: '',
});
const { obterToken } = useRecaptcha();

const benefits = computed(() => (content.value.extra || '')
    .split('\n')
    .map((line) => line.split('|').map((part) => part.trim()))
    .filter((parts) => parts[0] && parts[1]));

const submit = async () => {
    form.recaptcha_token = await obterToken('patrocinio');
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
            <section class="border-b border-linha bg-white py-14 sm:py-20">
                <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                    <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Festa anual</p>
                    <h1 class="m-0 mt-4 max-w-3xl text-[clamp(36px,5vw,60px)] font-extrabold leading-[1.05] tracking-[-0.02em]">
                        {{ content.hero_titulo || 'Apoia a Festa de Santa Ana' }}
                    </h1>
                    <p class="m-0 mt-5 max-w-2xl text-lg text-suave">
                        {{ content.hero_subtitulo || 'Ajude-nos a manter viva uma festa feita pela comunidade. Cada contributo conta e a visibilidade é combinada consigo.' }}
                    </p>
                </div>
            </section>

            <!-- Conteúdo + Formulário -->
            <section class="border-b border-linha bg-fundo py-14 sm:py-20">
                <div class="mx-auto grid w-full max-w-[1120px] gap-10 px-4 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:items-start lg:gap-12">
                    <div>
                        <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Como funciona</p>
                        <h2 class="m-0 mt-3 text-[clamp(28px,3.2vw,40px)] font-extrabold leading-[1.15]">
                            {{ content.introducao || 'Patrocínio simples, direto e adaptado.' }}
                        </h2>
                        <p class="m-0 mt-5 text-lg leading-relaxed text-suave">
                            {{ content.corpo || 'Cada patrocinador contribui com o que lhe for possível. Em troca, trabalhamos consigo para dar a máxima visibilidade à vossa marca: no recinto com lonas, nas nossas redes sociais e aqui no nosso site.' }}
                        </p>
                        <div v-if="benefits.length" class="mt-8 grid gap-3">
                            <article v-for="(benefit, i) in benefits" :key="benefit[0]" class="flex items-start gap-4 rounded-[14px] border border-linha bg-white p-5">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] bg-verde-claro text-verde" aria-hidden="true">
                                    <svg v-if="i % 3 === 0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="1" /><path d="M8 20l4-4 4 4" /></svg>
                                    <svg v-else-if="i % 3 === 1" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4" /></svg>
                                    <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" /></svg>
                                </span>
                                <div class="min-w-0">
                                    <h3 class="m-0 text-lg font-extrabold">{{ benefit[0] }}</h3>
                                    <p class="m-0 mt-1 text-base text-suave">{{ benefit[1] }}</p>
                                </div>
                            </article>
                        </div>
                    </div>

                    <!-- Formulário -->
                    <form id="proposta" class="rounded-[14px] border border-linha bg-white p-5 shadow-[0_8px_24px_rgba(22,32,28,.06)] sm:p-8" @submit.prevent="submit">
                        <h2 class="m-0 text-2xl font-extrabold">Proposta de patrocínio</h2>
                        <p class="m-0 mt-2 text-[15px] text-suave">Diga-nos como gostaria de apoiar. Entraremos em contacto para combinar os detalhes.</p>

                        <p v-if="inertiaPage.props.flash?.success" class="m-0 mt-4 flex items-start gap-2.5 rounded-[10px] border border-verde-claro2 bg-verde-claro p-3.5 text-[15px] font-semibold text-verde-escuro" role="status">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mt-px shrink-0"><path d="M5 12l5 5L19 7" /></svg>
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
                                <textarea v-model="form.mensagem" rows="5" class="pub-input pub-textarea" />
                                <span v-if="form.errors.mensagem" class="field-error">{{ form.errors.mensagem }}</span>
                            </label>
                        </div>

                        <label class="mt-5 flex cursor-pointer gap-3 rounded-[10px] bg-fundo p-3.5 text-[15px] text-suave">
                            <input v-model="form.aceita_contacto" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-linha-forte text-verde focus:ring-verde">
                            <span>Aceito ser contactado pela ARDC Santana para dar seguimento a esta proposta.</span>
                        </label>
                        <span v-if="form.errors.aceita_contacto" class="field-error mt-1 block">{{ form.errors.aceita_contacto }}</span>

                        <button type="submit" class="mt-6 h-[54px] w-full rounded-[10px] bg-verde px-5 text-[17px] font-bold text-white transition hover:bg-verde-escuro disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing">
                            {{ form.processing ? 'A enviar...' : 'Enviar proposta' }}
                        </button>
                    </form>
                </div>
            </section>

            <div class="bg-white">
                <SponsorsSlider :patrocinadores="patrocinadores" />
            </div>
        </main>
    </PublicShell>
</template>

<style scoped>
.field { display: flex; flex-direction: column; gap: 6px; font-size: 15px; font-weight: 700; color: #16201C; }
.pub-input {
    width: 100%;
    height: 50px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid #D5D9D3;
    background: #FFFFFF;
    color: #16201C;
    font-size: 16px;
    font-weight: 400;
    transition: border-color 150ms, box-shadow 150ms;
}
.pub-textarea { height: auto; padding: 12px 14px; resize: vertical; }
.pub-input:focus {
    border-color: #0F6B4F;
    box-shadow: 0 0 0 3px rgb(15 107 79 / 0.15);
    outline: none;
}
.field-error { color: #A3241A; font-size: 14px; font-weight: 600; }
</style>
