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
    const nome = window.prompt('Quem vai atender? (opcional)', nomeMembro.value) ?? '';
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
    <div v-if="ativo && chamadas.length" class="fixed inset-x-0 top-0 z-[69] bg-amber-500 text-black shadow-lg">
        <div class="mx-auto flex max-w-4xl flex-col gap-2 px-4 py-3">
            <div v-for="chamada in chamadas" :key="chamada.id" class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="animate-pulse text-2xl">🎉</span>
                    <div>
                        <div class="text-lg font-black leading-tight">{{ chamada.operador_nome }} chama a comissão — {{ chamada.local }}</div>
                        <div class="text-xs font-bold text-black/70">{{ chamada.criado_em }}</div>
                    </div>
                </div>
                <button
                    type="button"
                    class="rounded-lg bg-black px-4 py-2 text-sm font-black text-amber-400 hover:bg-black/80"
                    @click="atender(chamada)"
                >
                    ATENDER
                </button>
            </div>
        </div>
    </div>
</template>
