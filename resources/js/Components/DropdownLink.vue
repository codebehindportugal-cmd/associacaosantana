<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="flex min-h-11 w-full items-center px-4 py-2 text-start text-[15px] font-semibold leading-5 text-tinta transition duration-150 ease-in-out hover:bg-fundo focus:bg-fundo focus:outline-none"
    >
        <slot />
    </Link>
</template>
