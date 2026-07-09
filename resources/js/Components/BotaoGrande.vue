<script setup>
defineProps({ label: String, cor: { type: String, default: 'azul' }, icone: String, disabled: Boolean, loading: Boolean });

const cores = {
    verde: 'bg-emerald-600 hover:bg-emerald-700 text-white',
    azul: 'bg-blue-600 hover:bg-blue-700 text-white',
    vermelho: 'bg-red-600 hover:bg-red-700 text-white',
    cinza: 'bg-slate-700 hover:bg-slate-800 text-white',
};
</script>

<template>
    <button
        type="button"
        class="min-h-12 rounded-xl px-5 py-4 text-base font-black shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60"
        :class="cores[cor] ?? cores.azul"
        :disabled="disabled || loading"
    >
        <span v-if="icone" class="mr-2">{{ icone }}</span>{{ loading ? 'A processar...' : label }}
    </button>
</template>
