<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta transition placeholder:text-suave-2 focus:border-verde focus:outline-none focus:ring-2 focus:ring-verde/25 disabled:bg-fundo disabled:text-suave"
        v-model="model"
        ref="input"
    />
</template>
