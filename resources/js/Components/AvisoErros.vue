<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Aviso com as mensagens de erro de validação que não aparecem junto de um campo.
// - errors: objeto de erros (form.errors); por omissão usa page.props.errors.
// - excluir: chaves que já são mostradas por baixo do respetivo campo.
// - apenas: se indicado, mostra só estas chaves.
const props = defineProps({
    errors: { type: Object, default: null },
    excluir: { type: Array, default: () => [] },
    apenas: { type: Array, default: null },
});

const page = usePage();

const mensagens = computed(() => {
    const erros = props.errors ?? page.props.errors ?? {};
    return Object.entries(erros)
        .filter(([chave, msg]) => msg && !props.excluir.includes(chave) && (!props.apenas || props.apenas.includes(chave)))
        .map(([chave, msg]) => ({ chave, msg: Array.isArray(msg) ? msg.join(' ') : msg }));
});
</script>

<template>
    <div v-if="mensagens.length" role="alert" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-semibold text-perigo-texto">
        <div v-for="item in mensagens" :key="item.chave">{{ item.msg }}</div>
    </div>
</template>
