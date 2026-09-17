<script setup>
/*
 |--------------------------------------------------------------------------
 | DoorNav — entrar pela fachada do ARDC Santana
 |--------------------------------------------------------------------------
 | A foto real do edifício (luz de dia). Ao passar o rato, a porta/janela
 | destaca-se; ao clicar, ela abre e a câmara ENTRA por ela (zoom para
 | dentro) antes de navegar para a secção (via Inertia).
 |
 | Imagem: /images/edificio-entrada.jpg  (recorte focado no edifício)
 |
 | Uso:
 |   <DoorNav :auto="true" />  → ecrã de entrada (Home)
 |   <DoorNav />               → abre por um ícone no cabeçalho
 |
 | Editar destinos/posições no array `hotspots` (l/t/w/h em % da foto).
 */
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    facade: { type: String, default: '/images/edificio-entrada.jpg' },
    ratioW: { type: Number, default: 1600 },
    ratioH: { type: Number, default: 791 },
    auto: { type: Boolean, default: false },
});

function rt(name, fallback) {
    try {
        return typeof route === 'function' ? route(name) : fallback;
    } catch (e) {
        return fallback;
    }
}

const hotspots = [
    { key: 'eventos', label: 'Eventos', href: rt('inscricoes.index', '/inscricoes'), l: 20, t: 31.1, w: 13.5, h: 12.1, hinge: 'left' },
    { key: 'salao', label: 'Reservar Salão', href: rt('salao.pre-reserva', '/reserva-salao'), l: 13, t: 51.5, w: 11.5, h: 14.4, hinge: 'left' },
    { key: 'sobre', label: 'Sobre Nós', href: rt('pages.sobre-nos', '/sobre-nos'), l: 34, t: 37.9, w: 7, h: 33.3, hinge: 'left' },
    { key: 'restaurante', label: 'Restaurante & Bar', href: rt('precario', '/precario'), l: 43, t: 49.2, w: 26.5, h: 18.2, hinge: 'right' },
];

const secondary = [
    { label: 'Início', href: rt('home', '/') },
    { label: 'Patrocínios', href: rt('patrocinios.index', '/patrocinios') },
    { label: 'Contacto', href: rt('home', '/') + '#contactos' },
];

// A janela mostra a mesma foto, apenas a "fatia" correspondente.
function leafStyle(hs) {
    const size = `${(10000 / hs.w).toFixed(3)}% ${(10000 / hs.h).toFixed(3)}%`;
    const posX = hs.w >= 100 ? '50%' : `${(hs.l / (100 - hs.w) * 100).toFixed(3)}%`;
    const posY = hs.h >= 100 ? '50%' : `${(hs.t / (100 - hs.h) * 100).toFixed(3)}%`;
    return { backgroundImage: `url(${props.facade})`, backgroundSize: size, backgroundPosition: `${posX} ${posY}` };
}

const open = ref(false);
const arriving = ref(false);
const activeKey = ref(null);
const zooming = ref(false);
const fading = ref(false);
const busy = ref(false);
const reduce = ref(false);
const stageOrigin = ref('center');

function show() {
    open.value = true;
    document.body.style.overflow = 'hidden';
    if (!reduce.value) {
        arriving.value = true;
        window.setTimeout(() => (arriving.value = false), 1300);
    }
}
function hide() {
    if (busy.value) return;
    open.value = false;
    zooming.value = false;
    fading.value = false;
    activeKey.value = null;
    document.body.style.overflow = '';
}
function enter(hs) {
    if (busy.value) return;
    busy.value = true;
    activeKey.value = hs.key;
    stageOrigin.value = `${(hs.l + hs.w / 2).toFixed(2)}% ${(hs.t + hs.h / 2).toFixed(2)}%`;
    if (reduce.value) {
        window.setTimeout(() => router.visit(hs.href), 140);
        return;
    }
    window.setTimeout(() => (zooming.value = true), 360); // depois de a folha abrir
    window.setTimeout(() => (fading.value = true), 660);
    window.setTimeout(() => router.visit(hs.href), 1200);
}
function goSecondary(href) {
    if (busy.value) return;
    busy.value = true;
    window.setTimeout(() => router.visit(href), reduce.value ? 40 : 240);
}
function onKey(e) {
    if (e.key === 'Escape') hide();
}

onMounted(() => {
    reduce.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    window.addEventListener('keydown', onKey);
    if (props.auto) {
        let seen = false;
        try {
            seen = window.sessionStorage.getItem('santana_entrada') === '1';
        } catch (e) {
            seen = false;
        }
        if (!seen) {
            show();
            try {
                window.sessionStorage.setItem('santana_entrada', '1');
            } catch (e) {
                /* ignora */
            }
        }
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});

defineExpose({ show, hide });
</script>

<template>
    <button type="button" class="fc-trigger" aria-label="Abrir navegação" @click="show">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 21V6l7-3 7 3v15" />
            <path d="M17 21V9l4 2v10" />
            <path d="M8 21v-4h4v4" />
            <path d="M7 8h.01M11 8h.01M7 12h.01M11 12h.01" />
        </svg>
        <span class="fc-trigger-label">Menu</span>
    </button>

    <Teleport to="body">
        <Transition name="fc-fade">
            <div v-if="open" class="fc-scene" role="dialog" aria-modal="true" aria-label="Entrada pela fachada">
                <div class="fc-fill" :style="{ backgroundImage: `url(${props.facade})` }" aria-hidden="true"></div>

                <div class="fc-holder">
                    <div
                        class="fc-stage"
                        :class="{ 'is-arriving': arriving, 'is-zoom': zooming, 'is-fade': fading }"
                        :style="{ aspectRatio: `${props.ratioW} / ${props.ratioH}`, transformOrigin: stageOrigin }"
                    >
                        <img :src="props.facade" alt="Edifício da ARDC Santana" class="fc-base" />
                        <div class="fc-skyfade" aria-hidden="true"></div>

                        <button
                            v-for="hs in hotspots"
                            :key="hs.key"
                            type="button"
                            class="hs"
                            :class="[`hinge-${hs.hinge}`, { 'is-open': activeKey === hs.key, 'is-mute': busy && activeKey !== hs.key }]"
                            :style="{ left: hs.l + '%', top: hs.t + '%', width: hs.w + '%', height: hs.h + '%' }"
                            :aria-label="hs.label"
                            @click="enter(hs)"
                        >
                            <span class="hs-leaf" :style="leafStyle(hs)" aria-hidden="true"></span>
                            <span class="hs-ring" aria-hidden="true"></span>
                            <span class="hs-label">{{ hs.label }}</span>
                        </button>
                    </div>
                </div>

                <header class="fc-top">
                    <div class="fc-brand">
                        <img src="/images/santana-logo.png" alt="" class="fc-brand-logo" />
                        <span>ARDC Santana</span>
                    </div>
                    <button type="button" class="fc-close" aria-label="Fechar" @click="hide">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </header>

                <div class="fc-bottom" :class="{ 'is-faded': busy }">
                    <p class="fc-eyebrow">Associação de Santana</p>
                    <h2 class="fc-headline">Entre pela nossa casa</h2>
                    <p class="fc-sub">Clique numa porta ou janela para entrar</p>
                    <nav class="fc-secondary">
                        <button v-for="link in secondary" :key="link.label" type="button" @click="goSecondary(link.href)">{{ link.label }}</button>
                    </nav>
                </div>

                <div class="fc-white" :class="{ on: fading }" aria-hidden="true"></div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&display=swap');

/* ---------- Ícone ---------- */
.fc-trigger {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem 0.5rem 0.8rem;
    border-radius: 999px;
    border: 1px solid rgba(180, 120, 40, 0.35);
    background: linear-gradient(135deg, #f6e6bf, #e8c47a);
    color: #5a3d12;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(120, 80, 20, 0.18), inset 0 1px 0 rgba(255, 255, 255, 0.6);
    transition: transform 0.25s ease, box-shadow 0.25s ease, filter 0.25s ease;
}
.fc-trigger:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 26px rgba(120, 80, 20, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.7);
    filter: brightness(1.03);
}

/* ---------- Cena ---------- */
.fc-scene {
    position: fixed;
    inset: 0;
    z-index: 95;
    overflow: hidden;
    background: #0a0a0c;
    isolation: isolate;
}
.fc-fill {
    position: absolute;
    inset: 0;
    background-position: center;
    background-size: cover;
    filter: brightness(0.5) saturate(0.9) blur(8px);
    transform: scale(1.1);
    z-index: 0;
}
.fc-holder {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
    overflow: hidden;
}
.fc-stage {
    position: relative;
    height: 100%;
    min-width: 100%;
    transition: transform 1s cubic-bezier(0.6, 0.02, 0.3, 1), opacity 0.7s ease 0.2s;
    will-change: transform;
}
.fc-stage.is-arriving {
    animation: fc-arrive 1.3s cubic-bezier(0.2, 0.7, 0.2, 1) both;
}
.fc-stage.is-zoom {
    transform: scale(7);
}
.fc-stage.is-fade {
    opacity: 0;
}
.fc-base {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.fc-skyfade {
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
    background: linear-gradient(180deg, rgba(10, 12, 20, 0.28), transparent 20%, transparent 60%, rgba(8, 8, 12, 0.6));
}

/* ---------- Hotspots ---------- */
.hs {
    position: absolute;
    z-index: 4;
    border: 0;
    background: transparent;
    padding: 0;
    cursor: pointer;
    transform-style: preserve-3d;
}
.hs-leaf {
    position: absolute;
    inset: 0;
    background-repeat: no-repeat;
    border-radius: 2px;
    filter: brightness(1.04);
    transform-origin: left center;
    backface-visibility: hidden;
    opacity: 0;
    transition: opacity 0.2s ease, transform 0.55s cubic-bezier(0.5, 0.02, 0.3, 1);
    pointer-events: none;
}
.hinge-right .hs-leaf {
    transform-origin: right center;
}
.hs-ring {
    position: absolute;
    inset: 0;
    border-radius: 3px;
    outline: 2px solid rgba(255, 246, 222, 0);
    outline-offset: 2px;
    box-shadow: 0 14px 40px rgba(0, 0, 0, 0);
    transition: outline-color 0.3s ease, box-shadow 0.3s ease, background 0.3s ease;
    pointer-events: none;
}
.hs-label {
    position: absolute;
    left: 50%;
    bottom: -1.7rem;
    transform: translateX(-50%);
    white-space: nowrap;
    color: #fff;
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    opacity: 0;
    transition: opacity 0.3s ease;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.95);
    pointer-events: none;
}
.hs:hover .hs-ring,
.hs:focus-visible .hs-ring {
    outline-color: rgba(255, 246, 222, 0.95);
    box-shadow: 0 14px 44px rgba(0, 0, 0, 0.45);
    background: rgba(255, 240, 205, 0.06);
}
.hs:hover .hs-label,
.hs:focus-visible .hs-label {
    opacity: 1;
}
.hs.is-open {
    z-index: 6;
}
.hs.is-open .hs-leaf {
    opacity: 1;
    transform: rotateY(-95deg);
}
.hinge-right.is-open .hs-leaf {
    transform: rotateY(95deg);
}
.hs.is-open .hs-ring,
.hs.is-open .hs-label {
    opacity: 0;
    outline-color: transparent;
}
.hs.is-mute {
    opacity: 0;
    transition: opacity 0.4s ease;
}

/* ---------- Topo ---------- */
.fc-top {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 12;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.1rem 1.4rem;
}
.fc-brand {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: #fff;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-weight: 600;
    font-size: 1.4rem;
    letter-spacing: 0.02em;
    text-shadow: 0 2px 14px rgba(0, 0, 0, 0.8);
}
.fc-brand-logo {
    width: 2.3rem;
    height: 2.3rem;
    border-radius: 999px;
    object-fit: contain;
    background: rgba(255, 255, 255, 0.92);
    padding: 0.15rem;
    border: 1px solid rgba(240, 210, 140, 0.5);
}
.fc-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.6rem;
    height: 2.6rem;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.4);
    background: rgba(0, 0, 0, 0.32);
    color: #fff;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease;
}
.fc-close:hover {
    background: rgba(0, 0, 0, 0.55);
    transform: rotate(90deg);
}

/* ---------- Rodapé ---------- */
.fc-bottom {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 12;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.32rem;
    padding: 1rem 1rem 1.4rem;
    text-align: center;
    transition: opacity 0.5s ease, transform 0.5s ease;
}
.fc-bottom.is-faded {
    opacity: 0;
    transform: translateY(10px);
}
.fc-eyebrow {
    margin: 0;
    color: #ffe1a8;
    font-size: 0.66rem;
    letter-spacing: 0.34em;
    text-transform: uppercase;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.9);
}
.fc-headline {
    margin: 0.1rem 0 0;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-weight: 500;
    font-style: italic;
    font-size: clamp(1.5rem, 3.4vw, 2.5rem);
    line-height: 1.05;
    color: #fff;
    text-shadow: 0 3px 20px rgba(0, 0, 0, 0.9);
}
.fc-sub {
    margin: 0.25rem 0 0.55rem;
    color: #eadfca;
    font-size: 0.72rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.9);
}
.fc-secondary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem 1.2rem;
    justify-content: center;
}
.fc-secondary button {
    background: none;
    border: none;
    color: #f0e6d2;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    text-shadow: 0 1px 6px rgba(0, 0, 0, 0.8);
    transition: color 0.2s ease;
}
.fc-secondary button:hover {
    color: #fff;
}

/* ---------- Entrada branca (transição para a página) ---------- */
.fc-white {
    position: absolute;
    inset: 0;
    z-index: 20;
    background: #ffffff;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.5s ease 0.35s;
}
.fc-white.on {
    opacity: 1;
}

/* ---------- Transições ---------- */
.fc-fade-enter-active,
.fc-fade-leave-active {
    transition: opacity 0.5s ease;
}
.fc-fade-enter-from,
.fc-fade-leave-to {
    opacity: 0;
}
@keyframes fc-arrive {
    from { transform: scale(1.06); }
    to { transform: scale(1); }
}

/* ---------- Telemóvel ---------- */
@media (max-width: 760px) {
    .hs-label {
        font-size: 0.66rem;
        letter-spacing: 0.08em;
    }
}

/* ---------- Menos movimento ---------- */
@media (prefers-reduced-motion: reduce) {
    .fc-stage {
        animation: none !important;
        transition: opacity 0.2s ease !important;
    }
    .fc-stage.is-zoom {
        transform: none;
    }
    .hs-leaf,
    .fc-white {
        transition-duration: 0.2s !important;
    }
}
</style>
