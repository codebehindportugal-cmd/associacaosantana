<script setup>
import { ref } from 'vue';

const props = defineProps({
    operadorNome: { type: String, default: '' },
});

const emit = defineEmits(['fechar']);

const locais = ['Bar', 'Restaurante', 'Receção', 'Palco', 'Bilheteira', 'Exterior', 'WC', 'Cozinha'];
const localSelecionado = ref('');
const enviando = ref(false);
const sucesso = ref(false);
const erro = ref('');

const chamar = async () => {
    if (!localSelecionado.value || enviando.value) return;
    enviando.value = true;
    erro.value = '';
    try {
        const res = await fetch(route('pos.comissao.chamar'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                operador_nome: props.operadorNome || 'Funcionário',
                local: localSelecionado.value,
            }),
        });
        if (res.ok) {
            sucesso.value = true;
            setTimeout(() => emit('fechar'), 2500);
        } else {
            erro.value = 'Não foi possível enviar. Tente novamente.';
        }
    } catch {
        erro.value = 'Erro de ligação. Tente novamente.';
    } finally {
        enviando.value = false;
    }
};
</script>

<template>
    <!-- Backdrop -->
    <div class="fixed inset-0 z-50 flex items-end bg-black/60 sm:items-center" @click.self="$emit('fechar')">
        <div class="w-full rounded-t-2xl bg-gray-900 p-5 text-white shadow-xl sm:mx-auto sm:max-w-md sm:rounded-2xl">

            <!-- Sucesso -->
            <div v-if="sucesso" class="py-10 text-center">
                <div class="mb-3 text-6xl">✅</div>
                <p class="text-2xl font-black">Comissão chamada!</p>
                <p class="mt-2 text-sm font-semibold text-gray-300">
                    Um membro vai até <strong class="text-amber-400">{{ localSelecionado }}</strong>.
                </p>
            </div>

            <!-- Formulário -->
            <template v-else>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-xl font-black">🎉 CHAMAR COMISSÃO</h2>
                    <button type="button" class="rounded-lg bg-gray-700 px-3 py-2 text-sm font-black hover:bg-gray-600" @click="$emit('fechar')">✕</button>
                </div>

                <p class="mb-4 text-sm font-bold text-gray-300">Onde estás? Um membro da comissão irá ter contigo.</p>

                <div class="mb-5 grid grid-cols-2 gap-2">
                    <button
                        v-for="local in locais"
                        :key="local"
                        type="button"
                        class="rounded-xl py-4 text-sm font-black transition"
                        :class="localSelecionado === local
                            ? 'bg-amber-500 text-black'
                            : 'bg-gray-700 text-white hover:bg-gray-600'"
                        @click="localSelecionado = local"
                    >
                        {{ local }}
                    </button>
                </div>

                <div v-if="erro" class="mb-3 rounded-lg bg-red-700 p-3 text-sm font-bold">
                    {{ erro }}
                </div>

                <button
                    type="button"
                    class="w-full rounded-xl bg-amber-500 p-4 text-lg font-black text-black transition disabled:opacity-40"
                    :disabled="!localSelecionado || enviando"
                    @click="chamar"
                >
                    {{ enviando ? 'A enviar...' : '📣 CHAMAR COMISSÃO' }}
                </button>
            </template>

        </div>
    </div>
</template>
