<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const visible = ref(false);

onMounted(() => {
    visible.value = !localStorage.getItem('cookie_consent');
});

const setConsent = (value) => {
    localStorage.setItem('cookie_consent', value);
    visible.value = false;
};
</script>

<template>
    <div
        v-if="visible"
        role="region"
        aria-label="Cookies"
        class="fixed inset-x-4 bottom-4 z-50 mx-auto box-border flex max-w-[760px] flex-wrap items-center justify-between gap-x-5 gap-y-3 rounded-[14px] bg-escuro py-3.5 pl-5 pr-4 font-sans text-white shadow-[0_12px_32px_rgba(22,32,28,.28)]"
    >
        <p class="m-0 text-[15px] text-escuro-inativo">
            Utilizamos cookies para melhorar a sua experiência.
            <Link :href="route('legal.cookies')" class="font-bold text-white underline hover:text-verde-claro">Saiba mais</Link>.
        </p>
        <div class="flex gap-2">
            <button type="button" class="h-11 rounded-[10px] bg-verde px-[18px] text-[15px] font-bold text-white transition hover:bg-verde-escuro" @click="setConsent('all')">
                Aceitar
            </button>
            <button type="button" class="h-11 rounded-[10px] bg-escuro-2 px-[18px] text-[15px] font-bold text-white transition hover:bg-suave" @click="setConsent('essential')">
                Só essenciais
            </button>
        </div>
    </div>
</template>
