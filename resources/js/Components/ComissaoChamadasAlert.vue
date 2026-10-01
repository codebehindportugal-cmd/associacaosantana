<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

/**
 * Alerta de chamadas à comissão num POS em modo comissão.
 * Só faz polling quando a sessão POS está em modo comissão.
 */
const page = usePage();
const ativo = computed(() => !!page.props.pos_comissao);
const nomeMembro = computed(() => page.props.pos_comissao_nome || '');

const chamadas = ref([]);
let timer = null;
let audioCtx = null;

const tocarSom = () => {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        const beep = (inicio, freq) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.25, audioCtx.currentTime + inicio);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + inicio + 0.35);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start(audioCtx.currentTime + inicio);
            osc.stop(audioCtx.currentTime + inicio + 0.4);
        };
        beep(0, 523);
        beep(0.45, 659);
        beep(0.9, 784);
    } catch {
        // sem audio
    }
};

const verificar = async () => {
    if (!ativo.value) return;
    try {
        const res = await fetch(route('pos.comum.comissao.pendentes'), { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        const antes = chamadas.value.length;
        chamadas.value = data.chamadas ?? [];
        if (chamadas.value.length > antes) {
            tocarSom();
        }
    } catch {
        // ignora
    }
};

const atender = async (chamada) => {
    // Já se identificou ao entrar em modo comissão — não se volta a pedir o nome
    const nome = nomeMembro.value;
    chamadas.value = chamadas.value.filter((c) => c.id !== chamada.id);
    try {
        await fetch(route('pos.comum.comissao.atender', chamada.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
            body: JSON.stringify({ nome: nome || null }),
        });
    } catch {
        // ignora
    }
};

onMounted(() => {
    if (!ativo.value) return;
    verificar();
    timer = setInterval(verificar, 15000);
});

onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div v-if="ativo && chamadas.length" class="fixed inset-x-0 top-0 z-[69] bg-laranja font-sans text-white shadow-lg" role="alert">
        <div class="mx-auto flex max-w-4xl flex-col gap-2 px-4 py-3">
            <div v-for="chamada in chamadas" :key="chamada.id" class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 animate-pulse items-center justify-center rounded-[10px] bg-white/15"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg></span>
                    <div>
                        <div class="text-lg font-extrabold leading-tight">{{ chamada.operador_nome }} chama a comissão — {{ chamada.local }}</div>
                        <div class="text-sm font-semibold text-white/85 tabular-nums">{{ chamada.criado_em }}</div>
                    </div>
                </div>
                <button
                    type="button"
                    class="h-12 shrink-0 rounded-[10px] bg-white px-5 text-base font-extrabold text-laranja-texto transition hover:bg-laranja-claro"
                    @click="atender(chamada)"
                >
                    Atender
                </button>
            </div>
        </div>
    </div>
</template>
