<script setup>
import { Link } from '@inertiajs/vue3';

const logo = '/images/santana-logo.png';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-6 bg-fundo px-4 py-10 font-sans text-tinta">
        <Link :href="route('home')" class="flex flex-col items-center gap-2.5 text-tinta no-underline hover:text-tinta">
            <img :src="logo" alt="ARDC Santana" class="h-[72px] w-[72px] rounded-full border border-linha bg-white object-contain p-1.5" />
            <span class="text-[15px] font-bold text-suave">Associação de Santana</span>
        </Link>

        <main class="box-border flex w-full max-w-[420px] flex-col gap-5 rounded-[14px] border border-linha bg-white px-7 py-8 max-[420px]:px-5">
            <slot />
        </main>

        <nav aria-label="Legal" class="flex gap-5 text-sm">
            <Link :href="route('legal.privacidade')" class="inline-flex min-h-11 items-center text-suave-2 hover:text-verde">Privacidade</Link>
            <Link :href="route('legal.termos')" class="inline-flex min-h-11 items-center text-suave-2 hover:text-verde">Termos</Link>
            <Link :href="route('legal.cookies')" class="inline-flex min-h-11 items-center text-suave-2 hover:text-verde">Cookies</Link>
        </nav>
    </div>
</template>
