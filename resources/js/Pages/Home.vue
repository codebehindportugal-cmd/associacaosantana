<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useRecaptcha } from '@/composables/useRecaptcha';
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
    { icon: '🎭', label: 'Cultura', text: 'Festas, tradições e os momentos que contam a história de Santana, de geração em geração.' },
    { icon: '⚽', label: 'Desporto', text: 'Oportunidades para mexer, caminhar e participar — juntando idades e famílias.' },
    { icon: '🤝', label: 'Convívio', text: 'O salão e o largo: uma casa aberta a sócios, amigos e visitantes o ano inteiro.' },
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

    <div class="santana">
        <div class="topbar"><div class="wrap"><span>Santana · Carvalhal Benfeito · Caldas da Rainha</span><span class="hide-sm"><Link :href="r('salao.pre-reserva', '/reserva-salao')"><b>Reserve o salão online</b></Link> · @ardcsantana</span></div></div>

        <header class="nav">
            <div class="wrap nav-inner">
                <a class="brand" href="#inicio" @click.prevent="scrollTo('#inicio')">
                    <img :src="associationLogo" alt="ARDC Santana" />
                    <span><b>ARDC Santana</b><small>Recreio · Desporto · Cultura</small></span>
                </a>
                <nav class="menu">
                    <a v-for="link in navLinks" :key="link[0]" :href="link[1]" @click.prevent="scrollTo(link[1])">{{ link[0] }}</a>
                    <Link class="btn btn-primary btn-sm" :href="r('salao.pre-reserva', '/reserva-salao')">Reservar salão</Link>
                    <Link class="btn btn-green btn-sm" :href="r('patrocinios.index', '/patrocinios')">Ser sócio</Link>
                </nav>
                <button class="burger" aria-label="Menu" @click="menuOpen = !menuOpen"><span :class="{ open: menuOpen }"></span></button>
            </div>
            <Transition name="drop">
                <div v-if="menuOpen" class="mobile-menu">
                    <a v-for="link in navLinks" :key="link[0]" :href="link[1]" @click.prevent="scrollTo(link[1])">{{ link[0] }}</a>
                    <Link class="btn btn-primary" :href="r('salao.pre-reserva', '/reserva-salao')">Reservar o salão</Link>
                    <Link class="btn btn-green" :href="r('patrocinios.index', '/patrocinios')">Ser sócio</Link>
                </div>
            </Transition>
        </header>

        <!-- HERO -->
        <section class="hero" id="inicio">
            <div class="hero-img" :style="{ backgroundImage: `url(${heroImage})` }"></div>
            <div class="hero-orn" aria-hidden="true"></div>
            <div class="wrap"><div class="hero-inner">
                <p class="eyebrow light">Desde sempre, a casa da aldeia</p>
                <h1>O coração <span class="italic">de Santana</span></h1>
                <p class="lead">Recreio, desporto e cultura numa casa aberta a sócios, famílias e visitantes — onde a aldeia se encontra, festeja e cuida das suas tradições.</p>
                <div class="hero-cta">
                    <a class="btn btn-primary" href="#eventos" @click.prevent="scrollTo('#eventos')">Ver eventos →</a>
                    <Link class="btn btn-ghost" :href="r('salao.pre-reserva', '/reserva-salao')">Reservar o salão</Link>
                </div>
            </div></div>
            <div class="infocard"><div class="wrap"><div class="inner">
                <div class="cell"><div class="k">Próximo evento</div><div class="v">{{ featured ? featured.title : 'Em breve' }}<small>{{ featured && featured.date ? featured.date : 'Segue @ardcsantana' }}</small></div></div>
                <div class="cell"><div class="k">Onde estamos</div><div class="v">Santana<small>Carvalhal Benfeito</small></div></div>
                <Link class="cell" :href="r('salao.pre-reserva', '/reserva-salao')"><div class="k">Aluguer do salão</div><div class="v">Reservar →<small>pedido de pré-reserva online</small></div></Link>
            </div></div></div>
        </section>

        <!-- A CASA -->
        <section class="section" id="casa"><div class="wrap">
            <div class="sec-head reveal"><p class="eyebrow">A nossa casa</p><h2>Um lugar para viver a aldeia</h2><p class="desc">Três valências, uma só casa. É aqui que a comunidade se junta o ano inteiro.</p></div>
            <div class="grid3">
                <div v-for="p in pillars" :key="p.label" class="pcard reveal"><div class="strip"></div><div class="body"><div class="ic">{{ p.icon }}</div><h3>{{ p.label }}</h3><p>{{ p.text }}</p></div></div>
            </div>
        </div></section>

        <!-- EVENTOS -->
        <section class="section feat-ev" id="eventos"><div class="wrap">
            <div class="sec-head reveal"><p class="eyebrow">Em destaque</p><h2>Próximos eventos</h2></div>
            <div v-if="featured" class="fe reveal">
                <a class="fe-img" :href="eventHref(featured)" :aria-label="featured.title" :style="{ backgroundImage: `url(${featured.poster || groupImage})` }"><span v-if="featured.date" class="ribbon">{{ featured.date }}</span></a>
                <div class="fe-body"><span class="badge">{{ featured.badge || 'Evento' }}</span><h3>{{ featured.title }}</h3><p>{{ featured.description || featured.subtitle || 'Junta-te a nós na próxima iniciativa da associação.' }}</p><div class="fe-cta"><a class="btn btn-primary" :href="eventHref(featured)">Ver evento →</a><a v-if="featured.externalUrl" class="btn btn-outline" :href="featured.externalUrl" target="_blank" rel="noopener noreferrer">{{ featured.externalLabel || 'Inscrições' }} ↗</a></div></div>
            </div>
            <div v-if="gridEvents.length" class="ev-grid">
                <a v-for="(event, i) in gridEvents" :key="event.id || event.title" class="ev reveal" :href="eventHref(event)">
                    <div class="poster" :style="{ backgroundImage: `url(${posterFor(event, i)})` }"><span v-if="event.date" class="rib">{{ event.date }}</span></div>
                    <div class="b"><span class="bd">{{ event.badge || 'Evento' }}</span><h3>{{ event.title }}</h3><p v-if="event.location || event.subtitle">{{ event.location || event.subtitle }}</p><span class="more">Saber mais →</span></div>
                </a>
            </div>
            <p v-if="!featured" class="empty">Novos eventos em breve. Segue-nos em <b>@ardcsantana</b>.</p>
        </div></section>

        <!-- SALÃO + BAR -->
        <section class="section" id="salao"><div class="wrap">
            <div class="sec-head reveal"><p class="eyebrow">Os nossos espaços</p><h2>Feitos para receber</h2></div>
            <div class="feat">
                <div class="fbox salao reveal"><img :src="heroImage" alt="" loading="lazy" decoding="async" style="object-position:left center" /><div class="ov"></div><div class="inner"><p class="eyebrow light">O salão</p><h3>Reserve o salão</h3><p>Casamentos, batizados, aniversários e convívios. Um espaço amplo, no coração da aldeia, pronto a receber a sua festa.</p><Link class="btn btn-primary" :href="r('salao.pre-reserva', '/reserva-salao')">Pedir pré-reserva</Link></div></div>
                <div class="fbox bar reveal" id="festa"><img :src="heroImage" alt="" loading="lazy" decoding="async" style="object-position:right center" /><div class="ov"></div><div class="inner"><p class="eyebrow light">Festa de Santana</p><h3>Os dias da festa</h3><p>Durante a festa, o restaurante e o bar da associação servem a aldeia e quem nos visita. Consulte os preços praticados na festa.</p><Link class="btn btn-primary" :href="r('precario', '/precario')">Ver preçário da festa</Link></div></div>
            </div>
        </div></section>

        <div class="scallop up"></div>
        <section class="quote"><div class="tx"></div><div class="wrap"><blockquote>“A casa de todos nós.”</blockquote><div class="who">O espírito da ARDC Santana</div></div></section>
        <div class="scallop"></div>

        <!-- COMUNIDADE -->
        <section class="section" id="comunidade"><div class="wrap"><div class="commu">
            <div class="frame reveal"><img :src="groupImage" alt="Sócios da ARDC Santana" loading="lazy" decoding="async" /></div>
            <div class="reveal"><p class="eyebrow">A comunidade</p><h2>Feita por gente da terra</h2><p>A Associação Recreativa, Desportiva e Cultural de Santana nasceu do desejo de ter um sítio nosso — para conviver, festejar e cuidar das tradições. Hoje continua a ser isso mesmo: a casa de todos.</p>
                <div class="stats"><div class="stat"><b>{{ stats.fundada }}</b><span>Fundada em</span></div><div class="stat"><b>{{ stats.socios }}</b><span>Sócios</span></div><div class="stat"><b>{{ stats.eventos }}</b><span>Eventos por ano</span></div></div>
            </div>
        </div></div></section>

        <!-- GALERIA -->
        <section class="section spon" id="galeria"><div class="wrap">
            <div class="sec-head center reveal"><p class="eyebrow center">Momentos</p><h2>A vida da associação</h2></div>
            <div class="gal">
                <div v-for="(img, i) in galeria" :key="i" class="tile-img reveal" :style="{ backgroundImage: `url(${img})` }"></div>
            </div>
        </div></section>

        <!-- SÓCIO -->
        <section class="join"><div class="wrap"><p class="eyebrow center join-eb">Faça parte</p><h2>Torne-se sócio da associação</h2><p>Apoie as festas, o desporto e a cultura da aldeia — e faça parte da casa de todos.</p><Link class="btn btn-green" :href="r('patrocinios.index', '/patrocinios')">Quero ser sócio</Link></div></section>

        <!-- PATROCINADORES -->
        <section v-if="temPatrocinios" class="section"><div class="wrap" style="text-align:center">
            <p class="eyebrow center">Quem nos apoia</p><h2 style="color:var(--green);margin-top:.3rem">Patrocinadores</h2>
            <div style="margin-top:30px"><SponsorsSlider :patrocinadores="patrocinadores" /></div>
        </div></section>

        <!-- CONTACTO -->
        <section class="section contact-sec" id="contacto"><div class="wrap"><div class="contact-grid">
            <div class="reveal">
                <p class="eyebrow">Fale connosco</p><h2>Contacto</h2>
                <p class="ct-lead">Dúvidas, sugestões ou quer participar? Deixe uma mensagem — respondemos com gosto.</p>
                <ul class="ct-list">
                    <li><span>📍</span> Santana, Carvalhal Benfeito, Caldas da Rainha</li>
                    <li><span>✉️</span> <a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a></li>
                    <li><span>📱</span> Facebook e Instagram: @ardcsantana</li>
                </ul>
                <div class="hours">
                    <span class="hours-t">Reservas do salão</span>
                    <p class="hours-p">Para alugar o salão (casamentos, batizados, aniversários, convívios), faça o pedido de pré-reserva online — a associação confirma a disponibilidade.</p>
                    <Link class="btn btn-primary btn-sm" :href="r('salao.pre-reserva', '/reserva-salao')">Pedir pré-reserva do salão →</Link>
                </div>
            </div>
            <div class="ct-card reveal">
                <div v-if="formSent" class="ct-sent"><div class="ct-sent-ic">✓</div><h3>Mensagem enviada</h3><p>Obrigado pelo contacto. Respondemos assim que possível.</p></div>
                <form v-else @submit.prevent="submitForm" novalidate>
                    <div class="field"><label>Nome</label><input v-model="form.name" type="text" placeholder="O seu nome" /><small v-if="errors.name">{{ errors.name }}</small></div>
                    <div class="field-row">
                        <div class="field"><label>Email</label><input v-model="form.email" type="email" placeholder="email@exemplo.pt" /><small v-if="errors.email">{{ errors.email }}</small></div>
                        <div class="field"><label>Telefone</label><input v-model="form.phone" type="tel" placeholder="9xx xxx xxx" /><small v-if="errors.phone">{{ errors.phone }}</small></div>
                    </div>
                    <div class="field"><label>Mensagem</label><textarea v-model="form.message" rows="4" placeholder="Como podemos ajudar?"></textarea><small v-if="errors.message">{{ errors.message }}</small></div>
                    <button type="submit" class="btn btn-green" :disabled="form.processing">{{ form.processing ? 'A enviar…' : 'Enviar mensagem' }}</button>
                </form>
            </div>
        </div></div></section>

        <!-- FOOTER -->
        <footer class="foot">
            <div class="wrap"><div class="foot-grid">
                <div><div class="brand foot-brand"><img :src="associationLogo" alt="" /><b>ARDC Santana</b></div><p class="foot-about">Associação Recreativa, Desportiva e Cultural de Santana. A casa de todos, em Carvalhal Benfeito.</p></div>
                <div><h4>Navegar</h4><a v-for="link in navLinks" :key="link[0]" :href="link[1]" @click.prevent="scrollTo(link[1])">{{ link[0] }}</a></div>
                <div><h4>Contactos</h4><Link :href="r('salao.pre-reserva', '/reserva-salao')">Reservar o salão</Link><a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a><a href="#">Santana, Carvalhal Benfeito</a><a href="#">@ardcsantana</a></div>
                <div><h4>Legal</h4><Link :href="r('legal.privacidade', '/politica-de-privacidade')">Privacidade</Link><Link :href="r('legal.termos', '/termos-e-condicoes')">Termos</Link><Link :href="r('legal.cookies', '/politica-de-cookies')">Cookies</Link></div>
            </div>
            <div class="foot-bot"><span>© {{ currentYear }} Associação Recreativa, Desportiva e Cultural de Santana</span><a href="https://ateneya.com/" target="_blank" rel="noopener">#CreatingDevelopingImproving4you</a></div>
            </div>
        </footer>

        <CookieBanner />
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;1,9..144,500;1,9..144,600&family=Space+Grotesk:wght@400;500;600;700&display=swap');

.santana {
    --cream: #F4EAD5; --paper: #FBF6EB; --ink: #241F1A; --stone: #655C50;
    --green: #2E4732; --green-d: #20321F; --ochre: #D99A2B; --ochre-d: #9A6A12; --terra: #A2472B;
    --r: 20px; --sh: 0 14px 40px rgba(46,71,50,.10);
    --tile: url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2260%22%20height%3D%2260%22%20viewBox%3D%220%200%2060%2060%22%3E%3Crect%20width%3D%2260%22%20height%3D%2260%22%20fill%3D%22%23F4EAD5%22/%3E%3Cg%20fill%3D%22none%22%20stroke%3D%22%232E4732%22%20stroke-width%3D%221.6%22%3E%3Cpath%20d%3D%22M30%206%20A24%2024%200%200%201%2054%2030%20A24%2024%200%200%201%2030%2054%20A24%2024%200%200%201%206%2030%20A24%2024%200%200%201%2030%206%20Z%22/%3E%3Ccircle%20cx%3D%2230%22%20cy%3D%2230%22%20r%3D%2211%22/%3E%3C/g%3E%3Ccircle%20cx%3D%2230%22%20cy%3D%2230%22%20r%3D%224%22%20fill%3D%22%23D99A2B%22/%3E%3Ccircle%20cx%3D%220%22%20cy%3D%220%22%20r%3D%224%22%20fill%3D%22%23A2472B%22/%3E%3Ccircle%20cx%3D%2260%22%20cy%3D%220%22%20r%3D%224%22%20fill%3D%22%23A2472B%22/%3E%3Ccircle%20cx%3D%220%22%20cy%3D%2260%22%20r%3D%224%22%20fill%3D%22%23A2472B%22/%3E%3Ccircle%20cx%3D%2260%22%20cy%3D%2260%22%20r%3D%224%22%20fill%3D%22%23A2472B%22/%3E%3C/svg%3E");
    --orn: url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2248%22%20height%3D%2248%22%20viewBox%3D%220%200%2048%2048%22%3E%3Cg%20fill%3D%22none%22%20stroke%3D%22%23F0D79A%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M24%203l6%2015%2015%206-15%206-6%2015-6-15-15-6%2015-6z%22/%3E%3C/g%3E%3Ccircle%20cx%3D%2224%22%20cy%3D%2224%22%20r%3D%223.5%22%20fill%3D%22%23F0D79A%22/%3E%3C/svg%3E");
    background: var(--paper); color: var(--ink);
    font-family: 'Space Grotesk', system-ui, sans-serif; font-size: 17px; line-height: 1.62;
}
.santana h1, .santana h2, .santana h3, .santana h4 { font-family: 'Fraunces', Georgia, serif; font-weight: 600; line-height: 1.06; letter-spacing: -0.01em; margin: 0; }
.italic { font-style: italic; font-weight: 500; }
.santana a { color: inherit; text-decoration: none; }
.wrap { max-width: 1200px; margin: 0 auto; padding: 0 26px; }
.hide-sm { display: inline; }
.eyebrow { font-size: 0.72rem; letter-spacing: 0.3em; text-transform: uppercase; color: var(--ochre-d); font-weight: 700; margin: 0; display: inline-flex; align-items: center; gap: 0.7rem; }
.eyebrow::before { content: ""; width: 26px; height: 1.5px; background: var(--ochre); }
.eyebrow.light { color: #f4dca6; } .eyebrow.light::before { background: #f4dca6; }
.eyebrow.center { justify-content: center; }
.btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.9rem 1.6rem; border-radius: 999px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: 0.2s; border: 1.5px solid transparent; font-family: inherit; }
.btn-sm { padding: 0.55rem 1.1rem; font-size: 0.9rem; }
.btn-primary { background: var(--ochre); color: #3a2708; box-shadow: 0 10px 26px rgba(217,154,43,.36); }
.btn-primary:hover { background: #c58a1e; transform: translateY(-2px); }
.btn-ghost { border-color: rgba(255,255,255,.65); color: #fff; } .btn-ghost:hover { background: rgba(255,255,255,.14); }
.btn-green { background: var(--green); color: #fff; } .btn-green:hover { background: var(--green-d); }
.btn:disabled { opacity: 0.6; cursor: default; }

.topbar { background: var(--green-d); color: #c3d0c4; font-size: 0.72rem; letter-spacing: 0.06em; }
.topbar .wrap { display: flex; justify-content: space-between; align-items: center; height: 34px; } .topbar b { color: #f4dca6; }

.nav { position: sticky; top: 0; z-index: 50; background: rgba(251,246,235,.93); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(46,71,50,.14); }
.nav-inner { display: flex; align-items: center; justify-content: space-between; height: 78px; }
.brand { display: flex; align-items: center; gap: 0.75rem; } .brand img { width: 48px; height: 48px; object-fit: contain; }
.brand b { font-family: 'Fraunces', serif; font-size: 1.32rem; font-weight: 600; display: block; }
.brand small { display: block; font-size: 0.6rem; letter-spacing: 0.22em; text-transform: uppercase; color: var(--stone); margin-top: -2px; }
.menu { display: flex; gap: 0.2rem; align-items: center; }
.menu a { padding: 0.55rem 0.85rem; border-radius: 9px; font-size: 0.92rem; font-weight: 500; color: #3a352e; transition: 0.2s; }
.menu a:hover { background: rgba(46,71,50,.08); color: var(--green); }
.burger { display: none; width: 44px; height: 44px; border: 0; background: none; cursor: pointer; position: relative; }
.burger span, .burger span::before, .burger span::after { content: ''; position: absolute; left: 10px; height: 2px; width: 24px; background: var(--green); transition: 0.25s; }
.burger span { top: 21px; } .burger span::before { top: -7px; } .burger span::after { top: 7px; }
.burger span.open { background: transparent; } .burger span.open::before { top: 0; transform: rotate(45deg); } .burger span.open::after { top: 0; transform: rotate(-45deg); }
.mobile-menu { display: flex; flex-direction: column; gap: 0.2rem; padding: 12px 26px 20px; border-bottom: 1px solid rgba(46,71,50,.12); background: var(--paper); }
.mobile-menu a { padding: 0.7rem 0.4rem; font-weight: 500; color: #3a352e; } .mobile-menu .btn { margin-top: 8px; justify-content: center; }
.drop-enter-active, .drop-leave-active { transition: opacity 0.25s, transform 0.25s; } .drop-enter-from, .drop-leave-to { opacity: 0; transform: translateY(-8px); }
.menu a.btn { padding: 0.55rem 1.05rem; font-weight: 600; margin-left: 0.25rem; }
.menu a.btn-primary, .menu a.btn-primary:hover { color: #3a2708; } .menu a.btn-primary:hover { background: #c58a1e; }
.menu a.btn-green, .menu a.btn-green:hover { color: #fff; } .menu a.btn-green:hover { background: var(--green-d); }
.infocard a.cell { display: block; transition: background .2s; } .infocard a.cell:hover { background: #fff; }
@media (max-width: 1140px) { .menu { display: none; } .burger { display: block; } }
@media (max-width: 620px) { .hide-sm { display: none; } }

.hero { position: relative; min-height: 92vh; display: flex; align-items: center; overflow: hidden; }
.hero-img { position: absolute; inset: 0; background-position: center 40%; background-size: cover; }
.hero-img::after { content: ''; position: absolute; inset: 0; background: linear-gradient(100deg, rgba(16,26,19,.9) 0%, rgba(20,32,24,.66) 42%, rgba(26,40,30,.28) 72%), linear-gradient(0deg, rgba(16,26,19,.8), transparent 46%); }
/* Parallax subtil no hero (scroll-driven) */
@supports (animation-timeline: scroll()) {
    @media (prefers-reduced-motion: no-preference) {
        .hero-img { animation: hero-par linear both; animation-timeline: scroll(root); animation-range: 0 100vh; will-change: transform; }
    }
}
@keyframes hero-par { to { transform: translateY(9%) scale(1.08); } }
.hero-inner { position: relative; z-index: 3; color: #fff; padding: 70px 0 150px; max-width: 38rem; }
.hero h1 { font-size: clamp(2.8rem, 6.8vw, 5.6rem); color: #fff; margin: 0.6rem 0 0.3rem; text-shadow: 0 6px 34px rgba(0,0,0,.5); }
.hero .lead { font-size: 1.16rem; color: #f2ead9; margin: 0.5rem 0 2rem; text-shadow: 0 2px 14px rgba(0,0,0,.55); max-width: 32rem; }
.hero-cta { display: flex; gap: 0.9rem; flex-wrap: wrap; }
.hero-orn { position: absolute; top: 120px; right: 64px; width: 104px; height: 104px; background: var(--orn) center/contain no-repeat; opacity: 0.4; z-index: 3; }
.infocard { position: absolute; left: 0; right: 0; bottom: -1px; z-index: 4; }
.infocard .wrap { display: flex; }
.infocard .inner { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: rgba(46,71,50,.14); border-radius: 16px 16px 0 0; overflow: hidden; box-shadow: 0 -14px 40px rgba(0,0,0,.2); max-width: 760px; width: 100%; }
.infocard .cell { background: var(--paper); padding: 20px 24px; }
.infocard .k { font-size: 0.64rem; letter-spacing: 0.18em; text-transform: uppercase; color: var(--ochre-d); font-weight: 700; }
.infocard .v { font-family: 'Fraunces', serif; font-size: 1.1rem; color: var(--ink); margin-top: 3px; line-height: 1.2; }
.infocard .v small { display: block; font-family: 'Space Grotesk'; font-size: 0.8rem; color: var(--stone); font-weight: 400; }
@media (max-width: 720px) { .infocard { position: static; } .infocard .inner { grid-template-columns: 1fr; border-radius: 0; } .hero-inner { padding-bottom: 70px; } }

.scallop { height: 30px; background-color: var(--paper); background-image: radial-gradient(circle at 15px -2px, transparent 14px, var(--green) 15px); background-size: 30px 30px; background-repeat: repeat-x; }
.scallop.up { background-color: var(--green); background-image: radial-gradient(circle at 15px 32px, transparent 14px, var(--paper) 15px); }

.section { padding: 100px 0; position: relative; }
.sec-head { max-width: 42rem; margin-bottom: 50px; } .sec-head.center { margin: 0 auto 50px; text-align: center; }
.sec-head h2 { font-size: clamp(2.1rem, 4.2vw, 3.2rem); margin: 0.5rem 0 0; color: var(--green); }
.sec-head p.desc { color: var(--stone); margin: 0.8rem 0 0; }

.grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
@media (max-width: 860px) { .grid3 { grid-template-columns: 1fr; } }
.pcard { background: #fff; border: 1px solid rgba(46,71,50,.12); border-radius: var(--r); overflow: hidden; transition: 0.25s; box-shadow: var(--sh); }
.pcard:hover { transform: translateY(-6px); box-shadow: 0 26px 54px rgba(46,71,50,.16); }
.pcard .strip { height: 12px; background: var(--tile); background-size: 24px; }
.pcard .body { padding: 32px; }
.pcard .ic { width: 62px; height: 62px; border-radius: 16px; display: grid; place-items: center; font-size: 1.8rem; background: linear-gradient(160deg, rgba(217,154,43,.22), rgba(162,71,43,.14)); margin-bottom: 18px; }
.pcard h3 { font-size: 1.6rem; color: var(--green); margin-bottom: 0.5rem; } .pcard p { color: var(--stone); font-size: 0.98rem; margin: 0; }

.feat-ev { background: var(--cream); }
.fe { display: grid; grid-template-columns: 0.9fr 1.1fr; background: #fff; border-radius: 22px; overflow: hidden; box-shadow: 0 24px 60px rgba(46,71,50,.16); border: 1px solid rgba(46,71,50,.1); }
@media (max-width: 860px) { .fe { grid-template-columns: 1fr; } }
.fe .fe-img { background-size: cover; background-position: center; min-height: 360px; position: relative; }
.fe .fe-img::after { content: ''; position: absolute; inset: 0; background: linear-gradient(160deg, rgba(30,50,36,.15), rgba(20,32,24,.35)); }
.fe .fe-img .ribbon { position: absolute; top: 22px; left: 22px; z-index: 2; background: var(--terra); color: #fff; padding: 8px 16px; border-radius: 10px; font-weight: 700; }
.fe .fe-body { padding: 46px; }
.fe-cta { display: flex; flex-wrap: wrap; gap: 0.7rem; }
.btn-outline { border-color: var(--green); color: var(--green) !important; background: transparent; } .btn-outline:hover { background: var(--green); color: #fff !important; }
.fe .fe-body .badge { font-size: 0.7rem; letter-spacing: 0.16em; text-transform: uppercase; color: var(--ochre-d); font-weight: 700; }
.fe h3 { font-size: 2.3rem; color: var(--ink); margin: 0.4rem 0 0.6rem; } .fe p { color: var(--stone); margin: 0 0 22px; }
.ev-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-top: 26px; }
@media (max-width: 860px) { .ev-grid { grid-template-columns: 1fr; } }
.ev { background: #fff; border-radius: var(--r); overflow: hidden; border: 1px solid rgba(46,71,50,.12); transition: 0.25s; display: flex; flex-direction: column; box-shadow: var(--sh); }
.ev:hover { transform: translateY(-5px); box-shadow: 0 24px 50px rgba(46,71,50,.16); }
.ev .poster { height: 186px; background-size: cover; background-position: center; position: relative; }
.ev .poster::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.05), rgba(20,32,24,.35)); }
.ev .rib { position: absolute; top: 14px; left: 14px; z-index: 2; background: #fff; border-radius: 9px; padding: 5px 11px; font-size: 0.72rem; font-weight: 700; color: var(--terra); box-shadow: 0 6px 16px rgba(0,0,0,.2); }
.ev .b { padding: 24px; flex: 1; display: flex; flex-direction: column; }
.ev .bd { font-size: 0.66rem; letter-spacing: 0.14em; text-transform: uppercase; color: var(--ochre-d); font-weight: 700; }
.ev h3 { font-size: 1.32rem; margin: 0.3rem 0 0.3rem; } .ev p { color: var(--stone); font-size: 0.9rem; margin: 0 0 14px; flex: 1; }
.ev .more { color: var(--green); font-weight: 600; font-size: 0.9rem; }
.empty { color: var(--stone); font-size: 1.05rem; }

.feat { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media (max-width: 860px) { .feat { grid-template-columns: 1fr; } }
.fbox { position: relative; border-radius: 22px; overflow: hidden; min-height: 400px; display: flex; align-items: flex-end; color: #fff; }
.fbox img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.fbox .ov { position: absolute; inset: 0; }
.fbox.salao .ov { background: linear-gradient(180deg, rgba(35,58,42,.2), rgba(28,46,34,.94)); }
.fbox.bar .ov { background: linear-gradient(180deg, rgba(162,71,43,.18), rgba(110,42,24,.94)); }
.fbox .inner { position: relative; padding: 42px; z-index: 2; }
.fbox h3 { font-size: 2.1rem; margin: 0.3rem 0 0.5rem; } .fbox p { color: rgba(255,255,255,.92); max-width: 26rem; margin: 0 0 20px; }

.quote { background: var(--green); color: #fff; text-align: center; padding: 96px 0; position: relative; overflow: hidden; }
.quote .tx { position: absolute; inset: 0; background: var(--tile); background-size: 60px; opacity: 0.05; }
.quote blockquote { position: relative; font-family: 'Fraunces', serif; font-style: italic; font-weight: 500; font-size: clamp(1.8rem, 4vw, 3rem); max-width: 26rem; margin: 0 auto; line-height: 1.2; }
.quote .who { position: relative; margin-top: 20px; color: #f4dca6; letter-spacing: 0.16em; text-transform: uppercase; font-size: 0.72rem; }

.commu { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: center; }
@media (max-width: 860px) { .commu { grid-template-columns: 1fr; } }
.commu .frame { position: relative; padding: 14px; background: #fff; border-radius: 22px; box-shadow: 0 24px 60px rgba(46,71,50,.2); }
.commu .frame::after { content: ''; position: absolute; top: -16px; right: -16px; width: 64px; height: 64px; background: var(--orn) center/contain no-repeat; }
.commu .frame img { width: 100%; border-radius: 14px; display: block; }
.commu h2 { font-size: clamp(2.1rem, 4vw, 3.1rem); color: var(--green); margin: 0.4rem 0 0.6rem; } .commu p { color: #453f37; }
.stats { display: flex; gap: 40px; margin-top: 30px; flex-wrap: wrap; }
.stat b { font-family: 'Fraunces', serif; font-size: 2.6rem; color: var(--terra); display: block; line-height: 1; }
.stat span { font-size: 0.78rem; color: var(--stone); letter-spacing: 0.05em; }

.spon { background: var(--cream); }
.gal { display: grid; grid-template-columns: repeat(4, 1fr); grid-auto-rows: 184px; gap: 14px; margin-top: 36px; }
@media (max-width: 860px) { .gal { grid-template-columns: repeat(2, 1fr); } }
.tile-img { background-size: cover; background-position: center; border-radius: 14px; transition: 0.3s; }
.tile-img:hover { transform: scale(1.02); }
.gal .tile-img:nth-child(1) { grid-column: span 2; grid-row: span 2; } .gal .tile-img:nth-child(4) { grid-row: span 2; }

.join { background: linear-gradient(160deg, var(--ochre), #c58a1e); color: #3a2708; text-align: center; padding: 88px 0; }
.join .join-eb { color: #6e4a0e; justify-content: center; }
.join h2 { font-size: clamp(2.1rem, 4.5vw, 3.3rem); color: #3a2708; margin: 0 0 0.4rem; } .join p { color: #553a10; max-width: 34rem; margin: 0 auto 26px; }

.contact-sec { background: var(--paper); }
.contact-grid { display: grid; grid-template-columns: 0.9fr 1.1fr; gap: 48px; align-items: start; }
@media (max-width: 860px) { .contact-grid { grid-template-columns: 1fr; } }
.contact-sec h2 { font-size: clamp(2rem, 4vw, 3rem); color: var(--green); margin: 0.3rem 0 0.6rem; }
.ct-lead { color: #453f37; }
.ct-list { list-style: none; padding: 0; margin: 24px 0 0; }
.ct-list li { display: flex; gap: 10px; align-items: center; padding: 8px 0; color: #453f37; } .ct-list a { color: var(--ochre-d); font-weight: 600; }
.hours { margin-top: 26px; max-width: 22rem; }
.hours-t { display: block; font-size: 0.66rem; letter-spacing: 0.16em; text-transform: uppercase; color: var(--ochre-d); font-weight: 700; margin-bottom: 8px; }
.hours-p { margin: 0 0 14px; font-size: 0.92rem; color: var(--stone); }
.hours-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed rgba(46,71,50,.2); }
.ct-card { background: #fff; border: 1px solid rgba(46,71,50,.14); border-radius: 20px; padding: 30px; box-shadow: var(--sh); }
.field { margin-bottom: 16px; display: flex; flex-direction: column; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 520px) { .field-row { grid-template-columns: 1fr; } }
.field label { font-size: 0.78rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: var(--stone); margin-bottom: 6px; }
.field input, .field textarea { border: 1.5px solid rgba(46,71,50,.18); border-radius: 12px; padding: 12px 14px; font-family: inherit; font-size: 0.98rem; background: var(--paper); color: var(--ink); transition: 0.2s; }
.field input:focus, .field textarea:focus { outline: none; border-color: var(--ochre); box-shadow: 0 0 0 3px rgba(217,154,43,.18); background: #fff; }
.field small { color: #b23b22; font-size: 0.78rem; margin-top: 5px; }
.ct-card .btn { width: 100%; justify-content: center; margin-top: 6px; }
.ct-sent { text-align: center; padding: 30px 10px; }
.ct-sent-ic { width: 56px; height: 56px; border-radius: 999px; background: rgba(46,71,50,.12); color: var(--green); font-size: 1.6rem; display: grid; place-items: center; margin: 0 auto 14px; }
.ct-sent h3 { color: var(--green); font-size: 1.4rem; margin: 0 0 0.3rem; } .ct-sent p { color: var(--stone); margin: 0; }

.foot { background: var(--green-d); color: #cbd6cc; padding: 64px 0 26px; }
.foot-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 34px; }
@media (max-width: 860px) { .foot-grid { grid-template-columns: 1fr 1fr; } }
.foot-brand { margin-bottom: 14px; } .foot-brand img { width: 46px; height: 46px; } .foot-brand b { color: #fff; font-family: 'Fraunces', serif; font-size: 1.2rem; }
.foot-about { color: #a9bbab; max-width: 20rem; font-size: 0.92rem; }
.foot h4 { color: #fff; font-family: 'Fraunces', serif; font-size: 1.1rem; margin: 0 0 14px; }
.foot a { color: #cbd6cc; display: block; padding: 3px 0; font-size: 0.92rem; } .foot a:hover { color: var(--ochre); }
.foot-bot { border-top: 1px solid rgba(255,255,255,.12); margin-top: 36px; padding-top: 20px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; font-size: 0.8rem; color: #9fb0a2; }

/* Revelação por scroll — CSS puro (scroll-driven animations), progressive enhancement */
.reveal { opacity: 1; } /* base: visível para browsers sem suporte */
@supports ((animation-timeline: view()) and (animation-range: entry)) {
    @media (prefers-reduced-motion: no-preference) {
        .reveal { opacity: 0; animation: reveal-in linear both; animation-timeline: view(); animation-range: entry 5% cover 26%; }
    }
}
@keyframes reveal-in { from { opacity: 0; transform: translateY(22px); } to { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) { .santana *, .santana *::before, .santana *::after { animation: none !important; } }
</style>
