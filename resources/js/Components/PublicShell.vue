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
    <div class="public-theme min-h-screen bg-white text-slate-900">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/90 backdrop-blur-xl">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3.5 lg:px-8">
                <Link :href="route('home')" class="flex items-center gap-3">
                    <img :src="associationLogo" alt="Logo ARDC Santana" class="h-11 w-11 rounded bg-white object-contain p-2 shadow-sm">
                    <span class="font-display text-lg text-slate-900">ARDC Santana</span>
                </Link>

                <div class="hidden items-center gap-1 md:flex">
                    <Link v-for="item in navItems" :key="item[0]" :href="item[1]" class="rounded-md px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">
                        {{ item[0] }}
                    </Link>
                </div>

                <a :href="`mailto:${contactEmail}`" class="hidden rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-slate-800 lg:inline-flex">
                    Contactar
                </a>
            </nav>

            <div class="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-5 pb-3 md:hidden">
                <Link v-for="item in navItems" :key="item[0]" :href="item[1]" class="shrink-0 rounded-md bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700">
                    {{ item[0] }}
                </Link>
            </div>
        </header>

        <slot />

        <footer class="bg-slate-900 py-12 text-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 md:grid-cols-[1fr_1.2fr] lg:px-8">
                <div>
                    <div class="font-display flex items-center gap-3 text-lg">
                        <img :src="associationLogo" alt="" class="h-11 w-11 rounded bg-white/10 object-contain p-2">
                        ARDC Santana
                    </div>
                    <div class="mt-4 space-y-1 text-sm text-slate-300">
                        <p>Santana, Carvalhal Benfeito, Caldas da Rainha</p>
                        <p><a :href="`mailto:${contactEmail}`" class="hover:text-white">{{ contactEmail }}</a></p>
                        <p>Facebook e Instagram: @ardcsantana</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-x-5 gap-y-3 text-sm font-medium md:justify-end">
                    <Link v-for="item in navItems" :key="item[0]" :href="item[1]" class="text-slate-300 hover:text-white">
                        {{ item[0] }}
                    </Link>
                    <Link :href="route('legal.privacidade')" class="text-slate-300 hover:text-white">Política de Privacidade</Link>
                    <Link :href="route('legal.termos')" class="text-slate-300 hover:text-white">Termos e Condições</Link>
                    <Link :href="route('legal.cookies')" class="text-slate-300 hover:text-white">Política de Cookies</Link>
                    <a href="https://www.livroreclamacoes.pt" target="_blank" rel="noopener" class="text-slate-300 hover:text-white">Livro de Reclamações</a>
                </div>
            </div>
            <div class="mx-auto mt-8 flex max-w-7xl flex-col gap-2 border-t border-slate-700 px-5 pt-5 text-xs text-slate-400 md:flex-row md:items-center md:justify-between lg:px-8">
                <p>Copyright © {{ year }} Associação de Santana.</p>
                <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-semibold text-slate-300 hover:text-white">
                    #CreatingDevelopingImproving4you
                </a>
            </div>
        </footer>

        <CookieBanner />
    </div>
</template>
