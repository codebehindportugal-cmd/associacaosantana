<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    chamadas: {
        type: Array,
        default: () => [],
    },
})

let refresh = null

const principal = computed(() => props.chamadas[0] ?? null)
const emEspera  = computed(() => props.chamadas.slice(1))

const temEspera = computed(() => emEspera.value.length > 0)

const fontSizeNome = computed(() => {
    const len = (principal.value?.nome ?? '').length
    const compacto = temEspera.value || principal.value?.mesa_atribuida
    if (compacto) {
        if (len <= 6)  return 'clamp(3rem,9vh,7rem)'
        if (len <= 10) return 'clamp(2.5rem,7vh,5.5rem)'
        if (len <= 15) return 'clamp(2rem,5.5vh,4.5rem)'
        if (len <= 22) return 'clamp(1.5rem,4vh,3.5rem)'
        return 'clamp(1.25rem,3.5vh,3rem)'
    }
    if (len <= 6)  return 'clamp(4rem,14vh,11rem)'
    if (len <= 10) return 'clamp(3rem,10vh,8rem)'
    if (len <= 15) return 'clamp(2.5rem,8vh,6.5rem)'
    if (len <= 22) return 'clamp(2rem,6vh,5rem)'
    return 'clamp(1.5rem,5vh,4rem)'
})

onMounted(() => {
    refresh = setInterval(() => router.reload({ preserveScroll: true }), 5000)
})

onBeforeUnmount(() => {
    clearInterval(refresh)
})
</script>

<template>
    <main class="ecra-chamadas">

        <!-- Cabecalho -->
        <header class="ecra-header">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-widest text-amber-400 uppercase">ARDC Santana</h1>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Sistema de chamadas</p>
                </div>
            </div>
        </header>

        <!-- Zona principal -->
        <section class="ecra-body">

            <!-- Ninguem chamado -->
            <div v-if="!principal" class="flex flex-col items-center gap-6 opacity-30">
                <svg class="h-20 w-20 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <p class="text-3xl font-black text-gray-600 uppercase tracking-widest">Aguardando chamadas...</p>
            </div>

            <!-- Chamada activa -->
            <template v-else>
                <p class="mb-2 text-lg font-black uppercase tracking-[0.3em]" :class="principal.estado === 'sentada' ? 'text-emerald-400' : 'text-amber-400'">
                    {{ principal.estado === 'sentada' ? 'SENTADA' : 'A CHAMAR' }}
                </p>

                <div
                    class="w-full rounded-3xl border-4 px-6 py-5 text-center"
                    :class="principal.estado === 'sentada'
                        ? 'border-emerald-400 bg-emerald-400/10 shadow-[0_0_60px_rgba(52,211,153,0.2)]'
                        : 'border-amber-400 bg-amber-400/10 shadow-[0_0_60px_rgba(251,191,36,0.2)]'"
                >
                    <p :style="{ fontSize: fontSizeNome }" class="font-black uppercase leading-tight tracking-tight text-white break-words">
                        {{ principal.nome }}
                    </p>
                    <div class="mt-3 flex items-center justify-center gap-5" :class="principal.estado === 'sentada' ? 'text-emerald-300' : 'text-amber-300'">
                        <span class="text-3xl font-black">{{ principal.pessoas }}</span><span class="text-xl font-black"> pessoas</span>
                    </div>

                    <!-- Mesa atribuída -->
                    <div v-if="principal.mesa_atribuida" class="mt-4 rounded-2xl border-2 border-emerald-400 bg-emerald-900/50 px-4 py-3 text-center">
                        <p class="text-sm font-black uppercase tracking-widest text-emerald-400">Dirija-se à</p>
                        <p class="mt-1 font-black text-white leading-none" style="font-size: clamp(2.5rem,10vh,7rem)">Mesa {{ principal.mesa_atribuida }}</p>
                    </div>

                    <div v-if="principal.pessoas > 10 && !principal.mesa_atribuida" class="mt-4 rounded-2xl border-2 border-red-400 bg-red-900/40 px-4 py-3 text-center">
                        <p class="text-xl font-black uppercase tracking-wide text-red-300">GRUPO GRANDE</p>
                        <p class="mt-1 text-sm font-bold text-red-400">Dirija-se à entrada principal para acompanhamento</p>
                    </div>
                </div>

                <!-- Em espera -->
                <div v-if="emEspera.length" class="mt-3 w-full">
                    <p class="mb-2 text-center text-xs font-black uppercase tracking-widest text-gray-500">Também em espera</p>
                    <div class="grid gap-2" :class="emEspera.length === 1 ? 'grid-cols-1 max-w-xs mx-auto' : 'grid-cols-2 sm:grid-cols-3'">
                        <div
                            v-for="r in emEspera"
                            :key="r.id"
                            class="rounded-xl border-2 px-3 py-2 text-center"
                            :class="r.mesa_atribuida ? 'border-emerald-700 bg-emerald-900/30' : 'border-gray-700 bg-gray-900'"
                        >
                            <p class="truncate text-lg font-black text-white">{{ r.nome }}</p>
                            <p class="text-sm font-bold text-gray-400"><span class="font-black text-white">{{ r.pessoas }}</span> pess.</p>
                            <p v-if="r.mesa_atribuida" class="text-base font-black text-emerald-400">Mesa {{ r.mesa_atribuida }}</p>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <!-- Rodape -->
        <footer class="ecra-footer">
            <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-center">
                <p class="text-lg font-black uppercase tracking-widest text-amber-400">Reserve aqui a sua mesa</p>
                <p class="text-xs font-bold text-amber-600 uppercase tracking-widest">Fale com o gestor de sala</p>
            </div>
        </footer>

    </main>
</template>

<style scoped>
.ecra-chamadas {
    height: 100dvh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #030712;
    color: #fff;
    padding: 1rem 1.25rem;
    gap: 0.5rem;
}

.ecra-header {
    flex-shrink: 0;
    width: 100%;
    max-width: 64rem;
    margin-inline: auto;
}

.ecra-body {
    flex: 1;
    min-height: 0;
    overflow: hidden;
    width: 100%;
    max-width: 64rem;
    margin-inline: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.ecra-footer {
    flex-shrink: 0;
    width: 100%;
    max-width: 64rem;
    margin-inline: auto;
}
</style>
