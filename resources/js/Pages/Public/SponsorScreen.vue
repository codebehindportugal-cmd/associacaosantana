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

    <main class="sponsor-screen">
        <div class="sponsor-screen__bg" aria-hidden="true"></div>

        <!-- Estado vazio -->
        <div v-if="!sequencia.length" class="sponsor-screen__empty">
            <div>
                <h2 class="text-4xl font-black">Ainda não há patrocinadores ativos</h2>
                <p class="mt-3 text-lg font-bold text-white/55">Adicione patrocinadores no backoffice.</p>
            </div>
        </div>

        <!-- Slider -->
        <template v-else>
            <!-- Marca topo-esquerdo -->
            <div class="sponsor-screen__brand">
                <span class="brand-label">ARDC Santana</span>
                <span class="brand-sep">·</span>
                <span class="brand-title">Patrocinadores</span>
            </div>

            <!-- Imagem full-screen com fade -->
            <Transition name="img-fade" mode="out-in">
                <div :key="indice" class="sponsor-screen__slide">
                    <img
                        :src="atual.url"
                        :alt="atual.empresa"
                        class="sponsor-screen__img"
                    >
                </div>
            </Transition>

            <!-- Rodapé: nome do patrocinador + contador + barra de progresso -->
            <footer class="sponsor-screen__footer">
                <div class="footer-inner">
                    <div class="footer-sponsor">
                        <img
                            :src="atual.logo_url"
                            :alt="atual.empresa"
                            class="footer-logo"
                        >
                        <span class="footer-name">{{ atual.empresa }}</span>
                    </div>
                </div>

                <div class="footer-progress-track">
                    <div :key="indice" class="footer-progress-bar"></div>
                </div>
            </footer>
        </template>
    </main>
</template>

<style scoped>
.sponsor-screen {
    position: relative;
    min-height: 100vh;
    background: #020617;
    color: #fff;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.sponsor-screen__bg {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse at 20% 15%, rgba(16, 185, 129, 0.14) 0%, transparent 40%),
        radial-gradient(ellipse at 80% 80%, rgba(59, 130, 246, 0.12) 0%, transparent 40%);
    pointer-events: none;
}

/* Marca */
.sponsor-screen__brand {
    position: absolute;
    top: 2rem;
    left: 2.5rem;
    z-index: 20;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    font-weight: 900;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.5);
}

.brand-label {
    color: #6ee7b7;
}

.brand-sep {
    opacity: 0.4;
}

/* Imagem */
.sponsor-screen__slide {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 5rem 4rem 7rem;
    z-index: 10;
}

.sponsor-screen__img {
    max-width: 82vw;
    max-height: 78vh;
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 0.75rem;
    filter: drop-shadow(0 8px 40px rgba(0, 0, 0, 0.6));
}

/* Estado vazio */
.sponsor-screen__empty {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    text-align: center;
    padding: 2rem;
    z-index: 10;
}

/* Rodapé */
.sponsor-screen__footer {
    position: absolute;
    bottom: 0;
    inset-x: 0;
    z-index: 20;
    padding: 0 2.5rem 0;
    background: linear-gradient(to top, rgba(2, 6, 23, 0.9) 0%, transparent 100%);
    padding-top: 3rem;
}

.footer-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding-bottom: 0.75rem;
}

.footer-sponsor {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
}

.footer-logo {
    height: 2.25rem;
    width: auto;
    max-width: 6rem;
    object-fit: contain;
    background: white;
    border-radius: 0.375rem;
    padding: 0.2rem 0.4rem;
    flex-shrink: 0;
}

.footer-name {
    font-size: 1.25rem;
    font-weight: 900;
    color: #fff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}


.footer-progress-track {
    height: 3px;
    background: rgba(255, 255, 255, 0.12);
    overflow: hidden;
}

.footer-progress-bar {
    height: 100%;
    background: #34d399;
    animation: progress 6s linear both;
    transform-origin: left;
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
</style>
