<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'flex min-h-11 w-full items-center ps-3 pe-4 py-2 border-l-4 border-verde text-start text-base font-bold text-verde-escuro bg-verde-claro focus:outline-none focus:bg-verde-claro2 focus:border-verde-escuro transition duration-150 ease-in-out'
        : 'flex min-h-11 w-full items-center ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-semibold text-suave hover:text-tinta hover:bg-fundo hover:border-linha-forte focus:outline-none focus:text-tinta focus:bg-fundo focus:border-linha-forte transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
