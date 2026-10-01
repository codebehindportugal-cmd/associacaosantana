<script setup>
import { Link } from '@inertiajs/vue3';
import CookieBanner from './CookieBanner.vue';

const associationLogo = '/images/santana-logo.png';
const contactEmail = 'ardcsantana@outlook.com';
const year = new Date().getFullYear();

const navItems = [
    ['Início', route('home')],
    ['Sobre Nós', route('pages.sobre-nos')],
    ['Eventos', `${route('home')}#eventos`],
    ['Patrocínios', route('patrocinios.index')],
    ['Reservar Salão', route('salao.pre-reserva')],
    ['Contacto', `${route('home')}#contacto`],
];

// Só para marcar o item ativo (os links com âncora nunca ficam ativos)
const rotaDoItem = {
    'Início': 'home',
    'Sobre Nós': 'pages.sobre-nos',
    'Patrocínios': 'patrocinios.index',
    'Reservar Salão': 'salao.pre-reserva',
};

const ativo = (item) => {
    const nome = rotaDoItem[item[0]];
    if (!nome) return false;
    try {
        return route().current(nome);
    } catch (e) {
        return false;
    }
};

const legalLinks = [
    ['Política de Privacidade', route('legal.privacidade')],
    ['Termos e Condições', route('legal.termos')],
    ['Política de Cookies', route('legal.cookies')],
];
</script>

<template>
    <div class="min-h-screen bg-fundo font-sans leading-normal text-tinta tabular-nums">
        <header class="sticky top-0 z-40 border-b border-linha bg-white">
            <nav aria-label="Principal" class="mx-auto flex h-[72px] w-full max-w-[1120px] items-center justify-between gap-4 px-4 sm:px-6">
                <Link :href="route('home')" class="flex items-center gap-3 text-tinta no-underline">
                    <img :src="associationLogo" alt="Logo ARDC Santana" class="h-11 w-11 rounded-full border border-linha bg-white object-contain p-1">
                    <span class="text-lg font-extrabold">ARDC Santana</span>
                </Link>

                <div class="hidden items-center gap-0.5 min-[901px]:flex">
                    <Link
                        v-for="item in navItems"
                        :key="item[0]"
                        :href="item[1]"
                        :aria-current="ativo(item) ? 'page' : undefined"
                        class="flex h-11 items-center rounded-[10px] px-3.5 text-[15px] no-underline transition"
                        :class="ativo(item)
                            ? 'bg-verde-claro font-bold text-verde-escuro'
                            : 'font-semibold text-suave hover:bg-fundo hover:text-tinta'"
                    >
                        {{ item[0] }}
                    </Link>
                </div>

                <a :href="`mailto:${contactEmail}`" class="hidden h-11 items-center gap-2 rounded-[10px] bg-verde px-[18px] text-[15px] font-bold text-white no-underline transition hover:bg-verde-escuro hover:text-white min-[901px]:inline-flex">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></svg>
                    Contactar
                </a>
            </nav>

            <!-- Navegação telemóvel -->
            <nav aria-label="Principal (telemóvel)" class="mx-auto flex w-full max-w-[1120px] gap-2 overflow-x-auto px-4 pb-3 sm:px-6 min-[901px]:hidden">
                <Link
                    v-for="item in navItems"
                    :key="item[0]"
                    :href="item[1]"
                    :aria-current="ativo(item) ? 'page' : undefined"
                    class="flex h-11 shrink-0 items-center rounded-full border px-4 text-[15px] no-underline"
                    :class="ativo(item)
                        ? 'border-verde bg-verde font-bold text-white'
                        : 'border-linha bg-fundo font-semibold text-tinta'"
                >
                    {{ item[0] }}
                </Link>
            </nav>
        </header>

        <slot />

        <footer class="bg-escuro pb-6 pt-12 text-escuro-inativo">
            <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                <div class="grid gap-8 min-[901px]:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
                    <div>
                        <div class="flex items-center gap-3">
                            <img :src="associationLogo" alt="" class="h-11 w-11 rounded-full bg-escuro-2 object-contain p-1.5">
                            <span class="text-lg font-extrabold text-white">ARDC Santana</span>
                        </div>
                        <div class="mt-[18px] flex flex-col gap-2.5 text-[15px] text-[#B9C4BE]">
                            <p class="m-0 flex items-center gap-2.5">
                                <svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z" /><circle cx="12" cy="9" r="2.5" /></svg>
                                Santana, Carvalhal Benfeito, Caldas da Rainha
                            </p>
                            <p class="m-0 flex items-center gap-2.5">
                                <svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></svg>
                                <a :href="`mailto:${contactEmail}`" class="break-all font-semibold text-white hover:text-verde-claro">{{ contactEmail }}</a>
                            </p>
                            <p class="m-0 flex items-center gap-2.5">
                                <svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4" /><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8" /></svg>
                                Facebook e Instagram: @ardcsantana
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                        <nav aria-label="Rodapé" class="flex flex-col">
                            <Link v-for="item in navItems" :key="item[0]" :href="item[1]" class="py-2 text-[15px] font-semibold text-escuro-inativo no-underline transition hover:text-white">{{ item[0] }}</Link>
                        </nav>
                        <nav aria-label="Legal" class="flex flex-col">
                            <Link v-for="item in legalLinks" :key="item[0]" :href="item[1]" class="py-2 text-[15px] font-semibold text-escuro-inativo no-underline transition hover:text-white">{{ item[0] }}</Link>
                            <a href="https://www.livroreclamacoes.pt" target="_blank" rel="noopener" class="py-2 text-[15px] font-semibold text-escuro-inativo no-underline transition hover:text-white">Livro de Reclamações</a>
                        </nav>
                    </div>
                </div>

                <div class="mt-8 flex flex-wrap justify-between gap-x-6 gap-y-2 border-t border-escuro-2 pt-5 text-[13px] text-[#8FA39A]">
                    <p class="m-0">Copyright © {{ year }} Associação Recreativa, Desportiva e Cultural de Santana.</p>
                    <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-bold text-[#B9C4BE] no-underline transition hover:text-white">
                        #CreatingDevelopingImproving4you
                    </a>
                </div>
            </div>
        </footer>

        <CookieBanner />
    </div>
</template>
