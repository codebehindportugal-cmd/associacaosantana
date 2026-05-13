<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    upcomingEvents: Array,
    pastEvents: Array,
});

const featured = computed(() => props.upcomingEvents?.[0] ?? null);
const upcoming = computed(() => props.upcomingEvents ?? []);
const archived = computed(() => props.pastEvents ?? []);
</script>

<template>
    <Head title="Associação de Santana" />

    <main class="min-h-screen bg-[#061536] text-white">
        <section class="relative overflow-hidden">
            <img
                v-if="featured"
                :src="featured.poster"
                :alt="featured.title"
                class="absolute inset-0 h-full w-full object-cover opacity-35"
            >
            <div class="absolute inset-0 bg-gradient-to-b from-[#061536]/70 via-[#061536]/80 to-[#061536]" />

            <nav class="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-5 py-5 lg:px-8">
                <Link href="/" class="flex items-center gap-3">
                    <img src="/images/events/santana-2026.svg" alt="ARDC Santana" class="h-12 w-10 rounded bg-white object-cover object-bottom">
                    <span class="text-sm font-black uppercase tracking-[0.24em] text-amber-300">ARDC Santana</span>
                </Link>
                <div class="flex items-center gap-2">
                    <Link :href="route('login')" class="rounded-md border border-white/25 px-4 py-2 text-sm font-bold text-white hover:bg-white hover:text-[#061536]">Área reservada</Link>
                    <Link :href="route('pos.login')" class="hidden rounded-md bg-amber-400 px-4 py-2 text-sm font-black text-[#061536] hover:bg-amber-300 sm:inline-flex">POS</Link>
                </div>
            </nav>

            <div class="relative z-10 mx-auto grid min-h-[calc(100vh-88px)] max-w-7xl items-center gap-10 px-5 pb-16 pt-8 lg:grid-cols-[1fr_420px] lg:px-8">
                <div class="max-w-4xl">
                    <p class="mb-5 inline-flex rounded-full bg-amber-400 px-4 py-2 text-sm font-black uppercase tracking-[0.18em] text-[#061536]">
                        Associação Recreativa Desportiva Cultural
                    </p>
                    <h1 class="max-w-5xl text-5xl font-black leading-none sm:text-7xl lg:text-8xl">
                        Santana
                    </h1>
                    <p class="mt-5 max-w-2xl text-xl font-semibold leading-relaxed text-blue-100">
                        Carvalhal Benfeito. Cultura, desporto, convívio e festas da comunidade.
                    </p>
                    <div v-if="featured" class="mt-8 flex flex-wrap gap-3">
                        <a href="#eventos" class="rounded-md bg-amber-400 px-5 py-3 font-black text-[#061536] hover:bg-amber-300">Ver próximos eventos</a>
                        <a href="#arquivo" class="rounded-md border border-white/30 px-5 py-3 font-black text-white hover:bg-white hover:text-[#061536]">Eventos antigos</a>
                    </div>
                </div>

                <a v-if="featured" href="#eventos" class="group block">
                    <img :src="featured.poster" :alt="featured.title" class="aspect-[3/4] w-full rounded-lg object-cover shadow-2xl ring-1 ring-white/20 transition group-hover:scale-[1.01]">
                    <div class="mt-4 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-black uppercase tracking-[0.2em] text-amber-300">{{ featured.badge }}</p>
                            <h2 class="text-2xl font-black">{{ featured.title }}</h2>
                        </div>
                        <span class="rounded bg-white px-3 py-2 text-sm font-black text-[#061536]">{{ featured.period }}</span>
                    </div>
                </a>
            </div>
        </section>

        <section id="eventos" class="bg-white py-16 text-slate-950">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="font-black uppercase tracking-[0.2em] text-blue-700">Agenda</p>
                        <h2 class="mt-2 text-4xl font-black">Próximos Eventos</h2>
                    </div>
                    <p class="max-w-xl font-semibold text-slate-600">Programação em destaque da associação para sócios, amigos e comunidade.</p>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <article v-for="evento in upcoming" :key="evento.title" class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50 shadow-sm">
                        <div class="grid gap-0 md:grid-cols-[260px_1fr]">
                            <img :src="evento.poster" :alt="evento.title" class="h-full min-h-80 w-full object-cover">
                            <div class="p-5 sm:p-6">
                                <div class="mb-4 flex flex-wrap items-center gap-2">
                                    <span class="rounded bg-blue-700 px-3 py-1 text-xs font-black uppercase tracking-[0.14em] text-white">{{ evento.badge }}</span>
                                    <span class="font-black text-amber-700">{{ evento.date }}</span>
                                </div>
                                <h3 class="text-3xl font-black">{{ evento.title }}</h3>
                                <p class="mt-1 font-bold text-slate-500">{{ evento.subtitle }} · {{ evento.location }}</p>
                                <p class="mt-4 leading-relaxed text-slate-700">{{ evento.description }}</p>

                                <div class="mt-5 space-y-3">
                                    <div v-for="linha in evento.schedule" :key="linha.day" class="rounded-md bg-white p-3 ring-1 ring-slate-200">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <strong class="text-blue-800">{{ linha.day }}</strong>
                                            <span class="text-sm font-bold uppercase text-slate-500">{{ linha.label }}</span>
                                        </div>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span v-for="item in linha.items" :key="item" class="rounded bg-amber-100 px-2 py-1 text-sm font-black text-amber-900">{{ item }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="arquivo" class="bg-[#07122C] py-16">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="mb-8">
                    <p class="font-black uppercase tracking-[0.2em] text-amber-300">Memória</p>
                    <h2 class="mt-2 text-4xl font-black">Eventos Antigos</h2>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <article v-for="evento in archived" :key="evento.title" class="rounded-lg border border-white/10 bg-white/5 p-5">
                        <p class="text-sm font-black uppercase tracking-[0.16em] text-amber-300">{{ evento.date }}</p>
                        <h3 class="mt-3 text-2xl font-black">{{ evento.title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-blue-100">{{ evento.description }}</p>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <span v-for="stat in evento.stats" :key="stat" class="rounded bg-white/10 px-3 py-1 text-sm font-bold text-white">{{ stat }}</span>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <footer class="border-t border-white/10 bg-[#040A19] py-8">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 text-sm font-semibold text-blue-100 lg:px-8">
                <span>Associação Recreativa Desportiva Cultural de Santana</span>
                <span>Carvalhal Benfeito</span>
            </div>
        </footer>
    </main>
</template>
