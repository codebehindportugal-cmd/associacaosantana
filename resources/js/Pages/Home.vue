<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    upcomingEvents: Array,
    pastEvents: Array,
});

const associationLogo = '/images/santana-logo.png';
const santaAnaImage = '/images/santa-ana.png';
const contactEmail = 'ardcsantana@outlook.com';

const menuOpen = ref(false);
const selectedEventTab = ref('todos');
const selectedNeed = ref('Caminhadas e natureza');
const activeHeroSlide = ref(0);
const lightboxItem = ref(null);
const openFaq = ref(0);
const formSent = ref(false);
const form = useForm({
    name: '',
    email: '',
    phone: '',
    message: '',
});
const errors = ref({});

const upcoming = computed(() => props.upcomingEvents ?? []);
const archived = computed(() => props.pastEvents ?? []);
const allEvents = computed(() => [...upcoming.value, ...archived.value]);
const heroEvents = computed(() => upcoming.value);
const activeHeroEvent = computed(() => heroEvents.value[activeHeroSlide.value] ?? null);

let heroSliderTimer;

const navLinks = [
    ['Sobre', '#sobre'],
    ['Eventos', '#eventos'],
    ['Galeria', '#galeria'],
    ['Sócios', '#socios'],
    ['Contactos', '#contactos'],
];

const pillars = [
    ['Cultura', 'Mantemos vivas as tradições, as festas e os momentos que contam a história de Santana.'],
    ['Desporto', 'Criamos oportunidades para caminhar, mexer, participar e juntar gerações.'],
    ['Convívio', 'A associação é uma casa aberta para sócios, famílias, amigos e visitantes.'],
];

const eventTabs = computed(() => {
    const badges = allEvents.value
        .map((event) => event.badge)
        .filter(Boolean);

    return [
        { key: 'todos', label: 'Todos' },
        { key: 'proximos', label: 'Próximos' },
        { key: 'anteriores', label: 'Anteriores' },
        ...[...new Set(badges)].map((badge) => ({ key: `badge:${badge}`, label: badge })),
    ];
});

const filteredEvents = computed(() => {
    if (selectedEventTab.value === 'proximos') return upcoming.value;
    if (selectedEventTab.value === 'anteriores') return archived.value;
    if (selectedEventTab.value.startsWith('badge:')) {
        const badge = selectedEventTab.value.replace('badge:', '');
        return allEvents.value.filter((event) => event.badge === badge);
    }

    return allEvents.value;
});

const quizOptions = [
    'Caminhadas e natureza',
    'Música e festas',
    'Almoços e convívios',
    'Atividades para família',
    'Apoiar a associação',
];

const recommendedEvents = computed(() => {
    const terms = {
        'Caminhadas e natureza': ['caminhada', 'passeio', 'natureza', 'desporto'],
        'Música e festas': ['festa', 'música', 'musica', 'dj', 'banda', 'noite'],
        'Almoços e convívios': ['almoço', 'almoco', 'convívio', 'convivio', 'restaurante'],
        'Atividades para família': ['família', 'familia', 'comunidade', 'sócios', 'socios'],
        'Apoiar a associação': ['sócio', 'socio', 'associação', 'associacao', 'voluntariado'],
    }[selectedNeed.value];

    const availableEvents = upcoming.value.length ? upcoming.value : allEvents.value;
    const matches = availableEvents.filter((event) => {
        const haystack = `${event.title} ${event.badge} ${event.description} ${event.subtitle}`.toLowerCase();
        return terms.some((term) => haystack.includes(term));
    });

    return (matches.length ? matches : availableEvents).slice(0, 3);
});

const galleryItems = computed(() => {
    const media = allEvents.value
        .flatMap((event) => (event.media ?? []).map((item) => ({
            ...item,
            event: event.title,
            category: event.badge || 'Momentos especiais',
        })))
        .filter((item) => item.tipo === 'foto');

    const posters = allEvents.value
        .filter((event) => event.poster)
        .map((event) => ({
            tipo: 'foto',
            caminho: event.poster,
            titulo: event.title,
            event: event.date,
            category: event.badge || 'Comunidade',
        }));

    return [...media, ...posters].slice(0, 9);
});

const pastPhotoItems = computed(() => archived.value
    .flatMap((event) => (event.media ?? []).map((item) => ({
        ...item,
        event: event.title,
        date: event.date,
        category: event.badge || 'Evento anterior',
    })))
    .filter((item) => item.tipo === 'foto'));

const supportOptions = [
    'Participar em eventos',
    'Tornar-se sócio',
    'Voluntariado',
    'Apoio a iniciativas',
    'Partilhar nas redes sociais',
];

const faqs = [
    ['Como posso tornar-me sócio?', 'Preenche o formulário nesta página ou contacta a associação por email, telefone ou redes sociais.'],
    ['Os eventos são abertos a não sócios?', 'Muitas iniciativas são abertas à comunidade. Quando existir inscrição obrigatória, essa indicação aparece no evento.'],
    ['Posso ajudar como voluntário?', 'Sim. Toda a ajuda conta: preparação de eventos, apoio no bar, divulgação e novas ideias para a associação.'],
    ['Onde acompanho novidades?', 'Segue a ARDC Santana no Facebook e Instagram para veres cartazes, fotografias e avisos recentes.'],
];

const scrollTo = (target) => {
    menuOpen.value = false;
    document.querySelector(target)?.scrollIntoView({ behavior: 'smooth' });
};

const calendarHref = (event) => {
    const title = encodeURIComponent(event.title);
    const details = encodeURIComponent(event.description || 'Evento ARDC Santana');
    const location = encodeURIComponent(event.location || event.subtitle || 'ARDC Santana');
    return `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&details=${details}&location=${location}`;
};

const eventHref = (event) => route('eventos.public.show', event.id);

const selectHeroSlide = (index) => {
    if (!heroEvents.value.length) return;
    activeHeroSlide.value = (index + heroEvents.value.length) % heroEvents.value.length;
};

const nextHeroSlide = () => selectHeroSlide(activeHeroSlide.value + 1);
const previousHeroSlide = () => selectHeroSlide(activeHeroSlide.value - 1);

const validateForm = () => {
    errors.value = {};

    if (!form.name.trim()) errors.value.name = 'Indica o teu nome.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errors.value.email = 'Indica um email válido.';
    if (!form.phone.trim()) errors.value.phone = 'Indica um telefone.';
    if (form.message.trim().length < 8) errors.value.message = 'Escreve uma mensagem curta.';

    if (Object.keys(errors.value).length) return;

    form.post(route('contacto.store'), {
        preserveScroll: true,
        onSuccess: () => {
            formSent.value = true;
            form.reset();
        },
        onError: () => {
            errors.value = form.errors;
        },
    });
};

onMounted(() => {
    const items = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) entry.target.classList.add('is-visible');
        });
    }, { threshold: 0.16 });

    items.forEach((item) => observer.observe(item));

    if (heroEvents.value.length > 1) {
        heroSliderTimer = window.setInterval(nextHeroSlide, 6500);
    }
});

onBeforeUnmount(() => {
    window.clearInterval(heroSliderTimer);
});
</script>

<template>
    <Head title="ARDC Santana | Associação Recreativa, Desportiva e Cultural">
        <meta
            head-key="description"
            name="description"
            content="Conhece a ARDC Santana, participa nos nossos eventos, torna-te sócio e ajuda a manter viva a comunidade."
        >
    </Head>

    <main class="min-h-screen scroll-smooth bg-white text-slate-900">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-slate-200 bg-white/95 backdrop-blur-sm">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8">
                <Link href="/" class="flex items-center gap-3">
                    <img :src="associationLogo" alt="Logo ARDC Santana" class="h-10 w-10 rounded bg-slate-100 object-contain p-2">
                    <span class="text-base font-semibold text-slate-900">ARDC Santana</span>
                </Link>

                <div class="hidden items-center gap-1 md:flex">
                    <button
                        v-for="link in navLinks"
                        :key="link[0]"
                        type="button"
                        class="rounded-md px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900 hover:bg-slate-100"
                        @click="scrollTo(link[1])"
                    >
                        {{ link[0] }}
                    </button>
                </div>

                <a :href="`mailto:${contactEmail}`" class="hidden rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800 lg:inline-flex">
                    Contactar
                </a>

                <button type="button" class="grid h-10 w-10 place-items-center rounded-md text-slate-600 hover:bg-slate-100 md:hidden" aria-label="Abrir menu" @click="menuOpen = !menuOpen">
                    <span class="menu-icon" :class="{ open: menuOpen }" />
                </button>
            </nav>

            <Transition name="mobile-menu">
                <div v-if="menuOpen" class="border-t border-slate-200 bg-white px-5 py-4 md:hidden">
                    <button
                        v-for="link in navLinks"
                        :key="link[0]"
                        type="button"
                        class="block w-full rounded-md px-3 py-2 text-left font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-100"
                        @click="scrollTo(link[1])"
                    >
                        {{ link[0] }}
                    </button>
                </div>
            </Transition>
        </header>

        <section class="relative isolate overflow-hidden pt-20 pb-16">
            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-slate-50 to-white" />
            
            <div class="relative mx-auto grid min-h-[calc(100vh-5rem)] max-w-7xl items-center gap-12 px-5 lg:grid-cols-[1fr_1fr] lg:px-8">
                <div class="reveal">
                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Bem-vindo à associação</p>
                    <h1 class="mt-3 text-5xl font-bold leading-tight text-slate-900 sm:text-6xl lg:text-7xl">
                        ARDC Santana
                    </h1>
                    <p class="mt-5 text-xl text-slate-600">
                        Cultura, desporto e comunidade. Uma casa feita por pessoas, para pessoas.
                    </p>
                    <p class="mt-4 max-w-2xl text-slate-600 leading-relaxed">
                        Fundada em 1991, somos um ponto de encontro para eventos, convívios, caminhadas e iniciativas que mantêm Santana viva e unida.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <button type="button" class="rounded-lg bg-slate-900 px-6 py-3 font-semibold text-white transition hover:bg-slate-800" @click="scrollTo('#eventos')">
                            Ver próximos eventos
                        </button>
                        <button type="button" class="rounded-lg border border-slate-300 px-6 py-3 font-semibold text-slate-900 transition hover:bg-slate-50" @click="scrollTo('#socios')">
                            Tornar-me sócio
                        </button>
                    </div>

                    <div class="mt-10 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-lg bg-slate-50 p-4 border border-slate-200">
                            <div class="text-2xl font-bold text-slate-900">1991</div>
                            <div class="text-sm text-slate-600">Fundada</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4 border border-slate-200">
                            <div class="text-2xl font-bold text-slate-900">Santana</div>
                            <div class="text-sm text-slate-600">Carvalhal Benfeito</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4 border border-slate-200">
                            <div class="text-2xl font-bold text-slate-900">Comunidade</div>
                            <div class="text-sm text-slate-600">Cultura e desporto</div>
                        </div>
                    </div>
                </div>

                <aside v-if="activeHeroEvent" class="reveal hidden lg:block">
                    <div class="overflow-hidden rounded-lg shadow-lg border border-slate-200">
                        <Transition name="hero-slide" mode="out-in">
                            <img :key="activeHeroEvent.poster" :src="activeHeroEvent.poster || associationLogo" :alt="activeHeroEvent.title" class="aspect-[4/3] w-full object-cover">
                        </Transition>

                        <div class="p-5 bg-white">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">{{ activeHeroEvent.badge || 'Próximo evento' }}</p>
                            <h2 class="mt-2 text-2xl font-bold text-slate-900">{{ activeHeroEvent.title }}</h2>
                            <p class="mt-2 font-medium text-slate-600">{{ activeHeroEvent.date }} · {{ activeHeroEvent.location || activeHeroEvent.subtitle }}</p>
                            <p class="mt-3 text-slate-600 leading-relaxed text-sm">{{ activeHeroEvent.description }}</p>

                            <div v-if="heroEvents.length > 1" class="mt-4 flex gap-2">
                                <button
                                    v-for="(event, index) in heroEvents"
                                    :key="event.id || event.title"
                                    type="button"
                                    class="h-2 flex-1 rounded-full transition bg-slate-200"
                                    :class="activeHeroSlide === index ? 'bg-slate-900' : 'hover:bg-slate-300'"
                                    @click="selectHeroSlide(index)"
                                />
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>

        <section id="sobre" class="py-16 bg-slate-50 border-y border-slate-200">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal max-w-3xl">
                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Sobre nós</p>
                    <h2 class="mt-3 text-4xl font-bold text-slate-900">Uma casa local com memória e futuro.</h2>
                    <p class="mt-5 text-lg text-slate-600 leading-relaxed">
                        A ARDC Santana é uma associação recreativa, desportiva e cultural. Nasceu da vontade de criar um ponto de encontro para a terra e continua a ser uma casa aberta para sócios, vizinhos, famílias e amigos.
                    </p>
                </div>

                <div class="mt-10 grid gap-6 sm:grid-cols-3">
                    <article v-for="pillar in pillars" :key="pillar[0]" class="reveal rounded-lg bg-white p-6 border border-slate-200 hover:shadow-md transition">
                        <div class="inline-block mb-3 text-2xl font-bold text-slate-900">{{ pillar[0].slice(0, 1) }}</div>
                        <h3 class="text-xl font-bold text-slate-900">{{ pillar[0] }}</h3>
                        <p class="mt-3 text-slate-600 leading-relaxed">{{ pillar[1] }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="eventos" class="py-16">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal mb-8">
                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Próximos eventos</p>
                    <h2 class="mt-3 text-4xl font-bold text-slate-900">O que está a acontecer.</h2>
                </div>

                <div v-if="eventTabs.length" class="mb-8 flex flex-wrap gap-2">
                    <button
                        v-for="tab in eventTabs"
                        :key="tab.key"
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-medium border transition"
                        :class="selectedEventTab === tab.key ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-300 hover:border-slate-400'"
                        @click="selectedEventTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div v-if="filteredEvents.length" class="grid gap-6 lg:grid-cols-3">
                    <article v-for="event in filteredEvents" :key="event.id || event.title" class="reveal group overflow-hidden rounded-lg bg-white border border-slate-200 hover:shadow-lg transition">
                        <img :src="event.poster || associationLogo" :alt="event.title" class="aspect-[4/3] w-full object-cover group-hover:scale-105 transition duration-300">
                        <div class="p-6">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-1 rounded">{{ event.badge || 'Evento' }}</span>
                                <span class="text-sm font-medium text-slate-600">{{ event.date }}</span>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900">{{ event.title }}</h3>
                            <p class="mt-1 text-sm font-medium text-slate-600">{{ event.location || event.subtitle }}</p>
                            <p class="mt-3 text-slate-600 text-sm leading-relaxed">{{ event.description }}</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a :href="calendarHref(event)" target="_blank" rel="noreferrer" class="text-sm font-medium text-slate-900 hover:underline">+ Adicionar calendário</a>
                                <Link v-if="event.id" :href="eventHref(event)" class="text-sm font-medium text-slate-900 hover:underline">+ Saber mais</Link>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="rounded-lg bg-slate-50 p-8 text-center border border-slate-200">
                    <p class="text-slate-600">Ainda não há eventos publicados. Consulta-nos em breve.</p>
                </div>
            </div>
        </section>

        <section class="py-16 bg-slate-900 text-white">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal grid lg:grid-cols-[0.9fr_1.1fr] gap-8">
                    <div>
                        <p class="text-sm font-semibold text-slate-400 uppercase tracking-wide">Encontra o evento ideal</p>
                        <h2 class="mt-3 text-4xl font-bold">O que procuras?</h2>
                        <div class="mt-6 flex flex-wrap gap-2">
                            <button
                                v-for="option in quizOptions"
                                :key="option"
                                type="button"
                                class="rounded-lg border px-4 py-2 text-sm font-medium transition"
                                :class="selectedNeed === option ? 'bg-slate-100 text-slate-900 border-slate-100' : 'border-slate-700 text-white hover:border-slate-600'"
                                @click="selectedNeed = option"
                            >
                                {{ option }}
                            </button>
                        </div>
                    </div>

                    <div v-if="recommendedEvents.length" class="grid gap-3">
                        <article v-for="event in recommendedEvents" :key="event.id || event.title" class="rounded-lg bg-slate-800 p-4 border border-slate-700">
                            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ event.badge || 'Recomendado' }}</p>
                            <h3 class="mt-2 text-lg font-bold">{{ event.title }}</h3>
                            <p class="mt-1 text-sm text-slate-300">{{ event.date }} · {{ event.location || event.subtitle }}</p>
                        </article>
                    </div>
                    <div v-else class="rounded-lg bg-slate-800 p-4 border border-slate-700 font-medium text-slate-300">
                        Publica eventos no backoffice para ativar recomendações.
                    </div>
                </div>
            </div>
        </section>

        <section id="galeria" class="py-16">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal mb-8">
                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Galeria</p>
                    <h2 class="mt-3 text-4xl font-bold text-slate-900">Momentos especiais.</h2>
                </div>

                <div v-if="galleryItems.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="item in galleryItems"
                        :key="`${item.caminho}-${item.titulo}`"
                        type="button"
                        class="group relative overflow-hidden rounded-lg"
                        @click="lightboxItem = item"
                    >
                        <img :src="item.caminho" :alt="item.titulo || item.event" class="aspect-[4/3] w-full object-cover group-hover:scale-105 transition duration-300">
                        <span class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition" />
                    </button>
                </div>

                <div v-else class="rounded-lg bg-slate-50 p-8 text-center border border-slate-200">
                    <p class="text-slate-600">Ainda não há fotografias publicadas.</p>
                </div>
            </div>
        </section>

        <section id="socios" class="py-16 bg-slate-50 border-y border-slate-200">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div class="reveal">
                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Torna-te sócio</p>
                    <h2 class="mt-3 text-4xl font-bold text-slate-900">Faz parte da associação.</h2>
                    <p class="mt-5 text-lg text-slate-600 leading-relaxed">
                        Ser sócio é participar, apoiar a manutenção da associação e ajudar a manter viva esta casa comunitária.
                    </p>
                    <div class="mt-6 grid gap-3">
                        <div v-for="item in supportOptions" :key="item" class="rounded-lg bg-white p-4 font-medium text-slate-600 border border-slate-200">{{ item }}</div>
                    </div>
                </div>

                <form class="reveal rounded-lg bg-white p-6 shadow-md border border-slate-200" novalidate @submit.prevent="validateForm">
                    <h3 class="text-xl font-bold text-slate-900 mb-4">Contacta-nos</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="form-field">
                            <span class="block text-sm font-medium text-slate-700 mb-1">Nome</span>
                            <input v-model="form.name" type="text" autocomplete="name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
                            <span v-if="errors.name" class="text-xs text-red-600 mt-1">{{ errors.name }}</span>
                        </label>
                        <label class="form-field">
                            <span class="block text-sm font-medium text-slate-700 mb-1">Email</span>
                            <input v-model="form.email" type="email" autocomplete="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
                            <span v-if="errors.email" class="text-xs text-red-600 mt-1">{{ errors.email }}</span>
                        </label>
                        <label class="form-field sm:col-span-2">
                            <span class="block text-sm font-medium text-slate-700 mb-1">Telefone</span>
                            <input v-model="form.phone" type="tel" autocomplete="tel" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-slate-900 focus:ring-1 focus:ring-slate-900">
                            <span v-if="errors.phone" class="text-xs text-red-600 mt-1">{{ errors.phone }}</span>
                        </label>
                        <label class="form-field sm:col-span-2">
                            <span class="block text-sm font-medium text-slate-700 mb-1">Mensagem</span>
                            <textarea v-model="form.message" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-slate-900 focus:border-slate-900 focus:ring-1 focus:ring-slate-900"></textarea>
                            <span v-if="errors.message" class="text-xs text-red-600 mt-1">{{ errors.message }}</span>
                        </label>
                    </div>
                    <button type="submit" class="mt-4 w-full rounded-lg bg-slate-900 px-5 py-2.5 font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60" :disabled="form.processing">
                        {{ form.processing ? 'A enviar...' : 'Enviar' }}
                    </button>
                    <p v-if="formSent" class="mt-3 rounded-lg bg-emerald-100 p-3 text-sm font-medium text-emerald-900">
                        Obrigado. A tua mensagem foi recebida.
                    </p>
                </form>
            </div>
        </section>

        <section class="py-12 bg-slate-900 text-white border-y border-slate-800">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal rounded-lg p-6 text-center">
                    <p class="text-2xl font-bold">A tua participação é importante para a associação.</p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <span v-for="item in supportOptions" :key="item" class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-medium">{{ item }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-16">
            <div class="mx-auto max-w-4xl px-5 lg:px-8">
                <div class="reveal mb-8">
                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">FAQ</p>
                    <h2 class="mt-3 text-4xl font-bold text-slate-900">Perguntas frequentes.</h2>
                </div>
                <div class="space-y-3">
                    <article v-for="(faq, index) in faqs" :key="faq[0]" class="reveal rounded-lg border border-slate-200 bg-white overflow-hidden">
                        <button type="button" class="flex w-full items-center justify-between gap-4 p-5 text-left font-semibold text-slate-900 hover:bg-slate-50" @click="openFaq = openFaq === index ? null : index">
                            {{ faq[0] }}
                            <span class="text-xl text-slate-600 flex-shrink-0">{{ openFaq === index ? '−' : '+' }}</span>
                        </button>
                        <p v-if="openFaq === index" class="px-5 pb-5 leading-relaxed text-slate-600 border-t border-slate-200">{{ faq[1] }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="contactos" class="py-16 bg-slate-50 border-y border-slate-200">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 lg:grid-cols-[0.85fr_1.15fr] lg:px-8">
                <div class="reveal">
                    <p class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Contactos</p>
                    <h2 class="mt-3 text-4xl font-bold text-slate-900">Fala connosco.</h2>
                    <div class="mt-6 space-y-3 text-slate-600">
                        <p class="text-lg font-medium">Santana, Carvalhal Benfeito, Caldas da Rainha</p>
                        <p><a :href="`mailto:${contactEmail}`" class="font-medium text-slate-900 hover:underline">{{ contactEmail }}</a></p>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="https://www.facebook.com/ardcsantana" target="_blank" rel="noreferrer" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-800">Facebook</a>
                        <a href="https://www.instagram.com/ardcsantana/" target="_blank" rel="noreferrer" class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white hover:bg-slate-800">Instagram</a>
                    </div>
                </div>

                <div class="reveal overflow-hidden rounded-lg border border-slate-200 shadow-md">
                    <iframe
                        title="Localização da ARDC Santana"
                        class="h-[420px] w-full"
                        loading="lazy"
                        src="https://www.google.com/maps?q=Santana%20Carvalhal%20Benfeito%20Caldas%20da%20Rainha&output=embed"
                    />
                </div>
            </div>
        </section>

        <footer class="bg-slate-900 text-white py-8 border-t border-slate-800">
            <div class="mx-auto grid max-w-7xl gap-6 px-5 md:grid-cols-[1fr_auto] lg:px-8">
                <div>
                    <div class="flex items-center gap-3 font-semibold">
                        <img :src="associationLogo" alt="" class="h-10 w-10 rounded bg-slate-700 object-contain p-2">
                        ARDC Santana
                    </div>
                    <p class="mt-3 text-sm text-slate-400">Juntos mantemos viva a nossa associação.</p>
                </div>
                <div class="flex flex-wrap gap-4 text-sm font-medium md:justify-end">
                    <button v-for="link in navLinks" :key="link[0]" type="button" class="text-slate-400 hover:text-white" @click="scrollTo(link[1])">{{ link[0] }}</button>
                </div>
            </div>
        </footer>

        <Transition name="lightbox">
            <div v-if="lightboxItem" class="fixed inset-0 z-[60] grid place-items-center bg-black/80 p-5" @click.self="lightboxItem = null">
                <div class="max-w-4xl overflow-hidden rounded-lg bg-white">
                    <img :src="lightboxItem.caminho" :alt="lightboxItem.titulo || lightboxItem.event" class="max-h-[76vh] w-full object-contain bg-black">
                    <div class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">{{ lightboxItem.category }}</p>
                            <h3 class="text-lg font-bold text-slate-900">{{ lightboxItem.titulo || lightboxItem.event }}</h3>
                        </div>
                        <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white" @click="lightboxItem = null">Fechar</button>
                    </div>
                </div>
            </div>
        </Transition>
    </main>
</template>

<style scoped>
.section-pad {
    padding-bottom: 5rem;
    padding-top: 5rem;
}

.eyebrow {
    color: #9a7621;
    font-size: 0.75rem;
    font-weight: 900;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}

.section-title {
    color: #17241d;
    font-size: clamp(2.25rem, 5vw, 4rem);
    font-weight: 900;
    letter-spacing: 0;
    line-height: 1;
    margin-top: 0.75rem;
}

.hero-nav-button {
    display: grid;
    height: 2.75rem;
    place-items: center;
    width: 2.75rem;
    border-radius: 999px;
    background: rgb(255 255 255 / 0.88);
    color: #214c38;
    font-size: 2rem;
    font-weight: 900;
    line-height: 1;
    transition: transform 180ms ease, background 180ms ease;
}

.hero-nav-button:hover {
    background: #e5b84b;
    transform: scale(1.06);
}

.menu-icon,
.menu-icon::before,
.menu-icon::after {
    background: currentColor;
    border-radius: 999px;
    content: '';
    display: block;
    height: 2px;
    transition: transform 180ms ease, opacity 180ms ease;
    width: 1.3rem;
}

.menu-icon::before {
    transform: translateY(-7px);
}

.menu-icon::after {
    transform: translateY(5px);
}

.menu-icon.open {
    background: transparent;
}

.menu-icon.open::before {
    transform: translateY(2px) rotate(45deg);
}

.menu-icon.open::after {
    transform: translateY(0) rotate(-45deg);
}

.form-field {
    color: #214c38;
    display: grid;
    font-size: 0.9rem;
    font-weight: 900;
    gap: 0.45rem;
}

.form-field input,
.form-field textarea {
    border: 1px solid #cddac9;
    border-radius: 0.5rem;
    color: #17241d;
    font-weight: 700;
    width: 100%;
}

.form-field input:focus,
.form-field textarea:focus {
    border-color: #214c38;
    box-shadow: 0 0 0 3px rgb(33 76 56 / 0.12);
    outline: none;
}

.form-field span {
    color: #a33a2b;
    font-size: 0.8rem;
}

.reveal {
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 600ms ease, transform 600ms ease;
}

.reveal.is-visible {
    opacity: 1;
    transform: translateY(0);
}

.mobile-menu-enter-active,
.mobile-menu-leave-active,
.lightbox-enter-active,
.lightbox-leave-active,
.hero-bg-enter-active,
.hero-bg-leave-active,
.hero-slide-enter-active,
.hero-slide-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}

.mobile-menu-enter-from,
.mobile-menu-leave-to {
    opacity: 0;
    transform: translateY(-0.75rem);
}

.lightbox-enter-from,
.lightbox-leave-to {
    opacity: 0;
    transform: scale(0.98);
}

.hero-bg-enter-from,
.hero-bg-leave-to,
.hero-slide-enter-from,
.hero-slide-leave-to {
    opacity: 0;
}

.hero-slide-enter-from {
    transform: translateX(1rem) scale(1.02);
}

.hero-slide-leave-to {
    transform: translateX(-1rem) scale(0.98);
}
</style>
