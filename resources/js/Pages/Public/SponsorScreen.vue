<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const DURACAO = 6000;

const props = defineProps({
    patrocinadores: {
        type: Array,
        default: () => [],
    },
});

const indice = ref(0);
let timer;

// Lista plana: para cada patrocinador, usa as suas imagens ou, se não tiver, o logótipo
const sequencia = computed(() => {
    const items = [];
    for (const s of props.patrocinadores) {
        const imgs = s.images?.length
            ? s.images
            : [{ id: `logo-${s.id}`, url: s.logo_url }];
        for (const img of imgs) {
            items.push({
                key: String(img.id),
                url: img.url,
                empresa: s.empresa,
                logo_url: s.logo_url,
            });
        }
    }
    return items;
});

const atual = computed(() => sequencia.value[indice.value] ?? null);

const avancar = () => {
    if (sequencia.value.length <= 1) return;
    indice.value = (indice.value + 1) % sequencia.value.length;
};

onMounted(() => {
    timer = window.setInterval(avancar, DURACAO);
});

onBeforeUnmount(() => {
    window.clearInterval(timer);
});
</script>

<template>
    <Head title="Ecrã de Patrocinadores" />

    <main class="relative flex h-dvh min-h-screen flex-col overflow-hidden bg-escuro font-sans text-white">
        <!-- Estado vazio -->
        <div v-if="!sequencia.length" class="grid flex-1 place-items-center p-8 text-center">
            <div>
                <h2 class="text-4xl font-extrabold md:text-6xl">Ainda não há patrocinadores ativos</h2>
                <p class="mt-4 text-lg font-bold text-escuro-inativo md:text-3xl">Adicione patrocinadores no backoffice.</p>
            </div>
        </div>

        <!-- Slider -->
        <template v-else>
            <!-- Marca + indicador de diapositivos -->
            <header class="flex shrink-0 items-center justify-between gap-6 px-8 py-6 md:h-[120px] md:px-[72px] md:py-0">
                <div class="flex items-center gap-3 text-lg font-extrabold uppercase tracking-[.06em] md:gap-5 md:text-[34px]">
                    <span class="text-[#8FD3B5]">ARDC Santana</span>
                    <span class="text-suave-2">·</span>
                    <span class="text-escuro-inativo">Patrocinadores</span>
                </div>
                <div
                    v-if="sequencia.length > 1 && sequencia.length <= 24"
                    class="flex items-center gap-2 md:gap-3"
                    :aria-label="`Diapositivo ${indice + 1} de ${sequencia.length}`"
                >
                    <span
                        v-for="(item, i) in sequencia"
                        :key="item.key"
                        class="h-3 rounded-full transition-all duration-500 md:h-[18px]"
                        :class="i === indice ? 'w-8 bg-verde-ok md:w-12' : 'w-3 bg-suave md:w-[18px]'"
                    ></span>
                </div>
            </header>

            <!-- Imagem com fade -->
            <section class="relative flex min-h-0 flex-1 items-center justify-center px-8 md:px-[120px]">
                <Transition name="img-fade" mode="out-in">
                    <img
                        :key="indice"
                        :src="atual.url"
                        :alt="atual.empresa"
                        class="h-auto max-h-full w-auto max-w-full rounded-[22px] object-contain shadow-[0_20px_80px_rgba(0,0,0,.45)]"
                    >
                </Transition>
            </section>

            <!-- Rodapé: logótipo + nome do patrocinador + barra de progresso -->
            <footer class="flex shrink-0 flex-col">
                <div class="flex items-center gap-6 px-8 py-5 md:h-[156px] md:gap-9 md:px-[72px] md:py-0">
                    <div class="flex h-16 w-32 shrink-0 items-center justify-center rounded-[14px] bg-white p-2 md:h-[110px] md:w-[220px] md:p-3">
                        <img :src="atual.logo_url" :alt="atual.empresa" class="max-h-full max-w-full object-contain">
                    </div>
                    <span class="min-w-0 truncate text-4xl font-extrabold leading-none md:text-[76px]">{{ atual.empresa }}</span>
                </div>
                <div class="h-1.5 overflow-hidden bg-escuro-2 md:h-2.5">
                    <div :key="indice" class="progress-bar h-full origin-left bg-verde-ok"></div>
                </div>
            </footer>
        </template>
    </main>
</template>

<style scoped>
.progress-bar {
    animation: progress 6s linear both;
}

/* Transições */
.img-fade-enter-active,
.img-fade-leave-active {
    transition: opacity 500ms ease, transform 500ms ease;
}

.img-fade-enter-from,
.img-fade-leave-to {
    opacity: 0;
    transform: scale(1.03);
}

@keyframes progress {
    from { transform: scaleX(0); }
    to   { transform: scaleX(1); }
}

@media (prefers-reduced-motion: reduce) {
    .img-fade-enter-active,
    .img-fade-leave-active {
        transition: opacity 300ms ease;
    }

    .img-fade-enter-from,
    .img-fade-leave-to {
        transform: none;
    }
}
</style>
