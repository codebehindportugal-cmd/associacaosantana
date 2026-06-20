<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CookieBanner from '@/Components/CookieBanner.vue';
import SponsorsSlider from '@/Components/SponsorsSlider.vue';

const props = defineProps({
    upcomingEvents: Array,
    pastEvents: Array,
    patrocinadores: Array,
});

const associationLogo = '/images/santana-logo.png';
const santaAnaImage = '/images/santa-ana.png';
const contactEmail = 'ardcsantana@outlook.com';
const currentYear = new Date().getFullYear();

const menuOpen = ref(false);
const selectedEventTab = ref('todos');
const activeHeroSlide = ref(0);
const lightboxItem = ref(null);
const openFaq = ref(null);
const formSent = ref(false);
const form = useForm({ name: '', email: '', phone: '', message: '' });
const errors = ref({});

const upcoming = computed(() => props.upcomingEvents ?? []);
const archived = computed(() => props.pastEvents ?? []);
const allEvents = computed(() => [...upcoming.value, ...archived.value]);
const heroEvents = computed(() => {
    const source = upcoming.value.length ? upcoming.value : allEvents.value;
    return source.slice(0, 5);
});
const activeHeroEvent = computed(() => heroEvents.value[activeHeroSlide.value] ?? null);
const heroVisual = computed(() => activeHeroEvent.value?.poster || santaAnaImage);

let heroTimer;

const navLinks = [
    ['Início', '/'],
    ['Sobre Nós', route('pages.sobre-nos')],
    ['Eventos', '#eventos'],
    ['Patrocínios', route('patrocinios.index')],
    ['Contacto', '#contactos'],
];

const pillars = [
    { icon: '🎭', label: 'Cultura', text: 'Mantemos vivas as tradições, as festas e os momentos que contam a história de Santana.' },
    { icon: '⚽', label: 'Desporto', text: 'Criamos oportunidades para caminhar, mexer, participar e juntar gerações.' },
    { icon: '🤝', label: 'Convívio', text: 'A associação é uma casa aberta para sócios, famílias, amigos e visitantes.' },
];

const eventTabs = computed(() => {
    const badges = [...new Set(allEvents.value.map(e => e.badge).filter(Boolean))];
    return [
        { key: 'todos', label: 'Todos' },
        { key: 'proximos', label: 'Próximos' },
        { key: 'anteriores', label: 'Anteriores' },
        ...badges.map(b => ({ key: `badge:${b}`, label: b })),
    ];
});

const filteredEvents = computed(() => {
    if (selectedEventTab.value === 'proximos') return upcoming.value;
    if (selectedEventTab.value === 'anteriores') return archived.value;
    if (selectedEventTab.value.startsWith('badge:')) {
        const badge = selectedEventTab.value.replace('badge:', '');
        return allEvents.value.filter(e => e.badge === badge);
    }
    return allEvents.value;
});

const featuredEvent = computed(() => filteredEvents.value[0] ?? allEvents.value[0] ?? null);

const galleryItems = computed(() => {
    const media = allEvents.value
        .flatMap(e => (e.media ?? []).map(m => ({ ...m, event: e.title, category: e.badge || 'Momentos especiais' })))
        .filter(m => m.tipo === 'foto');
    const posters = allEvents.value
        .filter(e => e.poster)
        .map(e => ({ tipo: 'foto', caminho: e.poster, titulo: e.title, event: e.date, category: e.badge || 'Comunidade' }));
    return [...media, ...posters].slice(0, 9);
});

const faqs = [
    ['Como posso tornar-me sócio?', 'Preenche o formulário nesta página ou contacta a associação por email, telefone ou redes sociais.'],
    ['Os eventos são abertos a não sócios?', 'Muitas iniciativas são abertas à comunidade. Quando existir inscrição obrigatória, essa indicação aparece no evento.'],
    ['Posso ajudar como voluntário?', 'Sim. Toda a ajuda conta: preparação de eventos, apoio no bar, divulgação e novas ideias para a associação.'],
    ['Onde acompanho novidades?', 'Segue a ARDC Santana no Facebook e Instagram para veres cartazes, fotografias e avisos recentes.'],
];

const scrollTo = (target) => {
    menuOpen.value = false;
    if (!target.startsWith('#')) { window.location.href = target; return; }
    document.querySelector(target)?.scrollIntoView({ behavior: 'smooth' });
};

const calendarHref = (event) => {
    const title = encodeURIComponent(event.title);
    const details = encodeURIComponent(event.description || 'Evento ARDC Santana');
    const location = encodeURIComponent(event.location || event.subtitle || 'ARDC Santana');
    return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&details=${details}&location=${location}`;
};

const eventHref = (event) => route('eventos.public.show', event.id);

const absoluteEventUrl = (event) => {
    const path = event.id ? eventHref(event) : '#eventos';
    return new URL(path, window.location.origin).toString();
};

const copyEventLink = async (event) => {
    const url = absoluteEventUrl(event);
    try { await navigator.clipboard.writeText(url); }
    catch { window.prompt('Copia o link do evento:', url); }
};

const shareEvent = async (event) => {
    const url = absoluteEventUrl(event);
    if (navigator.share) {
        try { await navigator.share({ title: event.title, text: event.description || 'Evento ARDC Santana', url }); }
        catch { copyEventLink(event); }
        return;
    }
    copyEventLink(event);
};

const selectHeroSlide = (index) => {
    if (!heroEvents.value.length) return;
    activeHeroSlide.value = (index + heroEvents.value.length) % heroEvents.value.length;
};
const nextHeroSlide = () => selectHeroSlide(activeHeroSlide.value + 1);
const prevHeroSlide = () => selectHeroSlide(activeHeroSlide.value - 1);

const validateForm = () => {
    errors.value = {};
    if (!form.name.trim()) errors.value.name = 'Indica o teu nome.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errors.value.email = 'Indica um email válido.';
    if (!form.phone.trim()) errors.value.phone = 'Indica um telefone.';
    if (form.message.trim().length < 8) errors.value.message = 'Escreve uma mensagem curta.';
    if (Object.keys(errors.value).length) return;
    form.post(route('contacto.store'), {
        preserveScroll: true,
        onSuccess: () => { formSent.value = true; form.reset(); },
        onError: () => { errors.value = form.errors; },
    });
};

onMounted(() => {
    const items = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('is-visible'); });
    }, { threshold: 0.12 });
    items.forEach(el => observer.observe(el));

    if (heroEvents.value.length > 1) {
        heroTimer = window.setInterval(nextHeroSlide, 6000);
    }
});

onBeforeUnmount(() => window.clearInterval(heroTimer));
</script>

<template>
    <Head title="ARDC Santana | Associação Recreativa, Desportiva e Cultural">
        <meta head-key="description" name="description" content="Conhece a ARDC Santana, participa nos nossos eventos, torna-te sócio e ajuda a manter viva a comunidade.">
    </Head>

    <main class="min-h-screen bg-amber-50 text-stone-800 scroll-smooth">

        <!-- NAV -->
        <header class="fixed inset-x-0 top-0 z-50 border-b border-amber-200/80 bg-amber-50/95 backdrop-blur-xl">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3.5 lg:px-8">
                <Link href="/" class="flex items-center gap-3">
                    <img :src="associationLogo" alt="Logo ARDC Santana" class="h-10 w-10 rounded-full object-contain bg-white border border-amber-200 p-1">
                    <span class="font-display text-base font-semibold tracking-wide text-stone-800">ARDC Santana</span>
                </Link>

                <div class="hidden items-center gap-0.5 md:flex">
                    <button
                        v-for="link in navLinks"
                        :key="link[0]"
                        type="button"
                        class="rounded-md px-3.5 py-2 text-sm font-medium text-stone-600 transition hover:text-stone-900 hover:bg-amber-100"
                        @click="scrollTo(link[1])"
                    >
                        {{ link[0] }}
                    </button>
                </div>

                <a :href="`mailto:${contactEmail}`" class="hidden lg:inline-flex items-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-700">
                    Contactar
                </a>

                <button type="button" class="grid h-10 w-10 place-items-center rounded-md text-stone-600 hover:bg-amber-100 md:hidden" aria-label="Abrir menu" @click="menuOpen = !menuOpen">
                    <span class="hamburger" :class="{ open: menuOpen }" />
                </button>
            </nav>

            <Transition name="menu-slide">
                <div v-if="menuOpen" class="border-t border-amber-200 bg-amber-50 px-5 py-3 md:hidden">
                    <button
                        v-for="link in navLinks"
                        :key="link[0]"
                        type="button"
                        class="block w-full rounded-md px-3 py-2.5 text-left text-sm font-medium text-stone-600 hover:text-stone-900 hover:bg-amber-100"
                        @click="scrollTo(link[1])"
                    >
                        {{ link[0] }}
                    </button>
                </div>
            </Transition>
        </header>

        <!-- HERO -->
        <section class="relative isolate flex min-h-screen flex-col justify-end overflow-hidden pt-20">
            <Transition name="hero-fade" mode="out-in">
                <img
                    :key="heroVisual"
                    :src="heroVisual"
                    alt=""
                    class="absolute inset-0 -z-20 h-full w-full object-cover"
                >
            </Transition>
            <!-- Gradiente quente dourado -->
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-stone-900/95 via-stone-800/55 to-stone-700/20" />
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-stone-900/75 via-stone-900/20 to-transparent" />

            <div class="mx-auto w-full max-w-7xl px-5 pb-16 lg:px-8">
                <div class="reveal max-w-3xl">
                    <p class="eyebrow-hero">
                        {{ activeHeroEvent?.badge || 'Associação Recreativa, Desportiva e Cultural' }}
                    </p>
                    <h1 class="font-display mt-4 text-5xl font-bold leading-[1.07] text-white sm:text-6xl lg:text-7xl">
                        {{ activeHeroEvent?.title || 'ARDC Santana' }}
                    </h1>
                    <p class="mt-5 max-w-xl text-lg leading-relaxed text-stone-200">
                        {{ activeHeroEvent?.description || 'Cultura, desporto e comunidade numa casa viva, feita por pessoas e para pessoas.' }}
                    </p>
                    <p v-if="activeHeroEvent?.date" class="mt-2 text-sm font-semibold text-amber-300">
                        {{ activeHeroEvent.date }} · {{ activeHeroEvent.location || activeHeroEvent.subtitle }}
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            v-if="activeHeroEvent?.id"
                            :href="eventHref(activeHeroEvent)"
                            class="rounded-md bg-amber-500 px-6 py-3 text-sm font-bold text-white shadow-md transition hover:bg-amber-600"
                        >
                            Ver evento
                        </Link>
                        <button type="button" class="rounded-md bg-amber-500 px-6 py-3 text-sm font-bold text-white shadow-md transition hover:bg-amber-600" @click="scrollTo('#eventos')">
                            Ver próximos eventos
                        </button>
                        <button type="button" class="rounded-md border border-white/30 bg-white/15 px-6 py-3 text-sm font-bold text-white backdrop-blur transition hover:bg-white/25" @click="scrollTo('#socios')">
                            Tornar-me sócio
                        </button>
                    </div>
                </div>

                <div v-if="heroEvents.length > 1" class="mt-10 flex items-center gap-4">
                    <button type="button" class="hero-arrow" aria-label="Anterior" @click="prevHeroSlide">‹</button>
                    <div class="flex gap-2">
                        <button
                            v-for="(_, i) in heroEvents"
                            :key="i"
                            type="button"
                            class="hero-dot"
                            :class="{ active: activeHeroSlide === i }"
                            @click="selectHeroSlide(i)"
                        />
                    </div>
                    <button type="button" class="hero-arrow" aria-label="Seguinte" @click="nextHeroSlide">›</button>
                    <span class="ml-2 text-xs font-medium text-white/50">{{ activeHeroSlide + 1 }} / {{ heroEvents.length }}</span>
                </div>
            </div>
        </section>

        <!-- SOBRE -->
        <section id="sobre" class="py-24 bg-amber-50">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal mb-14 text-center">
                    <p class="eyebrow">Sobre nós</p>
                    <h2 class="section-title mt-3">Uma casa local com memória,<br>agenda e futuro.</h2>
                    <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-stone-500">
                        A ARDC Santana é uma associação recreativa, desportiva e cultural. Nasceu da vontade de criar um ponto de encontro para a terra e continua a ser uma casa aberta para sócios, vizinhos, famílias e amigos.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-3">
                    <article v-for="pillar in pillars" :key="pillar.label" class="reveal pillar-card">
                        <span class="text-3xl">{{ pillar.icon }}</span>
                        <h3 class="mt-4 text-xl font-bold text-stone-800">{{ pillar.label }}</h3>
                        <p class="mt-2 leading-relaxed text-stone-500">{{ pillar.text }}</p>
                    </article>
                </div>

                <div class="reveal mt-12 flex flex-wrap items-center gap-8 border-t border-amber-200 pt-10">
                    <div class="kpi">
                        <span class="kpi__value">1991</span>
                        <span class="kpi__label">Fundação</span>
                    </div>
                    <div class="h-10 w-px bg-amber-200" />
                    <div class="kpi">
                        <span class="kpi__value">{{ upcoming.length }}</span>
                        <span class="kpi__label">Próximos eventos</span>
                    </div>
                    <div class="h-10 w-px bg-amber-200" />
                    <div class="kpi">
                        <span class="kpi__value">{{ allEvents.length }}</span>
                        <span class="kpi__label">Eventos publicados</span>
                    </div>
                    <div class="ml-auto">
                        <Link :href="route('pages.sobre-nos')" class="text-sm font-semibold text-amber-700 hover:text-amber-900 transition">
                            Conhecer a associação →
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- DIVISOR DOURADO -->
        <div class="gold-divider" />

        <!-- EVENTOS -->
        <section id="eventos" class="py-24 bg-white">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal mb-10">
                    <p class="eyebrow">Agenda</p>
                    <h2 class="section-title mt-3">Eventos, memórias<br>e próximos encontros.</h2>
                </div>

                <div v-if="eventTabs.length" class="mb-8 overflow-x-auto">
                    <div class="inline-flex gap-1.5 rounded-lg border border-amber-200 bg-amber-50 p-1.5">
                        <button
                            v-for="tab in eventTabs"
                            :key="tab.key"
                            type="button"
                            class="shrink-0 rounded-md px-4 py-2 text-sm font-semibold transition"
                            :class="selectedEventTab === tab.key
                                ? 'bg-amber-600 text-white shadow-sm'
                                : 'text-stone-600 hover:text-stone-900 hover:bg-amber-100'"
                            @click="selectedEventTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <div v-if="filteredEvents.length" class="grid gap-6 xl:grid-cols-[1fr_1.4fr]">
                    <!-- Featured -->
                    <article v-if="featuredEvent" class="reveal event-card-featured">
                        <div class="relative overflow-hidden">
                            <img :src="featuredEvent.poster || associationLogo" :alt="featuredEvent.title" class="aspect-video w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-stone-900/70 to-transparent" />
                            <span class="absolute left-4 top-4 rounded-full bg-amber-500 px-3 py-1 text-xs font-bold text-white shadow">
                                {{ featuredEvent.badge || 'Destaque' }}
                            </span>
                        </div>
                        <div class="p-6">
                            <p class="text-sm font-semibold text-amber-700">{{ featuredEvent.date }} · {{ featuredEvent.location || featuredEvent.subtitle }}</p>
                            <h3 class="mt-2 text-2xl font-bold text-stone-800 leading-tight">{{ featuredEvent.title }}</h3>
                            <p class="mt-3 leading-relaxed text-stone-500 line-clamp-3">{{ featuredEvent.description || 'Mais informações em breve.' }}</p>
                            <div class="mt-6 flex flex-wrap gap-2">
                                <Link v-if="featuredEvent.id" :href="eventHref(featuredEvent)" class="rounded-md bg-amber-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-700 transition shadow-sm">
                                    Ver detalhes
                                </Link>
                                <a :href="calendarHref(featuredEvent)" target="_blank" rel="noreferrer" class="rounded-md border border-amber-200 px-4 py-2.5 text-sm font-semibold text-stone-700 hover:bg-amber-50 transition">
                                    Calendário
                                </a>
                                <button type="button" class="rounded-md border border-amber-200 px-4 py-2.5 text-sm font-semibold text-stone-700 hover:bg-amber-50 transition" @click="shareEvent(featuredEvent)">
                                    Partilhar
                                </button>
                            </div>
                        </div>
                    </article>

                    <!-- List -->
                    <div class="grid content-start gap-3">
                        <article
                            v-for="event in filteredEvents"
                            :key="event.id || event.title"
                            class="reveal event-card-row"
                        >
                            <img :src="event.poster || associationLogo" :alt="event.title" class="h-24 w-24 shrink-0 rounded-md object-cover sm:h-28 sm:w-28">
                            <div class="flex min-w-0 flex-col justify-between gap-2 py-1">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">{{ event.badge || 'Evento' }}</span>
                                        <span class="text-xs text-stone-400">{{ event.date }}</span>
                                    </div>
                                    <h3 class="mt-1.5 text-base font-bold text-stone-800 leading-snug">{{ event.title }}</h3>
                                    <p class="mt-0.5 text-sm text-stone-400">{{ event.location || event.subtitle }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Link v-if="event.id" :href="eventHref(event)" class="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700 transition">
                                        Saber mais
                                    </Link>
                                    <button type="button" class="rounded-md border border-amber-200 px-3 py-1.5 text-xs font-semibold text-stone-600 hover:bg-amber-50 transition" @click="copyEventLink(event)">
                                        Copiar link
                                    </button>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <div v-else class="reveal rounded-lg border border-dashed border-amber-300 bg-amber-50 p-12 text-center">
                    <p class="text-lg font-bold text-stone-800">Sem eventos nesta seleção.</p>
                    <button type="button" class="mt-4 rounded-md bg-amber-600 px-4 py-2.5 text-sm font-bold text-white" @click="selectedEventTab = 'todos'">
                        Ver todos
                    </button>
                </div>
            </div>
        </section>

        <!-- DIVISOR DOURADO -->
        <div class="gold-divider" />

        <!-- SÓCIOS + CONTACTO -->
        <section id="socios" class="py-24 bg-amber-50">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="grid gap-12 lg:grid-cols-2 lg:items-start">
                    <div class="reveal">
                        <p class="eyebrow">Torna-te sócio</p>
                        <h2 class="section-title mt-3">Faz parte<br>da associação.</h2>
                        <p class="mt-5 text-lg leading-relaxed text-stone-500">
                            Ser sócio é participar, apoiar a manutenção da associação e ajudar a manter viva esta casa comunitária. Toda a contribuição faz diferença.
                        </p>
                        <ul class="mt-8 space-y-3">
                            <li v-for="item in ['Participar em eventos', 'Tornar-se sócio', 'Voluntariado', 'Apoio a iniciativas', 'Partilhar nas redes sociais']" :key="item" class="flex items-center gap-3 text-stone-600">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
                                {{ item }}
                            </li>
                        </ul>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="https://www.facebook.com/ardcsantana" target="_blank" rel="noreferrer" class="rounded-md border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-700 hover:bg-amber-100 transition shadow-sm">Facebook</a>
                            <a href="https://www.instagram.com/ardcsantana/" target="_blank" rel="noreferrer" class="rounded-md border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-700 hover:bg-amber-100 transition shadow-sm">Instagram</a>
                        </div>
                    </div>

                    <form class="reveal rounded-xl border border-amber-200 bg-white p-8 shadow-md" novalidate @submit.prevent="validateForm">
                        <h3 class="text-xl font-bold text-stone-800">Contacta-nos</h3>
                        <p class="mt-1 text-sm text-stone-500">Responderemos o mais brevemente possível.</p>
                        <div class="mt-6 grid gap-4 sm:grid-cols-2">
                            <label class="field">
                                <span>Nome</span>
                                <input v-model="form.name" type="text" autocomplete="name">
                                <span v-if="errors.name" class="error">{{ errors.name }}</span>
                            </label>
                            <label class="field">
                                <span>Email</span>
                                <input v-model="form.email" type="email" autocomplete="email">
                                <span v-if="errors.email" class="error">{{ errors.email }}</span>
                            </label>
                            <label class="field sm:col-span-2">
                                <span>Telefone</span>
                                <input v-model="form.phone" type="tel" autocomplete="tel">
                                <span v-if="errors.phone" class="error">{{ errors.phone }}</span>
                            </label>
                            <label class="field sm:col-span-2">
                                <span>Mensagem</span>
                                <textarea v-model="form.message" rows="4" />
                                <span v-if="errors.message" class="error">{{ errors.message }}</span>
                            </label>
                        </div>
                        <button type="submit" class="mt-5 w-full rounded-md bg-amber-600 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-amber-700 disabled:opacity-50" :disabled="form.processing">
                            {{ form.processing ? 'A enviar...' : 'Enviar mensagem' }}
                        </button>
                        <p v-if="formSent" class="mt-3 rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm font-medium text-emerald-800">
                            Obrigado. A tua mensagem foi recebida.
                        </p>
                    </form>
                </div>
            </div>
        </section>

        <!-- GALERIA -->
        <section id="galeria" class="py-24 bg-white border-t border-amber-100">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal mb-10 text-center">
                    <p class="eyebrow">Galeria</p>
                    <h2 class="section-title mt-3">Momentos especiais.</h2>
                </div>

                <div v-if="galleryItems.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="item in galleryItems"
                        :key="`${item.caminho}-${item.titulo}`"
                        type="button"
                        class="group relative overflow-hidden rounded-xl shadow-sm"
                        @click="lightboxItem = item"
                    >
                        <img :src="item.caminho" :alt="item.titulo || item.event" class="aspect-[4/3] w-full object-cover transition duration-500 group-hover:scale-105">
                        <div class="absolute inset-0 bg-amber-900/0 transition duration-300 group-hover:bg-amber-900/40" />
                        <div class="absolute inset-0 flex items-end p-4 opacity-0 transition group-hover:opacity-100">
                            <p class="text-sm font-semibold text-white drop-shadow">{{ item.titulo || item.event }}</p>
                        </div>
                    </button>
                </div>
                <div v-else class="rounded-xl border border-dashed border-amber-200 bg-amber-50 p-12 text-center">
                    <p class="text-stone-500">Ainda não há fotografias publicadas.</p>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="py-24 bg-amber-50 border-t border-amber-100">
            <div class="mx-auto max-w-3xl px-5 lg:px-8">
                <div class="reveal mb-10 text-center">
                    <p class="eyebrow">FAQ</p>
                    <h2 class="section-title mt-3">Perguntas frequentes.</h2>
                </div>
                <div class="space-y-2">
                    <article v-for="(faq, index) in faqs" :key="faq[0]" class="reveal overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
                        <button type="button" class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left font-semibold text-stone-800 hover:bg-amber-50 transition" @click="openFaq = openFaq === index ? null : index">
                            {{ faq[0] }}
                            <span class="shrink-0 text-amber-600 text-xl font-bold leading-none">{{ openFaq === index ? '−' : '+' }}</span>
                        </button>
                        <Transition name="faq">
                            <p v-if="openFaq === index" class="border-t border-amber-100 px-6 py-5 leading-relaxed text-stone-500">{{ faq[1] }}</p>
                        </Transition>
                    </article>
                </div>
            </div>
        </section>

        <!-- CONTACTOS -->
        <section id="contactos" class="py-24 bg-white border-t border-amber-100">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-[1fr_1.5fr] lg:items-start">
                    <div class="reveal">
                        <p class="eyebrow">Contactos</p>
                        <h2 class="section-title mt-3">Fala connosco.</h2>
                        <div class="mt-8 space-y-4 text-stone-500">
                            <p class="font-medium text-stone-700">Santana, Carvalhal Benfeito<br>Caldas da Rainha</p>
                            <p>
                                <a :href="`mailto:${contactEmail}`" class="font-semibold text-amber-700 hover:text-amber-900 transition">{{ contactEmail }}</a>
                            </p>
                        </div>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="https://www.facebook.com/ardcsantana" target="_blank" rel="noreferrer" class="rounded-md border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-700 hover:bg-amber-50 transition shadow-sm">Facebook</a>
                            <a href="https://www.instagram.com/ardcsantana/" target="_blank" rel="noreferrer" class="rounded-md border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-stone-700 hover:bg-amber-50 transition shadow-sm">Instagram</a>
                        </div>
                    </div>

                    <div class="reveal overflow-hidden rounded-xl border border-amber-200 shadow-md">
                        <iframe
                            title="Localização da ARDC Santana"
                            class="h-[400px] w-full"
                            loading="lazy"
                            src="https://www.google.com/maps?q=Santana%20Carvalhal%20Benfeito%20Caldas%20da%20Rainha&output=embed"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- PATROCINADORES -->
        <SponsorsSlider :patrocinadores="props.patrocinadores || []" />

        <!-- FOOTER -->
        <footer class="border-t border-amber-200 bg-stone-800 py-10 text-stone-300">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <img :src="associationLogo" alt="" class="h-9 w-9 rounded-full object-contain bg-stone-700 border border-amber-700/40 p-1">
                        <span class="font-semibold text-white">ARDC Santana</span>
                    </div>
                    <div class="flex flex-wrap gap-4 text-sm text-stone-400">
                        <button v-for="link in navLinks" :key="link[0]" type="button" class="hover:text-amber-300 transition" @click="scrollTo(link[1])">{{ link[0] }}</button>
                    </div>
                </div>
                <div class="mt-8 flex flex-col gap-3 border-t border-stone-700 pt-6 text-xs text-stone-500 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap gap-4">
                        <Link :href="route('legal.privacidade')" class="hover:text-amber-300 transition">Política de Privacidade</Link>
                        <Link :href="route('legal.termos')" class="hover:text-amber-300 transition">Termos e Condições</Link>
                        <Link :href="route('legal.cookies')" class="hover:text-amber-300 transition">Política de Cookies</Link>
                        <a href="https://www.livroreclamacoes.pt" target="_blank" rel="noopener" class="hover:text-amber-300 transition">Livro de Reclamações</a>
                    </div>
                    <div class="flex items-center gap-4">
                        <p>© {{ currentYear }} Associação de Santana.</p>
                        <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-medium text-stone-400 hover:text-amber-300 transition">#CreatingDevelopingImproving4you</a>
                    </div>
                </div>
            </div>
        </footer>

        <CookieBanner />

        <!-- LIGHTBOX -->
        <Transition name="lightbox">
            <div v-if="lightboxItem" class="fixed inset-0 z-[60] grid place-items-center bg-stone-900/85 p-5 backdrop-blur-sm" @click.self="lightboxItem = null">
                <div class="max-w-4xl w-full overflow-hidden rounded-xl border border-amber-200 shadow-2xl">
                    <img :src="lightboxItem.caminho" :alt="lightboxItem.titulo || lightboxItem.event" class="max-h-[70vh] w-full object-contain bg-stone-100">
                    <div class="flex items-center justify-between gap-4 bg-white p-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">{{ lightboxItem.category }}</p>
                            <h3 class="mt-0.5 font-bold text-stone-800">{{ lightboxItem.titulo || lightboxItem.event }}</h3>
                        </div>
                        <button type="button" class="rounded-md border border-amber-200 px-4 py-2 text-sm font-semibold text-stone-700 hover:bg-amber-50 transition" @click="lightboxItem = null">Fechar</button>
                    </div>
                </div>
            </div>
        </Transition>
    </main>
</template>

<style scoped>
.eyebrow {
    color: #b45309;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
}

.eyebrow-hero {
    color: #fcd34d;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
}

.section-title {
    color: #1c1917;
    font-size: clamp(2rem, 4.5vw, 3.5rem);
    font-weight: 700;
    line-height: 1.1;
}

.gold-divider {
    height: 3px;
    background: linear-gradient(90deg, transparent, #d97706 20%, #f59e0b 50%, #d97706 80%, transparent);
}

/* KPIs */
.kpi { display: flex; flex-direction: column; gap: 0.2rem; }
.kpi__value { font-size: 1.75rem; font-weight: 700; color: #92400e; line-height: 1; }
.kpi__label { font-size: 0.75rem; color: #a8a29e; font-weight: 500; }

/* Pillars */
.pillar-card {
    background: #fff;
    border: 1px solid #fde68a;
    border-radius: 0.75rem;
    padding: 1.75rem;
    box-shadow: 0 1px 4px rgb(0 0 0 / 0.04);
    transition: border-color 300ms, box-shadow 300ms;
}
.pillar-card:hover {
    border-color: #f59e0b;
    box-shadow: 0 4px 16px rgb(245 158 11 / 0.12);
}

/* Event cards */
.event-card-featured {
    background: #fff;
    border: 1px solid #fde68a;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 2px 8px rgb(0 0 0 / 0.06);
    transition: box-shadow 300ms;
}
.event-card-featured:hover {
    box-shadow: 0 6px 24px rgb(245 158 11 / 0.15);
}

.event-card-row {
    display: flex;
    gap: 1rem;
    padding: 0.875rem;
    background: #fff;
    border: 1px solid #fde68a;
    border-radius: 0.75rem;
    box-shadow: 0 1px 4px rgb(0 0 0 / 0.04);
    transition: border-color 300ms, box-shadow 300ms;
}
.event-card-row:hover {
    border-color: #f59e0b;
    box-shadow: 0 4px 16px rgb(245 158 11 / 0.12);
}

/* Hero nav */
.hero-arrow {
    display: grid;
    place-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 9999px;
    border: 1px solid rgb(255 255 255 / 0.3);
    background: rgb(255 255 255 / 0.1);
    color: #fff;
    font-size: 1.4rem;
    font-weight: 700;
    line-height: 1;
    backdrop-blur: blur(4px);
    transition: background 200ms, border-color 200ms;
}
.hero-arrow:hover {
    background: rgb(245 158 11 / 0.4);
    border-color: #f59e0b;
}

.hero-dot {
    width: 1.5rem;
    height: 0.35rem;
    border-radius: 999px;
    background: rgb(255 255 255 / 0.35);
    transition: background 300ms, width 300ms;
}
.hero-dot.active {
    background: #fbbf24;
    width: 2.5rem;
}

/* Form */
.field { display: grid; gap: 0.35rem; font-size: 0.875rem; font-weight: 500; color: #57534e; }
.field input,
.field textarea {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 0.5rem;
    color: #1c1917;
    font-size: 0.875rem;
    padding: 0.625rem 0.875rem;
    transition: border-color 200ms, box-shadow 200ms;
    width: 100%;
}
.field input:focus,
.field textarea:focus {
    border-color: #d97706;
    box-shadow: 0 0 0 3px rgb(217 119 6 / 0.15);
    outline: none;
}
.error { color: #dc2626; font-size: 0.75rem; }

/* Hamburger */
.hamburger,
.hamburger::before,
.hamburger::after {
    background: currentColor;
    border-radius: 999px;
    content: '';
    display: block;
    height: 2px;
    transition: transform 200ms ease, opacity 200ms ease;
    width: 1.25rem;
}
.hamburger::before { transform: translateY(-6px); }
.hamburger::after  { transform: translateY(4px); }
.hamburger.open { background: transparent; }
.hamburger.open::before { transform: translateY(2px) rotate(45deg); }
.hamburger.open::after  { transform: translateY(0) rotate(-45deg); }

/* Reveal */
.reveal {
    opacity: 0;
    transform: translateY(18px);
    transition: opacity 600ms ease, transform 600ms ease;
}
.reveal.is-visible { opacity: 1; transform: translateY(0); }

/* Transitions */
.menu-slide-enter-active,
.menu-slide-leave-active { transition: opacity 180ms ease, transform 180ms ease; }
.menu-slide-enter-from,
.menu-slide-leave-to { opacity: 0; transform: translateY(-0.5rem); }

.hero-fade-enter-active,
.hero-fade-leave-active { transition: opacity 800ms ease; }
.hero-fade-enter-from,
.hero-fade-leave-to { opacity: 0; }

.lightbox-enter-active,
.lightbox-leave-active { transition: opacity 200ms ease; }
.lightbox-enter-from,
.lightbox-leave-to { opacity: 0; }

.faq-enter-active,
.faq-leave-active { transition: opacity 200ms ease, max-height 250ms ease; max-height: 200px; overflow: hidden; }
.faq-enter-from,
.faq-leave-to { opacity: 0; max-height: 0; }
</style>
