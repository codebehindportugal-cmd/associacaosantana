<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex min-h-11 items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-5 py-2.5 text-base font-bold text-tinta transition hover:bg-fundo focus:outline-none focus-visible:ring-2 focus-visible:ring-verde focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-45"
    >
        <slot />
    </button>
</template>
