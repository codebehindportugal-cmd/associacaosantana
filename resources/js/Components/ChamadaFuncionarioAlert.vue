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
        // 302 ou 401 significa sessão POS expirada — redireciona para login
        if (res.status === 401 || res.redirected) {
            window.location.href = route('pos.login');
            return;
        }
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

// Lê o XSRF-TOKEN do cookie (atualizado pelo Laravel em cada resposta)
// mais robusto que o meta tag que fica obsoleto quando a sessão expira
const xsrfToken = () => {
    const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : (document.querySelector('meta[name="csrf-token"]')?.content ?? '');
};

const atender = async (chamada) => {
    // Remove localmente de imediato (optimistic update)
    chamadas.value = chamadas.value.filter((c) => c.id !== chamada.id);
    try {
        const res = await fetch(route('pos.comum.chamadas.confirmar', chamada.id), {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': xsrfToken(),
                Accept: 'application/json',
            },
        });
        // Se falhou (ex: sessão expirou), volta a buscar para repor o estado correto
        if (!res.ok) {
            await verificar();
        }
    } catch {
        await verificar();
    }
};

onMounted(() => {
    verificar();
    timer = setInterval(verificar, 10000);
});

onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div v-if="chamadas.length" class="fixed inset-x-0 top-0 z-[70] bg-perigo font-sans text-white shadow-lg" role="alert">
        <div class="mx-auto flex max-w-4xl flex-col gap-2 px-4 py-3">
            <div v-for="chamada in chamadas" :key="chamada.id" class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 animate-pulse items-center justify-center rounded-[10px] bg-white/15"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg></span>
                    <div>
                        <div class="text-lg font-extrabold leading-tight">{{ chamada.mesa }} chamou!</div>
                        <div class="text-sm font-semibold text-white/85 tabular-nums">{{ chamada.ha_quanto }}</div>
                    </div>
                </div>
                <button
                    type="button"
                    class="h-12 shrink-0 rounded-[10px] bg-white px-5 text-base font-extrabold text-perigo-texto transition hover:bg-perigo-claro"
                    @click="atender(chamada)"
                >
                    Atendido
                </button>
            </div>
        </div>
    </div>
</template>
