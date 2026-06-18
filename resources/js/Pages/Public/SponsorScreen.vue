<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    patrocinadores: {
        type: Array,
        default: () => [],
    },
});

const slideAtual = ref(0);
let timer;

const logos = computed(() => props.patrocinadores.filter((sponsor) => sponsor.logo_url));
const porSlide = computed(() => (logos.value.length <= 6 ? Math.max(logos.value.length, 1) : 6));
const slides = computed(() => {
    if (!logos.value.length) {
        return [];
    }

    const grupos = [];
    for (let i = 0; i < logos.value.length; i += porSlide.value) {
        grupos.push(logos.value.slice(i, i + porSlide.value));
    }

    return grupos;
});
const slide = computed(() => slides.value[slideAtual.value] ?? []);

const avancar = () => {
    if (slides.value.length <= 1) {
        return;
    }

    slideAtual.value = (slideAtual.value + 1) % slides.value.length;
};

onMounted(() => {
    timer = window.setInterval(avancar, 7000);
});

onBeforeUnmount(() => {
    window.clearInterval(timer);
});
</script>

<template>
    <Head title="Ecrã de Patrocinadores" />

    <main class="sponsor-screen min-h-screen overflow-hidden bg-slate-950 text-white">
        <div class="absolute inset-0 sponsor-screen__texture"></div>

        <section class="relative flex min-h-screen flex-col px-8 py-8 sm:px-12 lg:px-16">
            <header class="flex shrink-0 items-center justify-between gap-6">
                <div>
                    <div class="text-sm font-black uppercase tracking-[0.28em] text-emerald-300">ARDC Santana</div>
                    <h1 class="mt-2 text-3xl font-black sm:text-5xl">Patrocinadores</h1>
                </div>
                <div class="hidden rounded-full border border-white/15 px-5 py-3 text-right text-sm font-black uppercase tracking-wide text-white/70 sm:block">
                    Obrigado pelo apoio
                </div>
            </header>

            <div v-if="!logos.length" class="grid flex-1 place-items-center text-center">
                <div>
                    <h2 class="text-4xl font-black">Ainda não há logos ativos</h2>
                    <p class="mt-3 text-lg font-bold text-white/60">Adicione patrocinadores ativos no backoffice.</p>
                </div>
            </div>

            <div v-else class="flex flex-1 items-center py-8">
                <Transition name="sponsor-fade" mode="out-in">
                    <div :key="slideAtual" class="grid w-full gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="sponsor in slide"
                            :key="sponsor.id"
                            class="sponsor-logo-tile grid aspect-[16/9] place-items-center rounded-lg border border-white/10 bg-white p-8 shadow-2xl shadow-black/30"
                        >
                            <img :src="sponsor.logo_url" :alt="sponsor.empresa" class="max-h-full max-w-full object-contain">
                        </article>
                    </div>
                </Transition>
            </div>

            <footer v-if="logos.length" class="relative shrink-0">
                <div class="h-1 overflow-hidden rounded-full bg-white/10">
                    <div class="sponsor-screen__progress h-full rounded-full bg-emerald-400" :key="slideAtual"></div>
                </div>
                <div class="mt-4 flex items-center justify-between text-xs font-black uppercase tracking-wide text-white/55">
                    <span>{{ logos.length }} patrocinadores</span>
                    <span>{{ slideAtual + 1 }} / {{ slides.length }}</span>
                </div>
            </footer>
        </section>
    </main>
</template>

<style scoped>
.sponsor-screen {
    position: relative;
}

.sponsor-screen__texture {
    background:
        radial-gradient(circle at 15% 10%, rgba(16, 185, 129, 0.18), transparent 30%),
        radial-gradient(circle at 85% 85%, rgba(59, 130, 246, 0.16), transparent 30%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0 1px, transparent 1px 18px);
    opacity: 0.9;
}

.sponsor-logo-tile {
    animation: tile-in 700ms ease both;
}

.sponsor-screen__progress {
    animation: progress 7s linear both;
    transform-origin: left;
}

.sponsor-fade-enter-active,
.sponsor-fade-leave-active {
    transition: opacity 450ms ease, transform 450ms ease;
}

.sponsor-fade-enter-from,
.sponsor-fade-leave-to {
    opacity: 0;
    transform: translateY(12px);
}

@keyframes tile-in {
    from {
        opacity: 0;
        transform: scale(0.96);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes progress {
    from {
        transform: scaleX(0);
    }

    to {
        transform: scaleX(1);
    }
}
</style>
