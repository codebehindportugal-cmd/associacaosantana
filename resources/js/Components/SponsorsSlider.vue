<script setup>
import { onMounted } from 'vue';

const props = defineProps({
    patrocinadores: {
        type: Array,
        default: () => [],
    },
});

onMounted(() => {
    if (!props.patrocinadores.length || window.Swiper) {
        window.Swiper && new window.Swiper('.sponsors-swiper', {
            loop: props.patrocinadores.length > 4,
            autoplay: { delay: 3000, disableOnInteraction: false },
            slidesPerView: 2,
            spaceBetween: 24,
            breakpoints: {
                640: { slidesPerView: 3 },
                1024: { slidesPerView: 5 },
            },
        });
        return;
    }

    const css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css';
    document.head.appendChild(css);

    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js';
    script.onload = () => new window.Swiper('.sponsors-swiper', {
        loop: props.patrocinadores.length > 4,
        autoplay: { delay: 3000, disableOnInteraction: false },
        slidesPerView: 2,
        spaceBetween: 24,
        breakpoints: {
            640: { slidesPerView: 3 },
            1024: { slidesPerView: 5 },
        },
    });
    document.body.appendChild(script);
});
</script>

<template>
    <section v-if="patrocinadores.length" class="border-t border-amber-200 bg-amber-50 py-14">
        <div class="mx-auto max-w-6xl px-5 lg:px-8">
            <p class="mb-8 text-center text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Os nossos patrocinadores</p>
            <div class="swiper sponsors-swiper">
                <div class="swiper-wrapper items-center">
                    <div v-for="sponsor in patrocinadores" :key="sponsor.id || sponsor.empresa" class="swiper-slide flex justify-center">
                        <a :href="sponsor.website || '#'" target="_blank" rel="noopener noreferrer" :title="sponsor.empresa" class="block rounded-lg border border-amber-200 bg-white p-4 grayscale shadow-sm transition hover:grayscale-0 hover:border-amber-400 hover:shadow-md">
                            <img :src="sponsor.logo_url" :alt="sponsor.empresa" class="max-h-20 max-w-[200px] object-contain" loading="lazy">
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
