<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';

const year = new Date().getFullYear();
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>

        <footer class="mt-6 px-4 text-center text-xs text-gray-500">
            <p>Copyright © {{ year }} Associação de Santana.</p>
            <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-semibold text-gray-700 underline underline-offset-2">
                #CreatingDevelopingImproving4you
            </a>
        </footer>
    </div>
</template>
