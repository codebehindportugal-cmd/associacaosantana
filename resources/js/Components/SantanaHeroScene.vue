<script setup>
const props = defineProps({
    compact: {
        type: Boolean,
        default: false,
    },
});

const associationLogo = '/images/santana-logo.png';
const particles = Array.from({ length: 18 }, (_, index) => index);
</script>

<template>
    <div class="logo-3d-scene" :class="{ 'is-compact': props.compact }" aria-hidden="true">
        <div class="logo-3d-field">
            <span
                v-for="particle in particles"
                :key="particle"
                class="logo-3d-particle"
                :style="{
                    '--i': particle,
                    '--delay': `${particle * -0.28}s`,
                    '--distance': `${64 + (particle % 6) * 18}px`,
                }"
            />
        </div>

        <div class="logo-3d-orbit logo-3d-orbit-a" />
        <div class="logo-3d-orbit logo-3d-orbit-b" />
        <div class="logo-3d-orbit logo-3d-orbit-c" />

        <div class="logo-3d-stage">
            <div class="logo-3d-shadow" />
            <div class="logo-3d-card">
                <img :src="associationLogo" alt="" class="logo-3d-img logo-3d-img-back">
                <img :src="associationLogo" alt="" class="logo-3d-img logo-3d-img-mid">
                <img :src="associationLogo" alt="" class="logo-3d-img logo-3d-img-front">
            </div>
        </div>
    </div>
</template>

<style scoped>
.logo-3d-scene {
    display: grid;
    inset: 0;
    overflow: hidden;
    perspective: 1100px;
    place-items: center;
    position: absolute;
}
.logo-3d-field,
.logo-3d-orbit,
.logo-3d-stage {
    position: absolute;
}
.logo-3d-stage {
    height: min(52vw, 37rem);
    max-height: 72vh;
    max-width: 72vh;
    min-height: 20rem;
    min-width: 20rem;
    transform: translateX(18vw) rotateX(58deg) rotateZ(-12deg);
    transform-style: preserve-3d;
    width: min(52vw, 37rem);
}
.logo-3d-card {
    animation: logo-float 7s ease-in-out infinite;
    border-radius: 999px;
    height: 100%;
    position: relative;
    transform-style: preserve-3d;
    width: 100%;
}
.logo-3d-card::before {
    background:
        radial-gradient(circle at 35% 28%, rgba(255, 255, 255, 0.28), transparent 24%),
        linear-gradient(135deg, rgba(240, 208, 96, 0.2), rgba(79, 216, 255, 0.1));
    border: 1px solid rgba(240, 208, 96, 0.24);
    border-radius: inherit;
    box-shadow:
        inset 0 0 34px rgba(255, 255, 255, 0.12),
        0 22px 80px rgba(0, 0, 0, 0.38),
        0 0 76px rgba(79, 216, 255, 0.18),
        0 0 80px rgba(212, 175, 55, 0.14);
    content: '';
    inset: -7%;
    position: absolute;
    transform: translateZ(-28px);
}
.logo-3d-img {
    border-radius: 999px;
    filter: drop-shadow(0 18px 38px rgba(0, 0, 0, 0.36));
    height: 100%;
    inset: 0;
    object-fit: contain;
    padding: 5%;
    position: absolute;
    width: 100%;
}
.logo-3d-img-back {
    opacity: 0.16;
    transform: translateZ(-58px) scale(1.08);
}
.logo-3d-img-mid {
    opacity: 0.42;
    transform: translateZ(-26px) scale(1.035);
}
.logo-3d-img-front {
    transform: translateZ(34px);
}
.logo-3d-shadow {
    background: radial-gradient(ellipse, rgba(0, 0, 0, 0.44), transparent 68%);
    bottom: -12%;
    filter: blur(8px);
    height: 24%;
    left: 8%;
    position: absolute;
    transform: translateZ(-90px);
    width: 84%;
}
.logo-3d-orbit {
    border: 1px solid rgba(240, 208, 96, 0.2);
    border-radius: 999px;
    box-shadow: 0 0 38px rgba(79, 216, 255, 0.1);
    height: min(58vw, 39rem);
    transform-style: preserve-3d;
    width: min(58vw, 39rem);
}
.logo-3d-orbit-a {
    animation: orbit-spin-a 12s linear infinite;
    border-color: rgba(240, 208, 96, 0.26);
    transform: translateX(18vw) rotateX(72deg) rotateZ(18deg);
}
.logo-3d-orbit-b {
    animation: orbit-spin-b 15s linear infinite reverse;
    border-color: rgba(79, 216, 255, 0.2);
    transform: translateX(18vw) rotateY(64deg) rotateZ(-12deg);
}
.logo-3d-orbit-c {
    animation: orbit-spin-c 18s linear infinite;
    border-color: rgba(245, 240, 232, 0.12);
    height: min(44vw, 30rem);
    transform: translateX(18vw) rotateX(54deg) rotateY(-36deg);
    width: min(44vw, 30rem);
}
.logo-3d-field {
    height: 44rem;
    transform: translateX(18vw) rotateX(58deg);
    transform-style: preserve-3d;
    width: 44rem;
}
.logo-3d-particle {
    --angle: calc(var(--i) * 20deg);
    animation: particle-drift 7s ease-in-out infinite;
    animation-delay: var(--delay);
    background: linear-gradient(135deg, #F0D060, #4fd8ff);
    border-radius: 999px;
    box-shadow: 0 0 18px rgba(79, 216, 255, 0.42);
    height: 0.38rem;
    left: 50%;
    opacity: 0.78;
    position: absolute;
    top: 50%;
    transform: rotate(var(--angle)) translateX(var(--distance)) translateZ(calc((var(--i) % 5) * 18px));
    width: 0.38rem;
}
.logo-3d-scene.is-compact .logo-3d-stage,
.logo-3d-scene.is-compact .logo-3d-orbit,
.logo-3d-scene.is-compact .logo-3d-field {
    transform: translateX(0) rotateX(58deg) rotateZ(-12deg) scale(0.74);
}
.logo-3d-scene.is-compact .logo-3d-orbit-a {
    animation-name: orbit-spin-compact-a;
}
.logo-3d-scene.is-compact .logo-3d-orbit-b {
    animation-name: orbit-spin-compact-b;
}
.logo-3d-scene.is-compact .logo-3d-orbit-c {
    animation-name: orbit-spin-compact-c;
}

@keyframes logo-float {
    0%, 100% {
        transform: rotateZ(0deg) translateZ(0);
    }
    50% {
        transform: rotateZ(3deg) translateZ(26px);
    }
}
@keyframes orbit-spin-a {
    to { transform: translateX(18vw) rotateX(72deg) rotateZ(378deg); }
}
@keyframes orbit-spin-b {
    to { transform: translateX(18vw) rotateY(64deg) rotateZ(348deg); }
}
@keyframes orbit-spin-c {
    to { transform: translateX(18vw) rotateX(54deg) rotateY(-36deg) rotateZ(360deg); }
}
@keyframes orbit-spin-compact-a {
    to { transform: translateX(0) rotateX(72deg) rotateZ(378deg) scale(0.74); }
}
@keyframes orbit-spin-compact-b {
    to { transform: translateX(0) rotateY(64deg) rotateZ(348deg) scale(0.74); }
}
@keyframes orbit-spin-compact-c {
    to { transform: translateX(0) rotateX(54deg) rotateY(-36deg) rotateZ(360deg) scale(0.74); }
}
@keyframes particle-drift {
    0%, 100% {
        opacity: 0.38;
        translate: 0 0;
    }
    50% {
        opacity: 0.9;
        translate: 0 -22px;
    }
}

@media (max-width: 760px) {
    .logo-3d-stage,
    .logo-3d-orbit,
    .logo-3d-field {
        transform: translateY(-10vh) rotateX(58deg) rotateZ(-12deg) scale(0.72);
    }
    .logo-3d-orbit-a,
    .logo-3d-orbit-b,
    .logo-3d-orbit-c {
        transform: translateY(-10vh) rotateX(58deg) rotateZ(-12deg) scale(0.72);
    }
}

@media (prefers-reduced-motion: reduce) {
    .logo-3d-card,
    .logo-3d-orbit,
    .logo-3d-particle {
        animation-duration: 1ms;
        animation-iteration-count: 1;
    }
}
</style>
