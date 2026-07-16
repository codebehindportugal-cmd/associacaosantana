<script setup>
import { useForm } from '@inertiajs/vue3';
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
    <main class="flex min-h-screen flex-col items-center justify-center bg-slate-950 px-6 text-white">
        <div class="w-full max-w-xs text-center">
            <div class="mb-2 text-xs font-black uppercase tracking-widest text-amber-400">ARDC Santana</div>
            <h1 class="mb-1 text-4xl font-black">Mesa {{ pedido.mesa }}</h1>

            <div v-if="!pedido.disponivel" class="mt-8 rounded-2xl border border-amber-400/40 bg-amber-400/10 p-6">
                <p class="font-bold text-amber-200">Este pedido já foi fechado. Chame um elemento da equipa pessoalmente.</p>
            </div>

            <template v-else-if="chamado">
                <div class="mt-10 flex flex-col items-center gap-4">
                    <div class="text-7xl">✅</div>
                    <p class="text-2xl font-black text-emerald-400">Funcionário chamado!</p>
                    <p class="text-sm font-semibold text-slate-400">Aguarde um momento, estamos a chegar.</p>
                    <button
                        type="button"
                        class="mt-6 w-full rounded-2xl bg-white/10 px-6 py-4 text-base font-black"
                        @click="chamado = false"
                    >
                        Chamar novamente
                    </button>
                </div>
            </template>

            <template v-else>
                <p class="mt-3 text-sm font-semibold text-slate-400">Precisa de ajuda? Carrega no botão para chamar um funcionário.</p>
                <button
                    type="button"
                    class="mt-10 w-full rounded-2xl bg-amber-400 px-6 py-6 text-2xl font-black text-slate-950 shadow-lg active:scale-95 disabled:opacity-50"
                    :disabled="form.processing"
                    @click="chamar"
                >
                    {{ form.processing ? 'A chamar...' : '🔔 Chamar Funcionário' }}
                </button>
                <p class="mt-4 text-xs text-slate-600">Um elemento da equipa virá à sua mesa.</p>
            </template>
        </div>
    </main>
</template>
