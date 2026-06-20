<script setup>
import { Link } from '@inertiajs/vue3';

const logo = '/images/santana-logo.png';
const year = new Date().getFullYear();
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-amber-50 px-4 py-12">
        <div class="mb-8 text-center">
            <Link href="/" class="inline-flex flex-col items-center gap-3">
                <img :src="logo" alt="ARDC Santana" class="h-16 w-16 rounded-full border border-amber-200 bg-white object-contain p-2 shadow-sm">
                <span class="font-display text-lg font-semibold text-stone-800">ARDC Santana</span>
            </Link>
        </div>

        <div class="w-full max-w-md rounded-xl border border-amber-200 bg-white p-8 shadow-md">
            <slot />
        </div>

        <footer class="mt-8 text-center text-xs text-stone-400">
            <p>Copyright © {{ year }} Associação de Santana.</p>
            <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-semibold text-stone-500 hover:text-amber-700 transition">
                #CreatingDevelopingImproving4you
            </a>
        </footer>
    </div>
</template>
