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
    ['Contacto', `${route('home')}#contactos`],
];
</script>

<template>
    <div class="min-h-screen bg-amber-50 text-stone-800">
        <header class="sticky top-0 z-40 border-b border-amber-200/80 bg-amber-50/95 backdrop-blur-xl">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3.5 lg:px-8">
                <Link :href="route('home')" class="flex items-center gap-3">
                    <img :src="associationLogo" alt="Logo ARDC Santana" class="h-10 w-10 rounded-full object-contain border border-amber-200 bg-white p-1">
                    <span class="font-display text-base font-semibold tracking-wide text-stone-800">ARDC Santana</span>
                </Link>

                <div class="hidden items-center gap-0.5 md:flex">
                    <Link
                        v-for="item in navItems"
                        :key="item[0]"
                        :href="item[1]"
                        class="rounded-md px-3.5 py-2 text-sm font-medium text-stone-600 transition hover:text-stone-900 hover:bg-amber-100"
                    >
                        {{ item[0] }}
                    </Link>
                </div>

                <a :href="`mailto:${contactEmail}`" class="hidden lg:inline-flex rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700">
                    Contactar
                </a>
            </nav>

            <!-- Mobile nav -->
            <div class="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-5 pb-3 md:hidden">
                <Link
                    v-for="item in navItems"
                    :key="item[0]"
                    :href="item[1]"
                    class="shrink-0 rounded-md bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-900"
                >
                    {{ item[0] }}
                </Link>
            </div>
        </header>

        <slot />

        <footer class="border-t border-amber-200 bg-stone-800 py-12 text-stone-300">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 md:grid-cols-[1fr_1.2fr] lg:px-8">
                <div>
                    <div class="flex items-center gap-3">
                        <img :src="associationLogo" alt="" class="h-10 w-10 rounded-full border border-amber-700/40 bg-stone-700 object-contain p-1.5">
                        <span class="font-semibold text-white">ARDC Santana</span>
                    </div>
                    <div class="mt-4 space-y-1 text-sm text-stone-400">
                        <p>Santana, Carvalhal Benfeito, Caldas da Rainha</p>
                        <p><a :href="`mailto:${contactEmail}`" class="text-amber-400 hover:text-amber-300 transition">{{ contactEmail }}</a></p>
                        <p>Facebook e Instagram: @ardcsantana</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-x-5 gap-y-3 text-sm font-medium md:justify-end">
                    <Link v-for="item in navItems" :key="item[0]" :href="item[1]" class="text-stone-400 hover:text-amber-300 transition">{{ item[0] }}</Link>
                    <Link :href="route('legal.privacidade')" class="text-stone-400 hover:text-amber-300 transition">Política de Privacidade</Link>
                    <Link :href="route('legal.termos')" class="text-stone-400 hover:text-amber-300 transition">Termos e Condições</Link>
                    <Link :href="route('legal.cookies')" class="text-stone-400 hover:text-amber-300 transition">Política de Cookies</Link>
                    <a href="https://www.livroreclamacoes.pt" target="_blank" rel="noopener" class="text-stone-400 hover:text-amber-300 transition">Livro de Reclamações</a>
                </div>
            </div>
            <div class="mx-auto mt-8 flex max-w-7xl flex-col gap-2 border-t border-stone-700 px-5 pt-5 text-xs text-stone-500 md:flex-row md:items-center md:justify-between lg:px-8">
                <p>Copyright © {{ year }} Associação de Santana.</p>
                <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-semibold text-stone-400 hover:text-amber-300 transition">
                    #CreatingDevelopingImproving4you
                </a>
            </div>
        </footer>

        <CookieBanner />
    </div>
</template>
