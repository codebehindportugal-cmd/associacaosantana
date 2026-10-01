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
    <section v-if="patrocinadores.length" class="py-14 font-sans">
        <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
            <p class="mb-8 text-center text-xs font-bold uppercase tracking-[0.14em] text-suave-2">Os nossos patrocinadores</p>
            <div class="swiper sponsors-swiper">
                <div class="swiper-wrapper items-center">
                    <div v-for="sponsor in patrocinadores" :key="sponsor.id || sponsor.empresa" class="swiper-slide flex justify-center">
                        <a :href="sponsor.website || '#'" target="_blank" rel="noopener noreferrer" :title="sponsor.empresa" class="flex h-28 w-full items-center justify-center rounded-[14px] border border-linha bg-white p-4 grayscale transition hover:border-verde hover:grayscale-0">
                            <img :src="sponsor.logo_url" :alt="sponsor.empresa" class="max-h-16 max-w-[80%] object-contain" loading="lazy">
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
