<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    chamadas: {
        type: Array,
        default: () => [],
    },
})

let refresh = null
let relogio = null
const agora = ref(new Date())
const horaAtual = computed(() => agora.value.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' }))

const principal = computed(() => props.chamadas[0] ?? null)
const emEspera  = computed(() => props.chamadas.slice(1))

const temEspera = computed(() => emEspera.value.length > 0)

const fontSizeNome = computed(() => {
    const len = (principal.value?.nome ?? '').length
    const compacto = temEspera.value || principal.value?.mesa_atribuida
    if (compacto) {
        if (len <= 6)  return 'clamp(3rem,13vh,9rem)'
        if (len <= 10) return 'clamp(2.5rem,12vh,8.125rem)'
        if (len <= 15) return 'clamp(2rem,9vh,6.5rem)'
        if (len <= 22) return 'clamp(1.5rem,6vh,4.5rem)'
        return 'clamp(1.25rem,4.5vh,3.5rem)'
    }
    if (len <= 6)  return 'clamp(4rem,14vh,11rem)'
    if (len <= 10) return 'clamp(3rem,10vh,8rem)'
    if (len <= 15) return 'clamp(2.5rem,8vh,6.5rem)'
    if (len <= 22) return 'clamp(2rem,6vh,5rem)'
    return 'clamp(1.5rem,5vh,4rem)'
})

onMounted(() => {
    refresh = setInterval(() => router.reload({ preserveScroll: true }), 5000)
    relogio = setInterval(() => { agora.value = new Date() }, 15000)
})

onBeforeUnmount(() => {
    clearInterval(refresh)
    clearInterval(relogio)
})
</script>

<template>
    <main class="flex h-dvh flex-col gap-4 overflow-hidden bg-escuro px-5 py-4 font-sans tabular-nums text-white md:gap-7 md:px-16 md:py-10">

        <!-- Cabeçalho -->
        <header class="flex shrink-0 items-center justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-extrabold uppercase tracking-[.06em] text-[#8FD3B5] md:text-[44px] md:leading-none">ARDC Santana</h1>
                <p class="text-sm font-semibold uppercase tracking-[.08em] text-escuro-inativo md:text-[26px] md:leading-tight">Sistema de chamadas</p>
            </div>
            <div class="text-3xl font-extrabold text-escuro-inativo md:text-[56px]">{{ horaAtual }}</div>
        </header>

        <!-- Zona principal -->
        <section class="grid min-h-0 flex-1 gap-6 md:gap-10" :class="temEspera ? 'lg:grid-cols-[minmax(0,1fr)_560px]' : ''">

            <!-- Ninguém chamado -->
            <div v-if="!principal" class="flex flex-col items-center justify-center gap-6 text-[#8FA39A]">
                <svg class="h-24 w-24 md:h-32 md:w-32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <p class="text-3xl font-extrabold uppercase tracking-[.1em] md:text-6xl">Aguardando chamadas...</p>
            </div>

            <!-- Chamada ativa -->
            <div v-else class="flex min-h-0 flex-col gap-3 md:gap-5">
                <div
                    class="flex items-center gap-4 text-xl font-extrabold uppercase tracking-[.14em] md:text-[40px]"
                    :class="principal.estado === 'sentada' ? 'text-[#8FD3B5]' : 'text-[#F6A15B]'"
                >
                    <span class="h-5 w-5 rounded-full md:h-7 md:w-7" :class="principal.estado === 'sentada' ? 'bg-verde-ok' : 'animate-pulse bg-laranja'"></span>
                    {{ principal.estado === 'sentada' ? 'SENTADA' : 'A CHAMAR' }}
                </div>

                <div
                    class="flex min-h-0 flex-1 flex-col items-center justify-center gap-4 rounded-[28px] border-[6px] p-6 text-center md:gap-5 md:border-8 md:p-8"
                    :class="principal.estado === 'sentada' ? 'border-verde bg-[#12301F]' : 'border-laranja bg-[#2B1E14]'"
                >
                    <p :style="{ fontSize: fontSizeNome }" class="break-words font-extrabold uppercase leading-[1.02] tracking-[-.01em] text-white">
                        {{ principal.nome }}
                    </p>
                    <p class="text-3xl font-bold md:text-[64px] md:leading-tight" :class="principal.estado === 'sentada' ? 'text-verde-claro2' : 'text-[#FFD9B8]'">
                        <span class="font-extrabold text-white">{{ principal.pessoas }}</span> pessoas
                    </p>

                    <!-- Mesa atribuída -->
                    <div v-if="principal.mesa_atribuida" class="mt-2 flex flex-col items-center gap-1 rounded-[22px] bg-verde px-10 py-4 md:px-16 md:py-5">
                        <span class="text-lg font-extrabold uppercase tracking-[.1em] text-verde-claro2 md:text-4xl">Dirija-se à</span>
                        <span class="font-extrabold leading-none text-white" style="font-size: clamp(2.5rem,12vh,8.125rem)">Mesa {{ principal.mesa_atribuida }}</span>
                    </div>

                    <div v-if="principal.pessoas > 10 && !principal.mesa_atribuida" class="mt-2 rounded-[22px] border-4 border-perigo bg-[#3A1612] px-8 py-4">
                        <p class="text-2xl font-extrabold uppercase tracking-[.06em] text-[#F4A79D] md:text-5xl">GRUPO GRANDE</p>
                        <p class="mt-1 text-lg font-semibold text-[#F4A79D] md:text-3xl">Dirija-se à entrada principal para acompanhamento</p>
                    </div>
                </div>
            </div>

            <!-- Em espera -->
            <aside v-if="principal && temEspera" aria-label="Também em espera" class="flex min-h-0 flex-col gap-3 overflow-hidden md:gap-4">
                <h2 class="text-xl font-extrabold uppercase tracking-[.1em] text-escuro-inativo md:text-[40px] md:leading-tight">Também em espera</h2>
                <div
                    v-for="r in emEspera"
                    :key="r.id"
                    class="flex flex-col gap-1.5 rounded-[18px] border-[3px] bg-[#233029] px-5 py-4 md:px-7 md:py-5"
                    :class="r.mesa_atribuida ? 'border-verde' : (r.pessoas > 10 ? 'border-perigo' : 'border-[#3A4842]')"
                >
                    <span class="truncate text-3xl font-extrabold leading-tight md:text-6xl">{{ r.nome }}</span>
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="text-xl font-semibold text-escuro-inativo md:text-[38px] md:leading-tight"><span class="font-extrabold text-white">{{ r.pessoas }}</span> pessoas</span>
                        <span v-if="r.mesa_atribuida" class="text-2xl font-extrabold text-[#8FD3B5] md:text-[44px]">Mesa {{ r.mesa_atribuida }}</span>
                    </div>
                    <template v-if="r.pessoas > 10 && !r.mesa_atribuida">
                        <span class="text-lg font-extrabold uppercase tracking-[.06em] text-[#F4A79D] md:text-3xl">Grupo grande</span>
                        <span class="text-base font-semibold text-[#F4A79D] md:text-[28px] md:leading-tight">Dirija-se à entrada principal</span>
                    </template>
                </div>
            </aside>
        </section>

        <!-- Rodapé -->
        <footer class="flex shrink-0 flex-wrap items-center justify-center gap-x-8 gap-y-1 rounded-[18px] border-[3px] border-laranja-texto bg-[#2B1E14] px-4 py-4 text-center md:min-h-[110px]">
            <span class="text-2xl font-extrabold uppercase tracking-[.08em] text-[#F6A15B] md:text-5xl">Reserve aqui a sua mesa</span>
            <span class="hidden text-4xl text-laranja-texto md:inline" aria-hidden="true">·</span>
            <span class="text-base font-bold uppercase tracking-[.06em] text-[#FFD9B8] md:text-4xl">Fale com o gestor de sala</span>
        </footer>

    </main>
</template>
