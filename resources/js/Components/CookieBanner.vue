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
    <div v-if="visible" class="fixed inset-x-0 bottom-0 z-50 bg-slate-950 p-4 text-white shadow-2xl">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 md:flex-row">
            <p class="text-sm text-slate-200">
                Utilizamos cookies para melhorar a sua experiência.
                <Link :href="route('legal.cookies')" class="font-semibold underline">Saiba mais</Link>.
            </p>
            <div class="flex gap-3">
                <button type="button" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700" @click="setConsent('all')">
                    Aceitar
                </button>
                <button type="button" class="rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-600" @click="setConsent('essential')">
                    Só essenciais
                </button>
            </div>
        </div>
    </div>
</template>
