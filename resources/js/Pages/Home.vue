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

    <main class="min-h-screen scroll-smooth bg-[#fbfaf4] text-[#18231d]">
        <header class="fixed inset-x-0 top-0 z-50 border-b border-black/5 bg-[#fbfaf4]/90 backdrop-blur-xl">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3 lg:px-8">
                <Link href="/" class="flex items-center gap-3">
                    <img :src="associationLogo" alt="Logo ARDC Santana" class="h-12 w-12 rounded-md bg-white object-contain p-1 shadow-sm">
                    <span class="text-sm font-black uppercase tracking-[0.18em] text-[#214c38]">ARDC Santana</span>
                </Link>

                <div class="hidden items-center gap-1 md:flex">
                    <button
                        v-for="link in navLinks"
                        :key="link[0]"
                        type="button"
                        class="rounded-md px-4 py-2 text-sm font-bold text-[#335041] transition hover:bg-[#e8efe5]"
                        @click="scrollTo(link[1])"
                    >
                        {{ link[0] }}
                    </button>
                </div>

                <a :href="`mailto:${contactEmail}`" class="hidden rounded-md bg-[#214c38] px-4 py-2 text-sm font-black text-white transition hover:bg-[#173629] lg:inline-flex">
                    Contactar
                </a>

                <button type="button" class="grid h-11 w-11 place-items-center rounded-md bg-[#214c38] text-white md:hidden" aria-label="Abrir menu" @click="menuOpen = !menuOpen">
                    <span class="menu-icon" :class="{ open: menuOpen }" />
                </button>
            </nav>

            <Transition name="mobile-menu">
                <div v-if="menuOpen" class="border-t border-black/5 bg-[#fbfaf4] px-5 py-4 shadow-xl md:hidden">
                    <button
                        v-for="link in navLinks"
                        :key="link[0]"
                        type="button"
                        class="block w-full rounded-md px-3 py-3 text-left font-black text-[#214c38] hover:bg-[#e8efe5]"
                        @click="scrollTo(link[1])"
                    >
                        {{ link[0] }}
                    </button>
                </div>
            </Transition>
        </header>

        <section class="relative isolate overflow-hidden pt-24">
            <Transition name="hero-bg" mode="out-in">
                <img :key="activeHeroEvent?.poster || santaAnaImage" :src="activeHeroEvent?.poster || santaAnaImage" alt="" class="absolute inset-0 h-full w-full object-cover opacity-25" aria-hidden="true">
            </Transition>
            <div class="absolute inset-0 bg-[linear-gradient(90deg,#fbfaf4_0%,rgba(251,250,244,0.94)_43%,rgba(251,250,244,0.72)_100%)]" />

            <div class="relative mx-auto grid min-h-[calc(100vh-6rem)] max-w-7xl items-center gap-10 px-5 pb-16 pt-10 lg:grid-cols-[1.05fr_0.95fr] lg:px-8">
                <div class="reveal max-w-3xl">
                    <p class="inline-flex rounded-md bg-[#e5b84b] px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-[#18231d]">
                        Associação Recreativa, Desportiva e Cultural
                    </p>
                    <h1 class="mt-5 text-5xl font-black leading-[0.95] text-[#17241d] sm:text-7xl lg:text-8xl">
                        ARDC Santana
                    </h1>
                    <p class="mt-5 text-2xl font-black text-[#214c38] sm:text-3xl">
                        Cultura, desporto e comunidade em Santana
                    </p>
                    <p class="mt-6 max-w-2xl text-lg font-semibold leading-relaxed text-[#4b5d52]">
                        Uma associação feita por pessoas, para pessoas. Juntamos gerações através de eventos, convívios, caminhadas, festas e iniciativas locais.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <button type="button" class="rounded-md bg-[#214c38] px-5 py-3 font-black text-white shadow-lg shadow-[#214c38]/20 transition hover:-translate-y-1 hover:bg-[#173629]" @click="scrollTo('#eventos')">
                            Ver próximos eventos
                        </button>
                        <button type="button" class="rounded-md bg-[#e5b84b] px-5 py-3 font-black text-[#18231d] transition hover:-translate-y-1 hover:bg-[#f0c85b]" @click="scrollTo('#socios')">
                            Tornar-me sócio
                        </button>
                        <button type="button" class="rounded-md border border-[#214c38]/25 px-5 py-3 font-black text-[#214c38] transition hover:-translate-y-1 hover:bg-white" @click="scrollTo('#contactos')">
                            Contactar associação
                        </button>
                    </div>

                    <div class="mt-10 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-md border border-[#214c38]/10 bg-white/80 p-4 shadow-sm">
                            <strong class="block text-2xl text-[#214c38]">1991</strong>
                            <span class="text-sm font-bold text-[#647267]">Fundada em 10 de Abril</span>
                        </div>
                        <div class="rounded-md border border-[#214c38]/10 bg-white/80 p-4 shadow-sm">
                            <strong class="block text-2xl text-[#214c38]">Santana</strong>
                            <span class="text-sm font-bold text-[#647267]">Carvalhal Benfeito</span>
                        </div>
                        <div class="rounded-md border border-[#214c38]/10 bg-white/80 p-4 shadow-sm">
                            <strong class="block text-2xl text-[#214c38]">Comunidade</strong>
                            <span class="text-sm font-bold text-[#647267]">Cultura, desporto e convívio</span>
                        </div>
                    </div>
                </div>

                <aside v-if="activeHeroEvent" class="reveal relative">
                    <div class="overflow-hidden rounded-lg bg-[#214c38] shadow-2xl shadow-[#214c38]/25">
                        <div class="relative bg-[#17241d]">
                            <Transition name="hero-slide" mode="out-in">
                                <img :key="activeHeroEvent.poster" :src="activeHeroEvent.poster || associationLogo" :alt="activeHeroEvent.title" class="aspect-[4/3] w-full object-cover">
                            </Transition>

                            <div v-if="heroEvents.length > 1" class="absolute inset-x-3 top-3 flex items-center justify-between">
                                <button type="button" class="hero-nav-button" aria-label="Evento anterior" @click="previousHeroSlide">‹</button>
                                <button type="button" class="hero-nav-button" aria-label="Evento seguinte" @click="nextHeroSlide">›</button>
                            </div>
                        </div>

                        <div class="p-5 text-white">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-[#f0c85b]">{{ activeHeroEvent.badge || 'Próximo evento' }}</p>
                            <h2 class="mt-2 text-3xl font-black">{{ activeHeroEvent.title }}</h2>
                            <p class="mt-2 font-bold text-white/75">{{ activeHeroEvent.date }} · {{ activeHeroEvent.location || activeHeroEvent.subtitle }}</p>
                            <p class="mt-4 leading-relaxed text-white/85">{{ activeHeroEvent.description }}</p>

                            <div class="mt-5 flex flex-wrap gap-2">
                                <Link v-if="activeHeroEvent.id" :href="eventHref(activeHeroEvent)" class="rounded-md bg-[#e5b84b] px-4 py-2 text-sm font-black text-[#18231d]">
                                    Ver evento
                                </Link>
                                <button type="button" class="rounded-md border border-white/20 px-4 py-2 text-sm font-black text-white hover:bg-white hover:text-[#214c38]" @click="scrollTo('#eventos')">
                                    Ver agenda
                                </button>
                            </div>

                            <div v-if="heroEvents.length > 1" class="mt-5 flex gap-2">
                                <button
                                    v-for="(event, index) in heroEvents"
                                    :key="event.id || event.title"
                                    type="button"
                                    class="h-2.5 flex-1 rounded-full transition"
                                    :class="activeHeroSlide === index ? 'bg-[#e5b84b]' : 'bg-white/25 hover:bg-white/50'"
                                    :aria-label="`Mostrar evento ${index + 1}`"
                                    @click="selectHeroSlide(index)"
                                />
                            </div>
                        </div>
                    </div>
                </aside>

                <aside v-else class="reveal rounded-lg border border-[#d8e2d5] bg-white/80 p-6 shadow-xl shadow-black/10">
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-[#9a7621]">Próximos eventos</p>
                    <h2 class="mt-2 text-3xl font-black text-[#17241d]">Ainda não há próximos eventos publicados.</h2>
                    <p class="mt-3 leading-relaxed text-[#536458]">Quando forem criados e publicados no backoffice, aparecem aqui automaticamente em formato slider.</p>
                    <button type="button" class="mt-5 rounded-md bg-[#214c38] px-4 py-2 font-black text-white" @click="scrollTo('#eventos')">
                        Ver agenda
                    </button>
                </aside>
            </div>
        </section>

        <section id="sobre" class="section-pad bg-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div class="reveal">
                    <p class="eyebrow">Sobre nós</p>
                    <h2 class="section-title">Uma casa local com memória, palco e futuro.</h2>
                    <p class="mt-5 text-lg leading-relaxed text-[#536458]">
                        A ARDC Santana é uma associação recreativa, desportiva e cultural. Nasceu da vontade de criar um ponto de encontro para a terra e continua a ser uma casa aberta para sócios, vizinhos, famílias e amigos.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <article v-for="pillar in pillars" :key="pillar[0]" class="reveal rounded-lg border border-[#dfe7dc] bg-[#fbfaf4] p-5 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-black/10">
                        <div class="mb-5 grid h-12 w-12 place-items-center rounded-md bg-[#214c38] text-xl font-black text-white">{{ pillar[0].slice(0, 1) }}</div>
                        <h3 class="text-2xl font-black text-[#17241d]">{{ pillar[0] }}</h3>
                        <p class="mt-3 leading-relaxed text-[#5a695f]">{{ pillar[1] }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="eventos" class="section-pad bg-[#eef4ea]">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal mb-8 flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <p class="eyebrow">Próximos eventos</p>
                        <h2 class="section-title">O que está a acontecer na associação.</h2>
                    </div>
                    <div v-if="eventTabs.length" class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in eventTabs"
                            :key="tab.key"
                            type="button"
                            class="rounded-md border px-4 py-2 text-sm font-black transition"
                            :class="selectedEventTab === tab.key ? 'border-[#214c38] bg-[#214c38] text-white' : 'border-[#cddac9] bg-white text-[#335041] hover:border-[#214c38]'"
                            @click="selectedEventTab = tab.key"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <div v-if="filteredEvents.length" class="grid gap-5 lg:grid-cols-3">
                    <article v-for="event in filteredEvents" :key="event.id || event.title" class="group overflow-hidden rounded-lg border border-[#d8e2d5] bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-2xl hover:shadow-[#214c38]/10">
                        <img :src="event.poster || associationLogo" :alt="event.title" class="aspect-[4/3] w-full bg-[#214c38] object-cover transition duration-700 group-hover:scale-105">
                        <div class="p-5">
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <span class="rounded bg-[#e5b84b] px-3 py-1 text-xs font-black uppercase tracking-[0.14em] text-[#18231d]">{{ event.badge || 'Evento' }}</span>
                                <span class="font-black text-[#214c38]">{{ event.date || event.period }}</span>
                            </div>
                            <h3 class="text-2xl font-black text-[#17241d]">{{ event.title }}</h3>
                            <p class="mt-1 font-bold text-[#67756b]">{{ event.location || event.subtitle || 'ARDC Santana' }}</p>
                            <p class="mt-4 leading-relaxed text-[#536458]">{{ event.description }}</p>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <a :href="calendarHref(event)" target="_blank" rel="noreferrer" class="rounded-md bg-[#214c38] px-3 py-2 text-sm font-black text-white">Adicionar ao calendário</a>
                                <button type="button" class="rounded-md border border-[#214c38]/20 px-3 py-2 text-sm font-black text-[#214c38]" @click="scrollTo('#socios')">Inscrever-me</button>
                                <Link v-if="event.id" :href="eventHref(event)" class="rounded-md border border-[#214c38]/20 px-3 py-2 text-sm font-black text-[#214c38]">Saber mais</Link>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="rounded-lg border border-[#d8e2d5] bg-white p-6 text-[#536458]">
                    Ainda não existem eventos publicados no backoffice para mostrar nesta área.
                </div>
            </div>
        </section>

        <section class="section-pad bg-[#214c38] text-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div class="reveal">
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-[#f0c85b]">Encontra o evento ideal</p>
                    <h2 class="mt-3 text-4xl font-black sm:text-5xl">O que procuras?</h2>
                    <div class="mt-6 flex flex-wrap gap-2">
                        <button
                            v-for="option in quizOptions"
                            :key="option"
                            type="button"
                            class="rounded-md border px-4 py-2 text-sm font-black transition"
                            :class="selectedNeed === option ? 'border-[#f0c85b] bg-[#f0c85b] text-[#18231d]' : 'border-white/20 bg-white/10 text-white hover:bg-white/20'"
                            @click="selectedNeed = option"
                        >
                            {{ option }}
                        </button>
                    </div>
                </div>

                <div v-if="recommendedEvents.length" class="grid gap-3">
                    <article v-for="event in recommendedEvents" :key="event.id || event.title" class="rounded-lg bg-white p-4 text-[#17241d]">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-[#9a7621]">{{ event.badge || 'Recomendado' }}</p>
                        <h3 class="mt-1 text-xl font-black">{{ event.title }}</h3>
                        <p class="mt-1 text-sm font-bold text-[#647267]">{{ event.date }} · {{ event.location || event.subtitle }}</p>
                    </article>
                </div>
                <div v-else class="rounded-lg bg-white p-4 font-bold text-[#536458]">
                    Publica eventos no backoffice para ativar recomendações automáticas nesta área.
                </div>
            </div>
        </section>

        <section id="galeria" class="section-pad bg-white">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal mb-8">
                    <p class="eyebrow">Galeria</p>
                    <h2 class="section-title">Caminhadas, festas, convívios e momentos especiais.</h2>
                </div>

                <div v-if="galleryItems.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="item in galleryItems"
                        :key="`${item.caminho}-${item.titulo}`"
                        type="button"
                        class="group relative overflow-hidden rounded-lg bg-[#214c38] text-left"
                        @click="lightboxItem = item"
                    >
                        <img :src="item.caminho" :alt="item.titulo || item.event" class="aspect-[4/3] w-full object-cover transition duration-700 group-hover:scale-105">
                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-4 pt-16 text-white">
                            <span class="block text-xs font-black uppercase tracking-[0.16em] text-[#f0c85b]">{{ item.category }}</span>
                            <span class="mt-1 block text-lg font-black">{{ item.titulo || item.event }}</span>
                        </span>
                    </button>
                </div>

                <div v-else class="rounded-lg border border-[#dfe7dc] bg-[#fbfaf4] p-6 text-[#536458]">
                    Ainda não existem fotografias ou cartazes publicados no backoffice.
                </div>
            </div>
        </section>

        <section id="arquivo-fotos" class="section-pad bg-[#fbfaf4]">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal mb-8">
                    <p class="eyebrow">Eventos anteriores</p>
                    <h2 class="section-title">Fotografias adicionadas pelo backoffice.</h2>
                </div>

                <div v-if="pastPhotoItems.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <button
                        v-for="item in pastPhotoItems"
                        :key="`${item.caminho}-${item.event}`"
                        type="button"
                        class="group relative overflow-hidden rounded-lg bg-[#214c38] text-left"
                        @click="lightboxItem = item"
                    >
                        <img :src="item.caminho" :alt="item.titulo || item.event" class="aspect-square w-full object-cover transition duration-700 group-hover:scale-105">
                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 to-transparent p-3 pt-14 text-white">
                            <span class="block text-xs font-black uppercase tracking-[0.14em] text-[#f0c85b]">{{ item.date }}</span>
                            <span class="mt-1 block truncate font-black">{{ item.event }}</span>
                        </span>
                    </button>
                </div>

                <div v-else class="rounded-lg border border-[#dfe7dc] bg-white p-6 text-[#536458]">
                    Ainda não existem fotografias de eventos anteriores publicadas no backoffice.
                </div>
            </div>
        </section>

        <section id="socios" class="section-pad bg-[#fbfaf4]">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                <div class="reveal">
                    <p class="eyebrow">Torna-te sócio</p>
                    <h2 class="section-title">Faz parte da vida da associação.</h2>
                    <p class="mt-5 text-lg leading-relaxed text-[#536458]">
                        Ser sócio é participar, apoiar a manutenção da associação, ter acesso a iniciativas e ajudar a manter viva esta casa comunitária.
                    </p>
                    <div class="mt-6 grid gap-3">
                        <div v-for="item in supportOptions" :key="item" class="rounded-md border border-[#dfe7dc] bg-white p-4 font-black text-[#214c38]">{{ item }}</div>
                    </div>
                </div>

                <form class="reveal rounded-lg bg-white p-5 shadow-xl shadow-black/10" novalidate @submit.prevent="validateForm">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="form-field">
                            Nome
                            <input v-model="form.name" type="text" autocomplete="name">
                            <span v-if="errors.name">{{ errors.name }}</span>
                        </label>
                        <label class="form-field">
                            Email
                            <input v-model="form.email" type="email" autocomplete="email">
                            <span v-if="errors.email">{{ errors.email }}</span>
                        </label>
                        <label class="form-field sm:col-span-2">
                            Telefone
                            <input v-model="form.phone" type="tel" autocomplete="tel">
                            <span v-if="errors.phone">{{ errors.phone }}</span>
                        </label>
                        <label class="form-field sm:col-span-2">
                            Mensagem
                            <textarea v-model="form.message" rows="5" />
                            <span v-if="errors.message">{{ errors.message }}</span>
                        </label>
                    </div>
                    <button type="submit" class="mt-5 w-full rounded-md bg-[#214c38] px-5 py-3 font-black text-white transition hover:bg-[#173629] disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing">
                        {{ form.processing ? 'A enviar...' : 'Quero ser sócio' }}
                    </button>
                    <p v-if="formSent" class="mt-4 rounded-md bg-[#eef4ea] p-3 text-sm font-bold text-[#214c38]">
                        Obrigado. A tua mensagem foi enviada para a associação e receberás uma confirmação por email.
                    </p>
                </form>
            </div>
        </section>

        <section class="bg-[#17241d] py-14 text-white">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="reveal rounded-lg border border-white/10 p-6 md:p-8">
                    <p class="text-2xl font-black md:text-4xl">A tua participação ajuda a manter viva a nossa associação.</p>
                    <div class="mt-6 flex flex-wrap gap-2">
                        <span v-for="item in supportOptions" :key="item" class="rounded-md bg-white/10 px-4 py-2 text-sm font-black text-white">{{ item }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-pad bg-white">
            <div class="mx-auto max-w-4xl px-5 lg:px-8">
                <div class="reveal mb-8">
                    <p class="eyebrow">Perguntas frequentes</p>
                    <h2 class="section-title">Respostas rápidas para participar melhor.</h2>
                </div>
                <div class="space-y-3">
                    <article v-for="(faq, index) in faqs" :key="faq[0]" class="reveal rounded-lg border border-[#dfe7dc]">
                        <button type="button" class="flex w-full items-center justify-between gap-4 p-4 text-left font-black text-[#17241d]" @click="openFaq = openFaq === index ? null : index">
                            {{ faq[0] }}
                            <span class="text-2xl text-[#214c38]">{{ openFaq === index ? '-' : '+' }}</span>
                        </button>
                        <p v-if="openFaq === index" class="px-4 pb-4 leading-relaxed text-[#536458]">{{ faq[1] }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="contactos" class="section-pad bg-[#eef4ea]">
            <div class="mx-auto grid max-w-7xl gap-8 px-5 lg:grid-cols-[0.85fr_1.15fr] lg:px-8">
                <div class="reveal">
                    <p class="eyebrow">Contactos</p>
                    <h2 class="section-title">Fala connosco ou passa pela associação.</h2>
                    <div class="mt-6 space-y-3 text-lg font-bold text-[#536458]">
                        <p>Santana, Carvalhal Benfeito, Caldas da Rainha</p>
                        <p><a :href="`mailto:${contactEmail}`" class="text-[#214c38]">{{ contactEmail }}</a></p>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="https://www.facebook.com/ardcsantana" target="_blank" rel="noreferrer" class="rounded-md bg-[#214c38] px-4 py-2 font-black text-white">Facebook</a>
                        <a href="https://www.instagram.com/ardcsantana/" target="_blank" rel="noreferrer" class="rounded-md bg-[#e5b84b] px-4 py-2 font-black text-[#18231d]">Instagram</a>
                    </div>
                </div>

                <div class="reveal overflow-hidden rounded-lg border border-[#d8e2d5] bg-white shadow-xl shadow-black/10">
                    <iframe
                        title="Localização da ARDC Santana"
                        class="h-[420px] w-full"
                        loading="lazy"
                        src="https://www.google.com/maps?q=Santana%20Carvalhal%20Benfeito%20Caldas%20da%20Rainha&output=embed"
                    />
                </div>
            </div>
        </section>

        <footer class="bg-[#17241d] py-8 text-white">
            <div class="mx-auto grid max-w-7xl gap-6 px-5 md:grid-cols-[1fr_auto] lg:px-8">
                <div>
                    <div class="flex items-center gap-3 font-black">
                        <img :src="associationLogo" alt="" class="h-11 w-11 rounded-md bg-white object-contain p-1">
                        ARDC Santana
                    </div>
                    <p class="mt-3 text-sm font-semibold text-white/70">Juntos mantemos viva a nossa associação.</p>
                </div>
                <div class="flex flex-wrap gap-3 text-sm font-black md:justify-end">
                    <button v-for="link in navLinks" :key="link[0]" type="button" class="text-white/75 hover:text-[#f0c85b]" @click="scrollTo(link[1])">{{ link[0] }}</button>
                </div>
            </div>
        </footer>

        <Transition name="lightbox">
            <div v-if="lightboxItem" class="fixed inset-0 z-[60] grid place-items-center bg-black/80 p-5" @click.self="lightboxItem = null">
                <div class="max-w-4xl overflow-hidden rounded-lg bg-white">
                    <img :src="lightboxItem.caminho" :alt="lightboxItem.titulo || lightboxItem.event" class="max-h-[76vh] w-full object-contain bg-black">
                    <div class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-[#9a7621]">{{ lightboxItem.category }}</p>
                            <h3 class="text-xl font-black text-[#17241d]">{{ lightboxItem.titulo || lightboxItem.event }}</h3>
                        </div>
                        <button type="button" class="rounded-md bg-[#214c38] px-4 py-2 font-black text-white" @click="lightboxItem = null">Fechar</button>
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
