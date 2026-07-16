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
        } else if (res.status === 419) {
            // CSRF token expirado — recarregar a página renova o token
            erro.value = 'Sessão expirada. A recarregar a página...';
            setTimeout(() => window.location.reload(), 1500);
        } else {
            erro.value = 'Erro ' + res.status + '. Tente novamente.';
        }
    } catch (e) {
        erro.value = 'Erro de ligação. Verifica a rede e tenta novamente.';
    } finally {
        enviando.value = false;
    }
};
</script>

<template>
    <!-- Backdrop -->
    <div
        class="fixed inset-0 z-50 flex items-end bg-black/70"
        @click.self="$emit('fechar')"
    >
        <!-- Sheet -->
        <div class="w-full rounded-t-3xl bg-gray-950 text-white shadow-2xl flex flex-col"
             style="max-height: 92dvh; padding-bottom: max(env(safe-area-inset-bottom), 1.25rem);">

            <!-- Drag handle -->
            <div class="mx-auto mt-3 mb-1 h-1 w-10 rounded-full bg-gray-700 shrink-0"></div>

            <!-- Scrollable content -->
            <div class="overflow-y-auto px-5 pt-3 pb-2">

                <!-- Sucesso -->
                <div v-if="sucesso" class="py-10 text-center">
                    <div class="mb-3 text-6xl">✅</div>
                    <p class="text-2xl font-black">Comissão chamada!</p>
                    <p class="mt-2 text-sm font-semibold text-gray-400">
                        Um membro vai até <strong class="text-amber-400">{{ localSelecionado }}</strong>.
                    </p>
                </div>

                <template v-else>
                    <!-- Cabeçalho -->
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-black">🎉 Chamar Comissão</h2>
                        <button
                            type="button"
                            class="rounded-xl bg-gray-800 px-3 py-2 text-sm font-black active:bg-gray-700"
                            @click="$emit('fechar')"
                        >✕</button>
                    </div>

                    <p class="mb-4 text-sm font-semibold text-gray-400">Onde estás?</p>

                    <!-- Grelha de locais -->
                    <div class="mb-5 grid grid-cols-2 gap-2.5">
                        <button
                            v-for="local in locais"
                            :key="local"
                            type="button"
                            class="rounded-2xl py-5 text-base font-black transition active:scale-95"
                            :class="localSelecionado === local
                                ? 'bg-amber-500 text-black'
                                : 'bg-gray-800 text-white active:bg-gray-700'"
                            @click="localSelecionado = local"
                        >
                            {{ local }}
                        </button>
                    </div>

                    <!-- Erro -->
                    <div v-if="erro" class="mb-3 rounded-xl bg-red-900/80 p-3 text-sm font-bold text-red-200">
                        {{ erro }}
                    </div>

                    <!-- Botão principal -->
                    <button
                        type="button"
                        class="w-full rounded-2xl bg-amber-500 py-5 text-lg font-black text-black transition active:scale-95 disabled:opacity-40"
                        :disabled="!localSelecionado || enviando"
                        @click="chamar"
                    >
                        {{ enviando ? 'A enviar...' : '📣 CHAMAR COMISSÃO' }}
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
