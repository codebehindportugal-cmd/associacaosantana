<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    chamadas: {
        type: Array,
        default: () => [],
    },
})

const agora = ref(new Date())
let relogio = null
let refresh = null

const principal = computed(() => props.chamadas[0] ?? null)
const emEspera  = computed(() => props.chamadas.slice(1))

const horaReserva = (r) => r.hora?.slice(0, 5) ?? '--:--'

const horaChamada = (r) => {
    if (!r.chamada_em) return ''
    return new Date(r.chamada_em).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' })
}

const horaAtual = computed(() =>
    agora.value.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
)

onMounted(() => {
    relogio = setInterval(() => (agora.value = new Date()), 1000)
    refresh  = setInterval(() => router.reload({ preserveScroll: true }), 5000)
})

onBeforeUnmount(() => {
    clearInterval(relogio)
    clearInterval(refresh)
})
</script>

<template>
    <main class="ecra-chamadas flex min-h-screen flex-col items-center justify-between bg-gray-950 p-6 text-white">

        <!-- Cabeçalho -->
        <header class="w-full max-w-5xl">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black tracking-widest text-amber-400 uppercase">ARDC Santana</h1>
                    <p class="text-sm font-bold text-gray-500 uppercase tracking-widest">Sistema de chamadas</p>
                </div>
                <div class="text-right">
                    <p class="text-4xl font-black tabular-nums text-white">{{ horaAtual }}</p>
                </div>
            </div>
        </header>

        <!-- Zona principal -->
        <section class="flex w-full max-w-5xl flex-1 flex-col items-center justify-center py-10">

            <!-- Ninguém chamado -->
            <div v-if="!principal" class="flex flex-col items-center gap-6 opacity-30">
                <svg class="h-24 w-24 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <p class="text-4xl font-black text-gray-600 uppercase tracking-widest">Aguardando chamadas...</p>
            </div>

            <!-- Chamada activa -->
            <template v-else>
                <p class="mb-4 text-2xl font-black uppercase tracking-[0.3em] text-amber-400">A CHAMAR</p>

                <div class="mb-6 w-full rounded-3xl border-4 border-amber-400 bg-amber-400/10 px-10 py-10 text-center shadow-[0_0_80px_rgba(251,191,36,0.25)]">
                    <p class="text-[clamp(3rem,10vw,7rem)] font-black uppercase leading-none tracking-tight text-white">
                        {{ principal.nome }}
                    </p>
                    <div class="mt-6 flex items-center justify-center gap-8 text-amber-300">
                        <span class="text-4xl font-black">{{ principal.pessoas }} PESSOAS</span>
                        <span class="text-4xl font-black">·</span>
                        <span class="text-4xl font-black">{{ horaReserva(principal) }}</span>
                    </div>
                    <p class="mt-4 text-xl font-bold text-amber-500/70">Chamada às {{ horaChamada(principal) }}</p>
                </div>

                <!-- Em espera -->
                <div v-if="emEspera.length" class="w-full">
                    <p class="mb-3 text-center text-lg font-black uppercase tracking-widest text-gray-500">Também em espera</p>
                    <div class="grid gap-3" :class="emEspera.length === 1 ? 'grid-cols-1 max-w-md mx-auto' : 'grid-cols-2 sm:grid-cols-3'">
                        <div
                            v-for="r in emEspera"
                            :key="r.id"
                            class="rounded-2xl border-2 border-gray-700 bg-gray-900 px-5 py-4 text-center"
                        >
                            <p class="truncate text-2xl font-black text-white">{{ r.nome }}</p>
                            <p class="mt-1 text-base font-bold text-gray-400">{{ r.pessoas }} PESSOAS · {{ horaReserva(r) }}</p>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <!-- Rodapé -->
        <footer class="w-full max-w-5xl">
            <p class="text-center text-sm font-bold text-gray-700 uppercase tracking-widest">
                Dirija-se ao gestor de sala ao entrar
            </p>
        </footer>

    </main>
</template>

<style scoped>
.ecra-chamadas {
    height: 100dvh;
    overflow: hidden;
}
</style>
