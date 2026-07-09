<script setup>
import { Link } from '@inertiajs/vue3';

const logo = '/images/santana-logo.png';
</script>

<template>
    <div class="guest-wrapper">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="orb orb-1"></div>
            <div class="orb orb-2"></div>
        </div>

        <div class="relative z-10 flex min-h-screen flex-col items-center justify-center px-4 py-12">
            <div class="mb-8 text-center">
                <Link :href="route('home')" class="guest-logo-link inline-block">
                    <img :src="logo" alt="ARDC Santana" class="guest-logo mx-auto h-20 w-auto" />
                </Link>
                <p class="mt-3 text-sm font-semibold" style="color:#C9A84C">Associação de Santana</p>
            </div>

            <div class="guest-card w-full max-w-md rounded-2xl p-8 shadow-2xl">
                <div class="guest-card-accent"></div>
                <slot />
            </div>

            <div class="mt-8 flex flex-wrap justify-center gap-4 text-xs" style="color:rgba(255,253,248,0.3)">
                <Link :href="route('legal.privacidade')" class="guest-footer-link transition">Privacidade</Link>
                <Link :href="route('legal.termos')" class="guest-footer-link transition">Termos</Link>
                <Link :href="route('legal.cookies')" class="guest-footer-link transition">Cookies</Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.guest-wrapper {
    position: relative;
    min-height: 100vh;
    background: #0d0a05;
    color: #fffdf8;
    overflow: hidden;
}
.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    pointer-events: none;
}
.orb-1 {
    width: 500px;
    height: 500px;
    top: -150px;
    right: -100px;
    background: radial-gradient(circle, rgba(212,175,55,0.12), transparent 70%);
}
.orb-2 {
    width: 400px;
    height: 400px;
    bottom: -100px;
    left: -100px;
    background: radial-gradient(circle, rgba(201,168,76,0.08), transparent 70%);
}
.guest-logo {
    filter: drop-shadow(0 0 20px rgba(212,175,55,0.3));
    transition: transform 300ms;
}
.guest-logo-link:hover .guest-logo {
    transform: scale(1.05);
}
.guest-card {
    position: relative;
    background: rgba(17, 13, 3, 0.95);
    border: 1px solid rgba(212, 175, 55, 0.18);
    overflow: hidden;
}
.guest-card-accent {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, #D4AF37, transparent);
}
.guest-footer-link {
    color: rgba(255, 253, 248, 0.3);
}
.guest-footer-link:hover {
    color: #C9A84C;
}
</style>
