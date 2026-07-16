<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

/**
 * Alerta global de chamadas de cliente para o POS.
 * Incluir em qualquer página POS — faz polling a cada 10s e
 * mostra banner fixo + som enquanto houver chamadas pendentes.
 */
const chamadas = ref([]);
let timer = null;
let audioCtx = null;

const tocarSom = () => {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        const beep = (inicio, freq) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'square';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.25, audioCtx.currentTime + inicio);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + inicio + 0.3);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start(audioCtx.currentTime + inicio);
            osc.stop(audioCtx.currentTime + inicio + 0.35);
        };
        beep(0, 880);
        beep(0.4, 660);
        beep(0.8, 880);
    } catch {
        // sem audio disponivel
    }
};

const verificar = async () => {
    try {
        const res = await fetch(route('pos.comum.chamadas'), { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        const antes = chamadas.value.length;
        chamadas.value = data.chamadas ?? [];
        if (chamadas.value.length && chamadas.value.length >= antes) {
            tocarSom();
        }
    } catch {
        // ignora erros de rede
    }
};

const atender = async (chamada) => {
    chamadas.value = chamadas.value.filter((c) => c.id !== chamada.id);
    try {
        await fetch(route('pos.comum.chamadas.confirmar', chamada.id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });
    } catch {
        // ignora
    }
};

onMounted(() => {
    verificar();
    timer = setInterval(verificar, 10000);
});

onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div v-if="chamadas.length" class="fixed inset-x-0 top-0 z-[70] bg-red-600 text-white shadow-lg">
        <div class="mx-auto flex max-w-4xl flex-col gap-2 px-4 py-3">
            <div v-for="chamada in chamadas" :key="chamada.id" class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="animate-pulse text-2xl">🔔</span>
                    <div>
                        <div class="text-lg font-black leading-tight">{{ chamada.mesa }} chamou!</div>
                        <div class="text-xs font-bold text-white/80">{{ chamada.ha_quanto }}</div>
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-white px-4 py-2 text-sm font-black text-red-700 hover:bg-red-50"
                    @click="atender(chamada)"
                >
                    ATENDIDO
                </button>
            </div>
        </div>
    </div>
</template>
