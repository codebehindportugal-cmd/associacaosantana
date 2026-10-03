<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useRecaptcha } from '@/Composables/useRecaptcha';
import { computed, ref } from 'vue';
import CookieBanner from '@/Components/CookieBanner.vue';
import SponsorsSlider from '@/Components/SponsorsSlider.vue';

const props = defineProps({
    upcomingEvents: Array,
    pastEvents: Array,
    patrocinadores: Array,
});

const associationLogo = '/images/santana-logo.png';
const heroImage = '/images/edificio-entrada.jpg';
const groupImage = '/images/grupo-recortado.jpg';
const contactEmail = 'ardcsantana@outlook.com';
const currentYear = new Date().getFullYear();

// Preencher com os valores reais.
const stats = { fundada: '19—', socios: '+300', eventos: '20+' };

const fallbackShots = ['/images/edificio-parque.jpg', '/images/edificio-capela.jpg', '/images/edificio-fachada.jpg', heroImage];

const menuOpen = ref(false);
const formSent = ref(false);
const errors = ref({});
const form = useForm({ name: '', email: '', phone: '', message: '', recaptcha_token: '' });
const { obterToken } = useRecaptcha();

const upcoming = computed(() => props.upcomingEvents ?? []);
const allEvents = computed(() => [...(props.upcomingEvents ?? []), ...(props.pastEvents ?? [])]);
const featured = computed(() => upcoming.value[0] ?? allEvents.value[0] ?? null);
const gridEvents = computed(() => {
    const featId = featured.value?.id;
    const list = upcoming.value.length > 1 ? upcoming.value.slice(1, 4) : allEvents.value.filter((e) => !featId || e.id !== featId).slice(0, 3);
    return list;
});
const temPatrocinios = computed(() => (props.patrocinadores ?? []).length > 0);

const galeria = computed(() => {
    const fromMedia = allEvents.value.flatMap((e) => (e.media ?? []).filter((m) => m.tipo === 'foto').map((m) => m.miniatura || m.caminho));
    const fromPosters = allEvents.value.filter((e) => e.poster).map((e) => e.poster);
    const imgs = [...new Set([...fromMedia, ...fromPosters])];
    const base = imgs.length ? imgs : [];
    const filled = [...base, groupImage, ...fallbackShots];
    return [...new Set(filled)].slice(0, 6);
});

const navLinks = [
    ['A Casa', '#casa'], ['Eventos', '#eventos'], ['Salão', '#salao'],
    ['Festa', '#festa'], ['Comunidade', '#comunidade'], ['Galeria', '#galeria'], ['Contacto', '#contacto'],
];
const pillars = [
    { icon: 'cultura', label: 'Cultura', text: 'Festas, tradições e os momentos que contam a história de Santana, de geração em geração.' },
    { icon: 'desporto', label: 'Desporto', text: 'Oportunidades para mexer, caminhar e participar — juntando idades e famílias.' },
    { icon: 'convivio', label: 'Convívio', text: 'O salão e o largo: uma casa aberta a sócios, amigos e visitantes o ano inteiro.' },
];

function scrollTo(target) {
    menuOpen.value = false;
    if (!target.startsWith('#')) { window.location.href = target; return; }
    document.querySelector(target)?.scrollIntoView({ behavior: 'smooth' });
}
function eventHref(event) {
    try { return event && event.id ? route('eventos.public.show', event.id) : '#eventos'; }
    catch (e) { return '#eventos'; }
}
function r(name, fallback) {
    try { return typeof route === 'function' ? route(name) : fallback; }
    catch (e) { return fallback; }
}
function posterFor(event, i) {
    return event && event.poster ? event.poster : fallbackShots[i % fallbackShots.length];
}

// (revelação por scroll é feita em CSS puro — sem IntersectionObserver)

async function submitForm() {
    errors.value = {};
    if (!form.name.trim()) errors.value.name = 'Indica o teu nome.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) errors.value.email = 'Indica um email válido.';
    if (!form.phone.trim()) errors.value.phone = 'Indica um telefone.';
    if (form.message.trim().length < 8) errors.value.message = 'Escreve uma mensagem curta.';
    if (Object.keys(errors.value).length) return;
    form.recaptcha_token = await obterToken('contacto');
    form.post(route('contacto.store'), {
        preserveScroll: true,
        onSuccess: () => { formSent.value = true; form.reset(); },
        onError: () => { errors.value = form.errors; },
    });
}

</script>

<template>
    <Head title="ARDC Santana | Associação Recreativa, Desportiva e Cultural">
        <meta head-key="description" name="description" content="A casa de todos em Santana — recreio, desporto e cultura. Eventos, aluguer do salão, festa e comunidade.">
        <link head-key="preload-hero" rel="preload" as="image" :href="heroImage" fetchpriority="high">
    </Head>

    <div class="santana min-h-screen bg-fundo font-sans leading-normal text-tinta tabular-nums">
        <!-- Barra superior -->
        <div class="bg-escuro text-[13px] text-escuro-inativo">
            <div class="mx-auto flex h-9 w-full max-w-[1120px] items-center justify-between gap-4 px-4 sm:px-6">
                <span class="truncate">Santana · Carvalhal Benfeito · Caldas da Rainha</span>
                <span class="hidden shrink-0 sm:inline"><Link :href="r('salao.pre-reserva', '/reserva-salao')" class="font-bold text-white no-underline hover:text-verde-claro2">Reserve o salão online</Link> · @ardcsantana</span>
            </div>
        </div>

        <!-- Menu próprio da Home -->
        <header class="sticky top-0 z-40 border-b border-linha bg-white">
            <div class="mx-auto flex h-[72px] w-full max-w-[1280px] items-center justify-between gap-4 px-4 sm:px-6">
                <a class="flex shrink-0 items-center gap-3 whitespace-nowrap text-tinta no-underline" href="#inicio" @click.prevent="scrollTo('#inicio')">
                    <img :src="associationLogo" alt="ARDC Santana" class="h-11 w-11 rounded-full border border-linha bg-white object-contain p-1" />
                    <span class="flex flex-col leading-tight">
                        <b class="text-lg font-extrabold">ARDC Santana</b>
                        <small class="text-[11px] font-semibold uppercase tracking-[0.12em] text-suave-2">Recreio · Desporto · Cultura</small>
                    </span>
                </a>
                <nav aria-label="Principal" class="hidden items-center gap-0.5 min-[1281px]:flex">
                    <a v-for="link in navLinks" :key="link[0]" :href="link[1]" class="flex h-11 items-center whitespace-nowrap rounded-[10px] px-3 text-[15px] font-semibold text-suave no-underline transition hover:bg-fundo hover:text-tinta" @click.prevent="scrollTo(link[1])">{{ link[0] }}</a>
                    <Link class="ml-2 inline-flex h-11 items-center whitespace-nowrap rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta no-underline transition hover:border-verde hover:text-verde" :href="r('salao.pre-reserva', '/reserva-salao')">Reservar salão</Link>
                    <Link class="ml-2 inline-flex h-11 items-center whitespace-nowrap rounded-[10px] bg-verde px-4 text-[15px] font-bold text-white no-underline transition hover:bg-verde-escuro hover:text-white" :href="r('patrocinios.index', '/patrocinios')">Ser sócio</Link>
                </nav>
                <button type="button" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha text-tinta min-[1281px]:hidden" :aria-expanded="menuOpen" aria-label="Menu" @click="menuOpen = !menuOpen">
                    <svg v-if="!menuOpen" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                    <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>
            <Transition name="drop">
                <div v-if="menuOpen" class="border-t border-linha-fraca bg-white min-[1281px]:hidden">
                    <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-1 px-4 pb-5 pt-3 sm:px-6">
                        <a v-for="link in navLinks" :key="link[0]" :href="link[1]" class="flex h-11 items-center rounded-[10px] px-3 text-base font-semibold text-tinta no-underline hover:bg-fundo" @click.prevent="scrollTo(link[1])">{{ link[0] }}</a>
                        <Link class="mt-2 flex h-12 items-center justify-center rounded-[10px] border border-linha-forte bg-white text-base font-bold text-tinta no-underline" :href="r('salao.pre-reserva', '/reserva-salao')">Reservar o salão</Link>
                        <Link class="flex h-12 items-center justify-center rounded-[10px] bg-verde text-base font-bold text-white no-underline hover:bg-verde-escuro hover:text-white" :href="r('patrocinios.index', '/patrocinios')">Ser sócio</Link>
                    </div>
                </div>
            </Transition>
        </header>

        <main>
            <!-- HERO -->
            <section id="inicio" class="border-b border-linha bg-white pb-10 pt-10 sm:pt-14">
                <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-10 px-4 sm:px-6">
                    <div class="grid items-center gap-8 md:grid-cols-2 md:gap-12">
                        <div class="flex flex-col gap-5">
                            <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Desde sempre, a casa da aldeia</p>
                            <h1 class="m-0 text-[clamp(40px,5.4vw,64px)] font-extrabold leading-[1.02] tracking-[-0.02em]">O coração <span class="text-verde">de Santana</span></h1>
                            <p class="m-0 max-w-[34rem] text-lg text-suave sm:text-[19px]">Recreio, desporto e cultura numa casa aberta a sócios, famílias e visitantes — onde a aldeia se encontra, festeja e cuida das suas tradições.</p>
                            <div class="mt-1 flex flex-wrap gap-3">
                                <a class="inline-flex h-[54px] items-center gap-2.5 rounded-[10px] bg-verde px-6 text-[17px] font-bold text-white no-underline transition hover:bg-verde-escuro hover:text-white" href="#eventos" @click.prevent="scrollTo('#eventos')">
                                    Ver eventos
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                                </a>
                                <Link class="inline-flex h-[54px] items-center rounded-[10px] border border-linha-forte bg-white px-6 text-[17px] font-bold text-tinta no-underline transition hover:border-verde hover:text-verde" :href="r('salao.pre-reserva', '/reserva-salao')">Reservar o salão</Link>
                            </div>
                        </div>
                        <div class="hero-frame relative h-[280px] overflow-hidden rounded-[14px] bg-linha sm:h-[420px]">
                            <img :src="heroImage" alt="" class="hero-img absolute inset-0 h-full w-full object-cover" fetchpriority="high" decoding="async" />
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="flex items-start gap-3.5 rounded-[14px] border border-linha bg-fundo px-5 py-[18px]">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] bg-verde-claro text-verde">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                            </span>
                            <div class="min-w-0">
                                <div class="text-xs font-bold uppercase tracking-[0.08em] text-suave-2">Próximo evento</div>
                                <div class="mt-0.5 text-lg font-extrabold">{{ featured ? featured.title : 'Em breve' }}</div>
                                <div class="text-sm text-suave">{{ featured && featured.date ? featured.date : 'Segue @ardcsantana' }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3.5 rounded-[14px] border border-linha bg-fundo px-5 py-[18px]">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] bg-verde-claro text-verde">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" /></svg>
                            </span>
                            <div>
                                <div class="text-xs font-bold uppercase tracking-[0.08em] text-suave-2">Onde estamos</div>
                                <div class="mt-0.5 text-lg font-extrabold">Santana</div>
                                <div class="text-sm text-suave">Carvalhal Benfeito</div>
                            </div>
                        </div>
                        <Link class="flex items-start gap-3.5 rounded-[14px] border border-verde bg-verde px-5 py-[18px] text-white no-underline transition hover:bg-verde-escuro hover:text-white" :href="r('salao.pre-reserva', '/reserva-salao')">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] bg-verde-escuro text-white">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-7 9 7" /><path d="M5 10v10h14V10" /><path d="M10 20v-6h4v6" /></svg>
                            </span>
                            <div>
                                <div class="text-xs font-bold uppercase tracking-[0.08em] text-verde-claro2">Aluguer do salão</div>
                                <div class="mt-0.5 text-lg font-extrabold">Reservar →</div>
                                <div class="text-sm text-verde-claro2">pedido de pré-reserva online</div>
                            </div>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- A CASA -->
            <section id="casa" class="py-16 sm:py-[88px]">
                <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-10 px-4 sm:px-6">
                    <div class="reveal flex flex-col gap-2.5">
                        <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">A nossa casa</p>
                        <h2 class="m-0 text-[clamp(30px,3.6vw,42px)] font-extrabold leading-[1.1]">Um lugar para viver a aldeia</h2>
                        <p class="m-0 text-lg text-suave">Três valências, uma só casa. É aqui que a comunidade se junta o ano inteiro.</p>
                    </div>
                    <div class="grid gap-5 md:grid-cols-3">
                        <div v-for="p in pillars" :key="p.label" class="reveal flex flex-col gap-3 rounded-[14px] border border-linha bg-white p-7 transition hover:-translate-y-1 hover:shadow-[0_12px_32px_rgba(22,32,28,.08)]">
                            <span class="flex h-14 w-14 items-center justify-center rounded-[14px] bg-verde-claro text-verde">
                                <svg v-if="p.icon === 'cultura'" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13" /><circle cx="6" cy="18" r="3" /><circle cx="18" cy="16" r="3" /></svg>
                                <svg v-else-if="p.icon === 'desporto'" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="4" /><circle cx="12" cy="12" r="1" /></svg>
                                <svg v-else width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3" /><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" /><circle cx="17" cy="9" r="2.5" /><path d="M16 14.2c2.8.3 5 2.7 5 5.8" /></svg>
                            </span>
                            <h3 class="m-0 mt-1 text-2xl font-extrabold">{{ p.label }}</h3>
                            <p class="m-0 text-base text-suave">{{ p.text }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- EVENTOS -->
            <section id="eventos" class="border-y border-linha bg-white py-16 sm:py-[88px]">
                <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-7 px-4 sm:px-6">
                    <div class="reveal flex flex-col gap-2.5">
                        <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Em destaque</p>
                        <h2 class="m-0 text-[clamp(30px,3.6vw,42px)] font-extrabold leading-[1.1]">Próximos eventos</h2>
                    </div>

                    <article v-if="featured" class="reveal grid overflow-hidden rounded-[14px] border border-linha bg-white md:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)]">
                        <a class="relative block min-h-[260px] bg-linha bg-cover bg-center sm:min-h-[340px]" :href="eventHref(featured)" :aria-label="featured.title" :style="{ backgroundImage: `url(${featured.poster || groupImage})` }">
                            <span v-if="featured.date" class="absolute left-5 top-5 flex h-9 items-center rounded-[10px] bg-verde px-3.5 text-[15px] font-extrabold text-white">{{ featured.date }}</span>
                        </a>
                        <div class="flex flex-col justify-center gap-3.5 p-6 sm:p-10">
                            <span class="flex h-7 items-center self-start rounded-full bg-verde-claro px-3 text-[13px] font-bold uppercase tracking-[0.06em] text-verde-escuro">{{ featured.badge || 'Evento' }}</span>
                            <h3 class="m-0 text-[28px] font-extrabold leading-[1.1] sm:text-[34px]">{{ featured.title }}</h3>
                            <p class="m-0 text-[17px] text-suave">{{ featured.description || featured.subtitle || 'Junta-te a nós na próxima iniciativa da associação.' }}</p>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <a class="inline-flex h-[52px] items-center gap-2.5 rounded-[10px] bg-verde px-[22px] text-base font-bold text-white no-underline transition hover:bg-verde-escuro hover:text-white" :href="eventHref(featured)">
                                    Ver evento
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                                </a>
                                <a v-if="featured.externalUrl" class="inline-flex h-[52px] items-center gap-2 rounded-[10px] border border-verde bg-white px-[22px] text-base font-bold text-verde no-underline transition hover:bg-verde-claro" :href="featured.externalUrl" target="_blank" rel="noopener noreferrer">
                                    {{ featured.externalLabel || 'Inscrições' }}
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5" /></svg>
                                </a>
                            </div>
                        </div>
                    </article>

                    <div v-if="gridEvents.length" class="grid gap-5 md:grid-cols-3">
                        <a v-for="(event, i) in gridEvents" :key="event.id || event.title" class="reveal flex flex-col overflow-hidden rounded-[14px] border border-linha bg-white text-tinta no-underline transition hover:-translate-y-1 hover:border-verde hover:text-tinta" :href="eventHref(event)">
                            <div class="relative h-[180px] bg-linha bg-cover bg-center" :style="{ backgroundImage: `url(${posterFor(event, i)})` }">
                                <span v-if="event.date" class="absolute left-3.5 top-3.5 flex h-[30px] items-center rounded-lg bg-white px-3 text-[13px] font-extrabold text-verde-escuro shadow">{{ event.date }}</span>
                            </div>
                            <div class="flex grow flex-col gap-1.5 p-[22px]">
                                <span class="text-xs font-bold uppercase tracking-[0.08em] text-suave-2">{{ event.badge || 'Evento' }}</span>
                                <h3 class="m-0 text-[21px] font-extrabold">{{ event.title }}</h3>
                                <p v-if="event.location || event.subtitle" class="m-0 mb-2 grow text-[15px] text-suave">{{ event.location || event.subtitle }}</p>
                                <span class="mt-auto flex items-center gap-1.5 text-[15px] font-bold text-verde">
                                    Saber mais
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                                </span>
                            </div>
                        </a>
                    </div>
                    <p v-if="!featured" class="m-0 text-[17px] text-suave">Novos eventos em breve. Segue-nos em <b class="text-tinta">@ardcsantana</b>.</p>
                </div>
            </section>

            <!-- SALÃO + FESTA -->
            <section id="salao" class="py-16 sm:py-[88px]">
                <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-8 px-4 sm:px-6">
                    <div class="reveal flex flex-col gap-2.5">
                        <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Os nossos espaços</p>
                        <h2 class="m-0 text-[clamp(30px,3.6vw,42px)] font-extrabold leading-[1.1]">Feitos para receber</h2>
                    </div>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="reveal flex flex-col overflow-hidden rounded-[14px] border border-linha bg-white">
                            <img :src="heroImage" alt="" loading="lazy" decoding="async" class="h-[220px] w-full object-cover object-left" />
                            <div class="flex grow flex-col gap-2.5 p-7">
                                <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">O salão</p>
                                <h3 class="m-0 text-[28px] font-extrabold">Reserve o salão</h3>
                                <p class="m-0 mb-2.5 grow text-base text-suave">Casamentos, batizados, aniversários e convívios. Um espaço amplo, no coração da aldeia, pronto a receber a sua festa.</p>
                                <Link class="inline-flex h-[52px] items-center self-start rounded-[10px] bg-verde px-[22px] text-base font-bold text-white no-underline transition hover:bg-verde-escuro hover:text-white" :href="r('salao.pre-reserva', '/reserva-salao')">Pedir pré-reserva</Link>
                            </div>
                        </div>
                        <div id="festa" class="reveal flex flex-col overflow-hidden rounded-[14px] border border-linha bg-white">
                            <img :src="heroImage" alt="" loading="lazy" decoding="async" class="h-[220px] w-full object-cover object-right" />
                            <div class="flex grow flex-col gap-2.5 p-7">
                                <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-laranja">Festa de Santana</p>
                                <h3 class="m-0 text-[28px] font-extrabold">Os dias da festa</h3>
                                <p class="m-0 mb-2.5 grow text-base text-suave">Durante a festa, o restaurante e o bar da associação servem a aldeia e quem nos visita. Consulte os preços praticados na festa.</p>
                                <Link class="inline-flex h-[52px] items-center self-start rounded-[10px] border border-linha-forte bg-white px-[22px] text-base font-bold text-tinta no-underline transition hover:border-verde hover:text-verde" :href="r('precario', '/precario')">Ver preçário da festa</Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CITAÇÃO -->
            <section class="bg-verde py-16 text-center text-white sm:py-[72px]">
                <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                    <blockquote class="reveal mx-auto my-0 max-w-[40rem] text-[clamp(30px,4vw,44px)] font-extrabold leading-[1.15] tracking-[-0.01em]">“A casa de todos nós.”</blockquote>
                    <div class="mt-4 text-[13px] font-bold uppercase tracking-[0.14em] text-verde-claro2">O espírito da ARDC Santana</div>
                </div>
            </section>

            <!-- COMUNIDADE -->
            <section id="comunidade" class="py-16 sm:py-[88px]">
                <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                    <div class="grid items-center gap-10 md:grid-cols-2 md:gap-14">
                        <div class="reveal overflow-hidden rounded-[14px] bg-linha">
                            <img :src="groupImage" alt="Sócios da ARDC Santana" loading="lazy" decoding="async" class="block w-full" />
                        </div>
                        <div class="reveal flex flex-col gap-3.5">
                            <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">A comunidade</p>
                            <h2 class="m-0 text-[clamp(30px,3.6vw,42px)] font-extrabold leading-[1.1]">Feita por gente da terra</h2>
                            <p class="m-0 text-[17px] text-suave">A Associação Recreativa, Desportiva e Cultural de Santana nasceu do desejo de ter um sítio nosso — para conviver, festejar e cuidar das tradições. Hoje continua a ser isso mesmo: a casa de todos.</p>
                            <div class="mt-3 grid grid-cols-3 gap-3">
                                <div class="rounded-[14px] border border-linha bg-white p-4"><div class="text-[26px] font-extrabold leading-none text-verde sm:text-[32px]">{{ stats.fundada }}</div><div class="mt-1.5 text-sm text-suave-2">Fundada em</div></div>
                                <div class="rounded-[14px] border border-linha bg-white p-4"><div class="text-[26px] font-extrabold leading-none text-verde sm:text-[32px]">{{ stats.socios }}</div><div class="mt-1.5 text-sm text-suave-2">Sócios</div></div>
                                <div class="rounded-[14px] border border-linha bg-white p-4"><div class="text-[26px] font-extrabold leading-none text-verde sm:text-[32px]">{{ stats.eventos }}</div><div class="mt-1.5 text-sm text-suave-2">Eventos por ano</div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- GALERIA -->
            <section id="galeria" class="border-y border-linha bg-white py-16 sm:py-[88px]">
                <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-8 px-4 sm:px-6">
                    <div class="reveal flex flex-col gap-2.5 text-center">
                        <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Momentos</p>
                        <h2 class="m-0 text-[clamp(30px,3.6vw,42px)] font-extrabold leading-[1.1]">A vida da associação</h2>
                    </div>
                    <div class="gal">
                        <div v-for="(img, i) in galeria" :key="i" class="reveal rounded-[14px] bg-linha bg-cover bg-center transition hover:scale-[1.02]" :style="{ backgroundImage: `url(${img})` }"></div>
                    </div>
                </div>
            </section>

            <!-- SÓCIO -->
            <section class="bg-verde-claro py-16 text-center sm:py-[72px]">
                <div class="reveal mx-auto flex w-full max-w-[1120px] flex-col items-center gap-3 px-4 sm:px-6">
                    <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde-escuro">Faça parte</p>
                    <h2 class="m-0 text-[clamp(30px,3.6vw,42px)] font-extrabold leading-[1.1]">Torne-se sócio da associação</h2>
                    <p class="m-0 mb-3 max-w-[34rem] text-lg text-suave">Apoie as festas, o desporto e a cultura da aldeia — e faça parte da casa de todos.</p>
                    <Link class="inline-flex h-14 items-center rounded-[10px] bg-verde px-7 text-[17px] font-bold text-white no-underline transition hover:bg-verde-escuro hover:text-white" :href="r('patrocinios.index', '/patrocinios')">Quero ser sócio</Link>
                </div>
            </section>

            <!-- PATROCINADORES -->
            <section v-if="temPatrocinios" class="pt-16 sm:pt-[88px]">
                <div class="mx-auto flex w-full max-w-[1120px] flex-col gap-2.5 px-4 text-center sm:px-6">
                    <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Quem nos apoia</p>
                    <h2 class="m-0 text-[clamp(30px,3.6vw,42px)] font-extrabold leading-[1.1]">Patrocinadores</h2>
                </div>
                <SponsorsSlider :patrocinadores="patrocinadores" />
            </section>

            <!-- CONTACTO -->
            <section id="contacto" class="border-t border-linha bg-white py-16 sm:py-[88px]">
                <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                    <div class="grid items-start gap-10 md:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)]">
                        <div class="reveal flex flex-col gap-3.5">
                            <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Fale connosco</p>
                            <h2 class="m-0 text-[clamp(30px,3.6vw,42px)] font-extrabold leading-[1.1]">Contacto</h2>
                            <p class="m-0 text-[17px] text-suave">Dúvidas, sugestões ou quer participar? Deixe uma mensagem — respondemos com gosto.</p>
                            <ul class="m-0 mt-2 flex list-none flex-col gap-1 p-0">
                                <li class="flex min-h-11 items-center gap-3 text-base">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-verde-claro text-verde"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z" /><circle cx="12" cy="9.5" r="2.5" /></svg></span>
                                    Santana, Carvalhal Benfeito, Caldas da Rainha
                                </li>
                                <li class="flex min-h-11 items-center gap-3 text-base">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-verde-claro text-verde"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></svg></span>
                                    <a :href="`mailto:${contactEmail}`" class="break-all font-bold text-verde hover:text-verde-escuro">{{ contactEmail }}</a>
                                </li>
                                <li class="flex min-h-11 items-center gap-3 text-base">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-verde-claro text-verde"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2" /><path d="M11 18h2" /></svg></span>
                                    Facebook e Instagram: @ardcsantana
                                </li>
                            </ul>
                            <div class="mt-3 flex flex-col gap-2.5 rounded-[14px] border border-linha bg-fundo p-5">
                                <span class="text-xs font-bold uppercase tracking-[0.1em] text-verde">Reservas do salão</span>
                                <p class="m-0 text-[15px] text-suave">Para alugar o salão (casamentos, batizados, aniversários, convívios), faça o pedido de pré-reserva online — a associação confirma a disponibilidade.</p>
                                <Link class="inline-flex h-12 items-center gap-2 self-start rounded-[10px] bg-verde px-[18px] text-[15px] font-bold text-white no-underline transition hover:bg-verde-escuro hover:text-white" :href="r('salao.pre-reserva', '/reserva-salao')">
                                    Pedir pré-reserva do salão
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                                </Link>
                            </div>
                        </div>

                        <div class="reveal rounded-[14px] border border-linha bg-white p-5 shadow-[0_8px_24px_rgba(22,32,28,.06)] sm:p-7">
                            <div v-if="formSent" class="flex flex-col items-center gap-2.5 px-2 py-8 text-center">
                                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-verde-claro text-verde"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L19 7" /></svg></span>
                                <h3 class="m-0 text-[22px] font-extrabold">Mensagem enviada</h3>
                                <p class="m-0 text-base text-suave">Obrigado pelo contacto. Respondemos assim que possível.</p>
                            </div>
                            <form v-else class="flex flex-col gap-4" novalidate @submit.prevent="submitForm">
                                <label class="flex flex-col gap-1.5 text-[15px] font-bold">
                                    Nome
                                    <input v-model="form.name" type="text" placeholder="O seu nome" class="h-[50px] rounded-[10px] border border-linha-forte bg-white px-3.5 text-base font-normal text-tinta focus:border-verde focus:ring-verde" />
                                    <small v-if="errors.name" class="text-sm font-semibold text-perigo">{{ errors.name }}</small>
                                </label>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="flex flex-col gap-1.5 text-[15px] font-bold">
                                        Email
                                        <input v-model="form.email" type="email" placeholder="email@exemplo.pt" class="h-[50px] rounded-[10px] border border-linha-forte bg-white px-3.5 text-base font-normal text-tinta focus:border-verde focus:ring-verde" />
                                        <small v-if="errors.email" class="text-sm font-semibold text-perigo">{{ errors.email }}</small>
                                    </label>
                                    <label class="flex flex-col gap-1.5 text-[15px] font-bold">
                                        Telefone
                                        <input v-model="form.phone" type="tel" placeholder="9xx xxx xxx" class="h-[50px] rounded-[10px] border border-linha-forte bg-white px-3.5 text-base font-normal text-tinta focus:border-verde focus:ring-verde" />
                                        <small v-if="errors.phone" class="text-sm font-semibold text-perigo">{{ errors.phone }}</small>
                                    </label>
                                </div>
                                <label class="flex flex-col gap-1.5 text-[15px] font-bold">
                                    Mensagem
                                    <textarea v-model="form.message" rows="4" placeholder="Como podemos ajudar?" class="resize-y rounded-[10px] border border-linha-forte bg-white px-3.5 py-3 text-base font-normal text-tinta focus:border-verde focus:ring-verde"></textarea>
                                    <small v-if="errors.message" class="text-sm font-semibold text-perigo">{{ errors.message }}</small>
                                </label>
                                <AvisoErros :errors="errors" :excluir="['name', 'email', 'phone', 'message']" />
                                <button type="submit" class="h-[54px] rounded-[10px] bg-verde text-[17px] font-bold text-white transition hover:bg-verde-escuro disabled:cursor-not-allowed disabled:opacity-60" :disabled="form.processing">{{ form.processing ? 'A enviar…' : 'Enviar mensagem' }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- FOOTER -->
        <footer class="bg-escuro pb-6 pt-12 text-escuro-inativo">
            <div class="mx-auto w-full max-w-[1120px] px-4 sm:px-6">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr]">
                    <div>
                        <div class="flex items-center gap-3">
                            <img :src="associationLogo" alt="" class="h-11 w-11 rounded-full bg-white object-contain p-1" />
                            <b class="text-lg font-extrabold text-white">ARDC Santana</b>
                        </div>
                        <p class="mb-0 mt-4 max-w-[20rem] text-[15px] text-[#B9C4BE]">Associação Recreativa, Desportiva e Cultural de Santana. A casa de todos, em Carvalhal Benfeito.</p>
                    </div>
                    <nav aria-label="Navegar" class="flex flex-col">
                        <h4 class="m-0 mb-2 text-sm font-bold uppercase tracking-[0.1em] text-white">Navegar</h4>
                        <a v-for="link in navLinks" :key="link[0]" :href="link[1]" class="py-2 text-[15px] font-semibold text-escuro-inativo no-underline hover:text-white" @click.prevent="scrollTo(link[1])">{{ link[0] }}</a>
                    </nav>
                    <div class="flex flex-col">
                        <h4 class="m-0 mb-2 text-sm font-bold uppercase tracking-[0.1em] text-white">Contactos</h4>
                        <Link :href="r('salao.pre-reserva', '/reserva-salao')" class="py-2 text-[15px] font-semibold text-escuro-inativo no-underline hover:text-white">Reservar o salão</Link>
                        <a :href="`mailto:${contactEmail}`" class="break-all py-2 text-[15px] font-semibold text-escuro-inativo no-underline hover:text-white">{{ contactEmail }}</a>
                        <a href="#" class="py-2 text-[15px] font-semibold text-escuro-inativo no-underline hover:text-white">Santana, Carvalhal Benfeito</a>
                        <a href="#" class="py-2 text-[15px] font-semibold text-escuro-inativo no-underline hover:text-white">@ardcsantana</a>
                    </div>
                    <nav aria-label="Legal" class="flex flex-col">
                        <h4 class="m-0 mb-2 text-sm font-bold uppercase tracking-[0.1em] text-white">Legal</h4>
                        <Link :href="r('legal.privacidade', '/politica-de-privacidade')" class="py-2 text-[15px] font-semibold text-escuro-inativo no-underline hover:text-white">Privacidade</Link>
                        <Link :href="r('legal.termos', '/termos-e-condicoes')" class="py-2 text-[15px] font-semibold text-escuro-inativo no-underline hover:text-white">Termos</Link>
                        <Link :href="r('legal.cookies', '/politica-de-cookies')" class="py-2 text-[15px] font-semibold text-escuro-inativo no-underline hover:text-white">Cookies</Link>
                    </nav>
                </div>
                <div class="mt-8 flex flex-wrap justify-between gap-x-6 gap-y-2 border-t border-escuro-2 pt-5 text-[13px] text-[#8FA39A]">
                    <span>© {{ currentYear }} Associação Recreativa, Desportiva e Cultural de Santana</span>
                    <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-bold text-[#B9C4BE] no-underline hover:text-white">#CreatingDevelopingImproving4you</a>
                </div>
            </div>
        </footer>

        <CookieBanner />
    </div>
</template>

<style scoped>
.santana a { text-decoration: none; }

/* Abertura do menu telemóvel */
.drop-enter-active, .drop-leave-active { transition: opacity 0.25s, transform 0.25s; }
.drop-enter-from, .drop-leave-to { opacity: 0; transform: translateY(-8px); }

/* Galeria em mosaico */
.gal { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); grid-auto-rows: 184px; gap: 12px; grid-template-areas: "a a b d" "a a c d" "e e f f"; }
.gal > :nth-child(1) { grid-area: a; } .gal > :nth-child(2) { grid-area: b; } .gal > :nth-child(3) { grid-area: c; }
.gal > :nth-child(4) { grid-area: d; } .gal > :nth-child(5) { grid-area: e; } .gal > :nth-child(6) { grid-area: f; }
@media (max-width: 900px) {
    .gal { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-auto-rows: 140px; grid-template-areas: none; }
    .gal > * { grid-area: auto !important; }
    .gal > :nth-child(1) { grid-column: span 2 !important; grid-row: span 2 !important; }
    .gal > :nth-child(4) { grid-row: span 2 !important; }
}

/* Parallax subtil na foto do hero (scroll-driven) */
@supports (animation-timeline: scroll()) {
    @media (prefers-reduced-motion: no-preference) {
        .hero-img { animation: hero-par linear both; animation-timeline: scroll(root); animation-range: 0 100vh; will-change: transform; }
    }
}
@keyframes hero-par { from { transform: scale(1.04); } to { transform: translateY(6%) scale(1.12); } }

/* Revelação por scroll — CSS puro (scroll-driven animations), progressive enhancement */
.reveal { opacity: 1; }
@supports ((animation-timeline: view()) and (animation-range: entry)) {
    @media (prefers-reduced-motion: no-preference) {
        .reveal { opacity: 0; animation: reveal-in linear both; animation-timeline: view(); animation-range: entry 5% cover 26%; }
    }
}
@keyframes reveal-in { from { opacity: 0; transform: translateY(22px); } to { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) { .santana *, .santana *::before, .santana *::after { animation: none !important; } }
</style>
