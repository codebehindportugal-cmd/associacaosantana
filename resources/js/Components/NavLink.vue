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
        ? 'inline-flex min-h-11 items-center px-1 pt-1 border-b-2 border-verde text-[15px] font-bold leading-5 text-tinta focus:outline-none focus:border-verde-escuro transition duration-150 ease-in-out'
        : 'inline-flex min-h-11 items-center px-1 pt-1 border-b-2 border-transparent text-[15px] font-semibold leading-5 text-suave hover:text-tinta hover:border-linha-forte focus:outline-none focus:text-tinta focus:border-linha-forte transition duration-150 ease-in-out',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
