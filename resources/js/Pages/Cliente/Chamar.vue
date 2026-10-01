<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    token: String,
    pedido: Object,
});

const chamado = ref(false);
const form = useForm({});

const chamar = () => {
    if (form.processing || chamado.value) return;

    form.post(route('cliente.chamar', props.token), {
        preserveScroll: true,
        onSuccess: () => {
            chamado.value = true;
        },
    });
};
</script>

<template>
    <main class="flex min-h-screen flex-col bg-fundo font-sans text-tinta">
        <header class="bg-escuro text-white">
            <div class="mx-auto flex max-w-xl flex-col gap-0.5 px-4 py-3.5">
                <span class="text-xs font-bold uppercase tracking-[.08em] text-[#8FD3B5]">ARDC Santana</span>
                <span class="text-[26px] font-extrabold leading-none">{{ pedido.mesa }}</span>
            </div>
        </header>

        <div class="mx-auto flex w-full max-w-xl flex-1 flex-col justify-center px-5 py-6 text-center">
            <div v-if="!pedido.disponivel" class="rounded-[14px] border border-laranja/30 bg-laranja-claro p-6 text-lg font-bold text-laranja-texto">
                Este pedido já foi fechado. Chame um elemento da equipa pessoalmente.
            </div>

            <div v-else-if="chamado" role="status" class="flex flex-col items-center gap-5">
                <div class="flex h-[140px] w-[140px] items-center justify-center rounded-full bg-verde-claro2 text-verde">
                    <svg width="72" height="72" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                </div>
                <h1 class="text-[32px] font-extrabold text-verde">Funcionário chamado!</h1>
                <p class="text-lg font-medium text-suave">Aguarde um momento, estamos a chegar.</p>
                <button
                    type="button"
                    class="mt-4 h-[60px] w-full rounded-xl border border-linha-forte bg-white text-lg font-extrabold text-tinta"
                    @click="chamado = false"
                >
                    Chamar novamente
                </button>
            </div>

            <div v-else class="flex flex-col gap-7">
                <div class="flex flex-col gap-2.5">
                    <h1 class="text-[30px] font-extrabold leading-tight">Precisa de ajuda?</h1>
                    <p class="text-lg font-medium leading-snug text-suave">Carrega no botão para chamar um funcionário.</p>
                </div>
                <button
                    type="button"
                    class="flex h-[200px] flex-col items-center justify-center gap-3.5 rounded-[28px] bg-laranja text-white shadow-[0_10px_28px_rgba(194,87,12,.35)] transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-45"
                    :disabled="form.processing"
                    @click="chamar"
                >
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                    <span class="text-[28px] font-extrabold">{{ form.processing ? 'A chamar...' : 'Chamar funcionário' }}</span>
                </button>
                <p class="text-base text-suave-2">Um elemento da equipa virá à sua mesa.</p>
            </div>
        </div>

        <footer v-if="pedido.disponivel" class="border-t border-linha bg-white">
            <div class="mx-auto max-w-xl px-4 pb-5 pt-3">
                <Link
                    :href="route('cliente.mesa', token)"
                    class="flex h-14 items-center justify-center gap-2 rounded-xl border border-linha-forte text-[17px] font-bold text-tinta"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
                    Voltar ao pedido
                </Link>
            </div>
        </footer>
    </main>
</template>
