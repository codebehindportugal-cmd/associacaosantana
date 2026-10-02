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
        const res = await fetch(route('pos.comum.comissao.chamar'), {
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
        } else if (res.status === 422) {
            const corpo = await res.json().catch(() => ({}));
            const msgs = corpo.errors ? Object.values(corpo.errors).flat() : [];
            erro.value = msgs.join(' ') || corpo.message || 'Dados inválidos. Verifica e tenta novamente.';
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
        class="fixed inset-0 z-50 flex items-end bg-escuro/70 font-sans" role="dialog" aria-modal="true" aria-label="Chamar comissão"
        @click.self="$emit('fechar')"
    >
        <!-- Sheet -->
        <div class="mx-auto flex w-full max-w-2xl flex-col rounded-t-[22px] bg-escuro text-white shadow-2xl"
             style="max-height: 92dvh; padding-bottom: max(env(safe-area-inset-bottom), 1.25rem);">

            <!-- Drag handle -->
            <div class="mx-auto mb-1 mt-3 h-1 w-10 shrink-0 rounded-full bg-escuro-2"></div>

            <!-- Scrollable content -->
            <div class="overflow-y-auto px-5 pt-3 pb-2">

                <!-- Sucesso -->
                <div v-if="sucesso" class="py-10 text-center">
                    <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-verde text-white"><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7" /></svg></div>
                    <p class="text-2xl font-extrabold">Comissão chamada!</p>
                    <p class="mt-2 text-base font-semibold text-escuro-inativo">
                        Um membro vai até <strong class="text-white">{{ localSelecionado }}</strong>.
                    </p>
                </div>

                <template v-else>
                    <!-- Cabeçalho -->
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="flex items-center gap-2.5 text-xl font-extrabold"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11v2a1 1 0 0 0 1 1h3l5 4V6L7 10H4a1 1 0 0 0-1 1zM16 9a4 4 0 0 1 0 6M19 6a8 8 0 0 1 0 12" /></svg>Chamar Comissão</h2>
                        <button
                            type="button"
                            class="flex h-14 w-14 items-center justify-center rounded-[10px] bg-escuro-2 text-white active:bg-suave"
                            aria-label="Fechar"
                            @click="$emit('fechar')"
                        ><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg></button>
                    </div>

                    <p class="mb-4 text-base font-semibold text-escuro-inativo">Onde estás?</p>

                    <!-- Grelha de locais -->
                    <div class="mb-5 grid grid-cols-2 gap-2.5">
                        <button
                            v-for="local in locais"
                            :key="local"
                            type="button"
                            class="min-h-[64px] rounded-[14px] px-3 py-4 text-lg font-extrabold transition active:scale-95"
                            :class="localSelecionado === local
                                ? 'bg-laranja text-white ring-2 ring-white'
                                : 'bg-escuro-2 text-white active:bg-suave'"
                            :aria-pressed="localSelecionado === local"
                            @click="localSelecionado = local"
                        >
                            {{ local }}
                        </button>
                    </div>

                    <!-- Erro -->
                    <div v-if="erro" class="mb-3 rounded-[10px] bg-perigo-claro p-3 text-base font-bold text-perigo-texto" role="alert">
                        {{ erro }}
                    </div>

                    <!-- Botão principal -->
                    <button
                        type="button"
                        class="flex min-h-[72px] w-full items-center justify-center gap-3 rounded-[14px] bg-laranja py-5 text-xl font-extrabold text-white transition active:scale-95 disabled:opacity-40"
                        :disabled="!localSelecionado || enviando"
                        @click="chamar"
                    >
                        <template v-if="!enviando"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11v2a1 1 0 0 0 1 1h3l5 4V6L7 10H4a1 1 0 0 0-1 1zM16 9a4 4 0 0 1 0 6M19 6a8 8 0 0 1 0 12" /></svg></template>
                        {{ enviando ? 'A enviar...' : 'Chamar comissão' }}
                    </button>
                </template>
            </div>
        </div>
    </div>
</template>
