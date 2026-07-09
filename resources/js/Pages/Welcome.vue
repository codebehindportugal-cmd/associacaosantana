<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import AOS from 'aos';
import 'aos/dist/aos.css';

const props = defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
    laravelVersion: { type: String, required: true },
    phpVersion: { type: String, required: true },
    patrocinadores: { type: Array, default: () => [] },
});

const scrolled    = ref(false);
const mobileOpen  = ref(false);
const year        = new Date().getFullYear();

function onScroll() { scrolled.value = window.scrollY > 60; }

/* ── Animated counters ── */
const stats = ref([
    { end: 30,  current: 0, suffix: '+', label: 'Anos de história' },
    { end: 500, current: 0, suffix: '+', label: 'Sócios activos'   },
    { end: 20,  current: 0, suffix: '+', label: 'Eventos por ano'  },
]);

function runCounter(stat) {
    const duration = 1800;
    const steps    = 55;
    const step     = stat.end / steps;
    let   n        = 0;
    const id = setInterval(() => {
        n = Math.min(n + step, stat.end);
        stat.current = Math.round(n);
        if (n >= stat.end) clearInterval(id);
    }, duration / steps);
}

let statsObserver = null;

/* ── Canvas particles ── */
const particlesCanvas = ref(null);
let particlesRAF = null;
let particlesResizeCleanup = null;

function initParticles(canvas) {
    const ctx = canvas.getContext('2d');
    let W = canvas.width = canvas.offsetWidth;
    let H = canvas.height = canvas.offsetHeight;

    const COUNT = 85;
    const pts = Array.from({ length: COUNT }, () => ({
        x: Math.random() * W,
        y: Math.random() * H,
        vx: (Math.random() - 0.5) * 0.38,
        vy: (Math.random() - 0.5) * 0.38,
        r: Math.random() * 1.4 + 0.4,
        a: Math.random(),
    }));

    function draw() {
        ctx.clearRect(0, 0, W, H);
        for (const p of pts) {
            p.x += p.vx; p.y += p.vy;
            if (p.x < 0) p.x = W; if (p.x > W) p.x = 0;
            if (p.y < 0) p.y = H; if (p.y > H) p.y = 0;
        }
        for (let i = 0; i < pts.length; i++) {
            for (let j = i + 1; j < pts.length; j++) {
                const dx = pts[i].x - pts[j].x;
                const dy = pts[i].y - pts[j].y;
                const d = Math.sqrt(dx * dx + dy * dy);
                if (d < 115) {
                    ctx.beginPath();
                    ctx.strokeStyle = `rgba(201,168,76,${0.14 * (1 - d / 115)})`;
                    ctx.lineWidth = 0.5;
                    ctx.moveTo(pts[i].x, pts[i].y);
                    ctx.lineTo(pts[j].x, pts[j].y);
                    ctx.stroke();
                }
            }
        }
        for (const p of pts) {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(201,168,76,${0.3 + p.a * 0.4})`;
            ctx.fill();
        }
        particlesRAF = requestAnimationFrame(draw);
    }
    draw();

    function onResize() {
        W = canvas.width = canvas.offsetWidth;
        H = canvas.height = canvas.offsetHeight;
    }
    window.addEventListener('resize', onResize, { passive: true });
    particlesResizeCleanup = () => window.removeEventListener('resize', onResize);
}

/* ── Typing animation ── */
const heroSubFull1 = 'Cultura, desporto e tradição no coração de Caldas da Rainha.';
const heroSubFull2 = 'Junte-se à nossa comunidade.';
const typedRaw = ref('');
const showCursor = ref(true);
let typingInterval = null;
let cursorInterval = null;

const typedLine1 = computed(() => {
    const nl = typedRaw.value.indexOf('\n');
    return nl === -1 ? typedRaw.value : typedRaw.value.slice(0, nl);
});
const typedLine2 = computed(() => {
    const nl = typedRaw.value.indexOf('\n');
    return nl === -1 ? '' : typedRaw.value.slice(nl + 1);
});

function startTyping() {
    const full = heroSubFull1 + '\n' + heroSubFull2;
    let i = 0;
    cursorInterval = setInterval(() => { showCursor.value = !showCursor.value; }, 530);
    setTimeout(() => {
        typingInterval = setInterval(() => {
            typedRaw.value = full.slice(0, ++i);
            if (i >= full.length) clearInterval(typingInterval);
        }, 30);
    }, 1300);
}

onMounted(() => {
    AOS.init({ duration: 900, easing: 'ease-out-cubic', once: true, offset: 70 });
    window.addEventListener('scroll', onScroll, { passive: true });

    const statsEl = document.querySelector('.stats-row');
    if (statsEl) {
        statsObserver = new IntersectionObserver(([e]) => {
            if (e.isIntersecting) {
                stats.value.forEach(runCounter);
                statsObserver.disconnect();
            }
        }, { threshold: 0.3 });
        statsObserver.observe(statsEl);
    }

    if (particlesCanvas.value) initParticles(particlesCanvas.value);
    startTyping();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    statsObserver?.disconnect();
    if (particlesRAF) cancelAnimationFrame(particlesRAF);
    if (particlesResizeCleanup) particlesResizeCleanup();
    if (typingInterval) clearInterval(typingInterval);
    if (cursorInterval) clearInterval(cursorInterval);
});

const navLinks = [
    ['Sobre Nós',       '#sobre'],
    ['Eventos',         '#eventos'],
    ['Patrocinadores',  '#patrocinadores'],
    ['Contacto',        '#contacto'],
];

const events = [
    {
        img:   '/images/events/santana-2026-cartaz.png',
        date:  'Julho 2026',
        title: 'Festa de Santana 2026',
        desc:  'O maior evento cultural da região, com música ao vivo, gastronomia típica e tradição.',
        tag:   'Evento Principal',
    },
    {
        img:   '/images/events/afro-tropical-night-cartaz.png',
        date:  'Agosto 2026',
        title: 'Afro Tropical Night',
        desc:  'Uma noite vibrante de ritmos africanos e tropicais no coração de Santana.',
        tag:   'Música & Dança',
    },
    {
        img:   '/images/events/caminhada-primavera-2026.jpeg',
        date:  'Maio 2026',
        title: 'Caminhada da Primavera',
        desc:  'Descobre a beleza natural da Serra da Candeeiros numa caminhada inesquecível.',
        tag:   'Desporto & Natureza',
    },
];

</script>

<template>
    <Head title="Associação Recreativa de Santana" />

    <!-- ═══ Google Fonts ═══ -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,900;1,400;1,700&family=Inter:wght@300;400;500;600;700&display=swap" />

    <div class="pg-root">

        <!-- ══════════════════════════════
             NAVBAR
        ══════════════════════════════ -->
        <header class="site-nav" :class="{ 'nav-solid': scrolled }">
            <div class="nav-inner">

                <!-- Logo -->
                <Link :href="route('home')" class="logo-wrap">
                    <img src="/images/santana-logo.png" alt="ARDC Santana" class="logo-img" />
                    <span class="logo-name">ARDC Santana</span>
                </Link>

                <!-- Desktop links -->
                <nav class="desk-links" aria-label="Navegação principal">
                    <a
                        v-for="[label, href] in navLinks"
                        :key="label"
                        :href="href"
                        class="nav-a"
                    >{{ label }}</a>

                    <Link
                        v-if="canLogin && !$page.props.auth?.user"
                        :href="route('login')"
                        class="btn-entrar"
                    >Entrar</Link>

                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('dashboard')"
                        class="btn-entrar btn-entrar-filled"
                    >Dashboard</Link>
                </nav>

                <!-- Hamburger (mobile) -->
                <button
                    type="button"
                    class="hamburger"
                    :class="{ 'is-open': mobileOpen }"
                    aria-label="Abrir menu"
                    @click="mobileOpen = !mobileOpen"
                >
                    <span class="hb-line"></span>
                    <span class="hb-line"></span>
                    <span class="hb-line"></span>
                </button>
            </div>

            <!-- Mobile panel -->
            <Transition name="panel-fade">
                <div v-if="mobileOpen" class="mobile-panel">
                    <a
                        v-for="[label, href] in navLinks"
                        :key="label"
                        :href="href"
                        class="mp-link"
                        @click="mobileOpen = false"
                    >{{ label }}</a>
                    <Link
                        :href="route('login')"
                        class="mp-entrar"
                        @click="mobileOpen = false"
                    >Entrar</Link>
                </div>
            </Transition>
        </header>


        <!-- ══════════════════════════════
             HERO  (full-viewport)
        ══════════════════════════════ -->
        <section class="hero-sec">

            <!-- Animated background layers -->
            <div class="hero-bg-base" aria-hidden="true"></div>
            <div class="hero-bg-glow" aria-hidden="true"></div>

            <!-- Canvas particles -->
            <canvas ref="particlesCanvas" class="hero-canvas" aria-hidden="true"></canvas>

            <!-- Geometric ornament -->
            <div class="hero-ornament" aria-hidden="true">
                <div class="orn-ring orn-ring-1"></div>
                <div class="orn-ring orn-ring-2"></div>
                <div class="orn-ring orn-ring-3"></div>
                <div class="orn-line orn-line-h"></div>
                <div class="orn-line orn-line-v"></div>
            </div>

            <!-- Overlay gradient -->
            <div class="hero-overlay" aria-hidden="true"></div>

            <!-- Content -->
            <div class="hero-body">

                <p class="hero-eyebrow" data-aos="fade-down" data-aos-delay="100">
                    <span class="eyebrow-pulse"></span>
                    Santana · Caldas da Rainha
                </p>

                <h1 class="hero-title" data-aos="fade-up" data-aos-delay="200">
                    <span class="ht-top">Associação</span>
                    <span class="ht-bottom">de Santana</span>
                </h1>

                <div class="hero-rule" data-aos="zoom-in" data-aos-delay="380" aria-hidden="true"></div>

                <p class="hero-sub hero-sub-typed" data-aos="fade-up" data-aos-delay="440">
                    {{ typedLine1 }}<br v-if="typedLine2" />{{ typedLine2 }}<span
                        class="hero-cursor"
                        :class="{ 'cursor-off': !showCursor }"
                        aria-hidden="true"
                    >|</span>
                </p>

                <div class="hero-ctas" data-aos="fade-up" data-aos-delay="580">
                    <a href="#sobre" class="cta-ghost">Saber Mais</a>
                    <Link
                        v-if="canLogin"
                        :href="route('login')"
                        class="cta-gold"
                    >Entrar na plataforma</Link>
                </div>
            </div>

            <!-- Scroll indicator -->
            <div class="scroll-cue" aria-hidden="true">
                <span class="sc-label">Scroll</span>
                <span class="sc-bar"></span>
            </div>

            <!-- Bottom fade -->
            <div class="hero-fade-bottom" aria-hidden="true"></div>
        </section>


        <!-- ══════════════════════════════
             SOBRE NÓS  (light section)
        ══════════════════════════════ -->
        <section id="sobre" class="sobre-sec">
            <div class="sobre-inner">

                <!-- Text column -->
                <div class="sobre-text" data-aos="fade-right" data-aos-duration="1000">
                    <p class="sec-label">Quem Somos</p>
                    <h2 class="sec-h2 dark-h">
                        Uma Associação<br />ao Serviço<br />
                        <em>da Comunidade</em>
                    </h2>
                    <div class="sobre-rule" aria-hidden="true"></div>
                    <p class="sobre-para">
                        A Associação Recreativa e Desportiva do Carvalhal de Santana
                        (ARDC Santana) é uma instituição sem fins lucrativos dedicada
                        à promoção da cultura, desporto e bem-estar da comunidade de
                        Santana, em Caldas da Rainha.
                    </p>
                    <p class="sobre-para mt-4">
                        Fundada com a missão de preservar e promover as tradições
                        locais, organizamos eventos culturais, actividades desportivas
                        e iniciativas que fortalecem os laços comunitários.
                    </p>

                    <!-- Stats row -->
                    <div class="stats-row">
                        <div v-for="s in stats" :key="s.label" class="stat-item">
                            <span class="stat-num">{{ s.current }}{{ s.suffix }}</span>
                            <span class="stat-lbl">{{ s.label }}</span>
                        </div>
                    </div>

                    <a :href="route('pages.sobre-nos')" class="link-arrow">
                        Conhecer a Associação
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                            <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>

                <!-- Image column -->
                <div class="sobre-img-col" data-aos="fade-left" data-aos-delay="150" data-aos-duration="1000">
                    <div class="sobre-frame">
                        <img src="/images/santa-ana-transparent.png"
                             alt="Associação de Santana"
                             class="sobre-img" />
                        <!-- Corner accents -->
                        <span class="fc fc-tl" aria-hidden="true"></span>
                        <span class="fc fc-tr" aria-hidden="true"></span>
                        <span class="fc fc-bl" aria-hidden="true"></span>
                        <span class="fc fc-br" aria-hidden="true"></span>
                    </div>
                </div>
            </div>
        </section>


        <!-- ══════════════════════════════
             EVENTOS  (dark section)
        ══════════════════════════════ -->
        <section id="eventos" class="eventos-sec">
            <div class="eventos-inner">

                <div class="sec-header" data-aos="fade-up">
                    <p class="sec-label light-lbl">Próximos</p>
                    <h2 class="sec-h2 light-h">Eventos & Actividades</h2>
                    <div class="sec-rule" aria-hidden="true"></div>
                </div>

                <!-- 3-col grid -->
                <div class="eventos-grid">
                    <article
                        v-for="(ev, i) in events"
                        :key="i"
                        class="ev-card"
                        data-aos="fade-up"
                        :data-aos-delay="i * 120"
                    >
                        <div class="ev-img-wrap">
                            <img :src="ev.img" :alt="ev.title" class="ev-img" loading="lazy" />
                            <div class="ev-img-grad" aria-hidden="true"></div>
                            <span class="ev-tag">{{ ev.tag }}</span>
                        </div>
                        <div class="ev-body">
                            <time class="ev-date">{{ ev.date }}</time>
                            <h3 class="ev-title">{{ ev.title }}</h3>
                            <p class="ev-desc">{{ ev.desc }}</p>
                            <span class="ev-link">
                                Ver detalhes
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5"
                                          stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                        </div>
                    </article>
                </div>

                <div class="ev-more" data-aos="fade-up" data-aos-delay="200">
                    <a href="#eventos" class="btn-outline-gold">Ver todos os eventos</a>
                </div>
            </div>
        </section>


        <!-- ══════════════════════════════
             PATROCINADORES  (light section)
        ══════════════════════════════ -->
        <section id="patrocinadores" class="sponsors-sec">
            <div class="sponsors-header" data-aos="fade-up">
                <p class="sec-label">Com o apoio de</p>
                <h2 class="sec-h2 dark-h" style="font-size:clamp(1.8rem,4vw,3rem)">
                    Os Nossos Parceiros
                </h2>
                <div class="sobre-rule" aria-hidden="true"></div>
            </div>

            <!-- CSS Marquee -->
            <div v-if="patrocinadores.length" class="marquee-wrap" data-aos="fade-up" data-aos-delay="100">
                <div class="marquee-track">
                    <span
                        v-for="(s, i) in [...patrocinadores, ...patrocinadores, ...patrocinadores]"
                        :key="i"
                        class="sp-item"
                    >
                        <img
                            v-if="s.logo_url"
                            :src="s.logo_url"
                            :alt="s.empresa"
                            class="sp-logo"
                            loading="lazy"
                        />
                        <span v-else>{{ s.empresa }}</span>
                    </span>
                </div>
            </div>

            <div class="sp-cta" data-aos="fade-up" data-aos-delay="150">
                <a :href="route('patrocinios.index')" class="link-arrow dark-arrow">
                    Ver todos os patrocinadores
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
        </section>


        <!-- ══════════════════════════════
             CONTACTO  (dark section)
        ══════════════════════════════ -->
        <section id="contacto" class="contacto-sec">
            <div class="contacto-inner">
                <div class="sec-header" data-aos="fade-up">
                    <p class="sec-label light-lbl">Contacto</p>
                    <h2 class="sec-h2 light-h">Fale Connosco</h2>
                    <div class="sec-rule" aria-hidden="true"></div>
                    <p class="contacto-sub">
                        Questões, sugestões ou interesse em tornar-se sócio?<br />
                        Estamos ao seu dispor.
                    </p>
                </div>

                <div class="contacto-grid">
                    <div class="ct-card" data-aos="fade-up" data-aos-delay="0">
                        <div class="ct-icon">✉</div>
                        <p class="ct-label">Email</p>
                        <a href="mailto:ardcsantana@outlook.com" class="ct-val">ardcsantana@outlook.com</a>
                    </div>
                    <div class="ct-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="ct-icon">📍</div>
                        <p class="ct-label">Localização</p>
                        <span class="ct-val">Santana, Carvalhal Benfeito<br />Caldas da Rainha</span>
                    </div>
                    <div class="ct-card" data-aos="fade-up" data-aos-delay="200">
                        <div class="ct-icon">📱</div>
                        <p class="ct-label">Redes Sociais</p>
                        <a href="#" class="ct-val">@ardcsantana</a>
                    </div>
                </div>
            </div>
        </section>


        <!-- ══════════════════════════════
             FOOTER
        ══════════════════════════════ -->
        <footer class="site-footer">
            <div class="footer-gold-bar" aria-hidden="true"></div>
            <div class="footer-inner">

                <!-- Col 1: logo + tagline -->
                <div class="ft-col ft-brand">
                    <Link :href="route('home')" class="ft-logo-wrap">
                        <img src="/images/santana-logo.png" alt="ARDC Santana" class="ft-logo" />
                        <span class="ft-logo-name">ARDC Santana</span>
                    </Link>
                    <p class="ft-tagline">
                        A unir a comunidade através<br />da cultura, desporto e convívio.
                    </p>
                    <div class="ft-socials">
                        <a href="#" class="ft-social-btn" aria-label="Facebook">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                        <a href="#" class="ft-social-btn" aria-label="Instagram">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                                <circle cx="12" cy="12" r="4"/>
                                <circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Col 2: navigation -->
                <div class="ft-col">
                    <p class="ft-heading">Navegação</p>
                    <ul class="ft-list">
                        <li v-for="[label, href] in navLinks" :key="label">
                            <a :href="href">{{ label }}</a>
                        </li>
                    </ul>
                </div>

                <!-- Col 3: legal + contact -->
                <div class="ft-col">
                    <p class="ft-heading">Legal & Contacto</p>
                    <ul class="ft-list">
                        <li><Link :href="route('legal.privacidade')">Privacidade</Link></li>
                        <li><Link :href="route('legal.termos')">Termos</Link></li>
                        <li><Link :href="route('legal.cookies')">Cookies</Link></li>
                        <li><a href="mailto:ardcsantana@outlook.com">Email</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-copy">
                © {{ year }} Associação de Santana · Desenvolvido por
                <a href="https://ateneya.com/" target="_blank" rel="noopener noreferrer">Ateneya</a>
            </div>
        </footer>

    </div><!-- /pg-root -->
</template>

<style scoped>
/* ═══════════════════════════════════════════
   TOKENS
═══════════════════════════════════════════ */
:root {
    --gold:       #C9A84C;
    --gold-light: #D4AF37;
    --gold-dim:   rgba(201,168,76,0.4);
    --dark:       #1a1208;
    --dark-2:     #120e06;
    --cream:      #FAF7F0;
    --cream-2:    #F3EFE6;
    --ink:        #2a2010;
    --ink-2:      #4a3c28;
    --white:      #fffdf8;
}

/* ═══════════════════════════════════════════
   BASE
═══════════════════════════════════════════ */
.pg-root {
    min-height: 100vh;
    font-family: 'Inter', system-ui, sans-serif;
    color: var(--ink);
    background: var(--dark);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
a { text-decoration: none; }
img { display: block; max-width: 100%; }

/* ═══════════════════════════════════════════
   NAVBAR
═══════════════════════════════════════════ */
.site-nav {
    position: fixed;
    inset-inline: 0;
    top: 0;
    z-index: 100;
    transition: background 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease;
    background: transparent;
    border-bottom: 1px solid transparent;
}
.nav-solid {
    background: rgba(22, 15, 4, 0.96);
    border-bottom-color: rgba(201,168,76,0.18);
    box-shadow: 0 4px 40px rgba(0,0,0,0.55);
    backdrop-filter: blur(20px) saturate(1.4);
}
.nav-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1280px;
    margin-inline: auto;
    padding: 1rem 2rem;
}
@media(max-width:768px) { .nav-inner { padding: 0.875rem 1.25rem; } }

/* Logo */
.logo-wrap {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-shrink: 0;
}
.logo-img {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: contain;
    transition: transform 0.4s ease;
}
.logo-wrap:hover .logo-img { transform: rotate(6deg) scale(1.06); }
.logo-name {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: 0.04em;
    display: none;
}
@media(min-width:520px) { .logo-name { display: block; } }

/* Desktop links */
.desk-links {
    display: none;
    align-items: center;
    gap: 0.25rem;
}
@media(min-width:860px) { .desk-links { display: flex; } }

.nav-a {
    position: relative;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: rgba(255,253,248,0.72);
    letter-spacing: 0.01em;
    transition: color 0.2s;
    border-radius: 6px;
}
.nav-a::after {
    content: '';
    position: absolute;
    bottom: 5px;
    left: 50%;
    transform: translateX(-50%) scaleX(0);
    width: 70%;
    height: 1.5px;
    background: var(--gold);
    border-radius: 2px;
    transition: transform 0.25s ease;
}
.nav-a:hover { color: var(--gold); }
.nav-a:hover::after { transform: translateX(-50%) scaleX(1); }

.btn-entrar {
    margin-left: 0.75rem;
    padding: 0.5rem 1.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gold);
    border: 1.5px solid rgba(201,168,76,0.45);
    border-radius: 8px;
    transition: all 0.25s ease;
    letter-spacing: 0.02em;
}
.btn-entrar:hover {
    background: rgba(201,168,76,0.1);
    border-color: var(--gold);
    transform: translateY(-1px);
}
.btn-entrar-filled {
    background: var(--gold);
    color: var(--dark-2);
    border-color: var(--gold);
}
.btn-entrar-filled:hover {
    background: var(--gold-light);
    border-color: var(--gold-light);
}

/* Hamburger */
.hamburger {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 5px;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    border: 1.5px solid rgba(201,168,76,0.25);
    background: rgba(201,168,76,0.05);
    cursor: pointer;
}
@media(min-width:860px) { .hamburger { display: none; } }
.hb-line {
    display: block;
    width: 20px;
    height: 1.5px;
    background: var(--gold);
    border-radius: 2px;
    transition: all 0.3s ease;
    transform-origin: center;
}
.hamburger.is-open .hb-line:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
.hamburger.is-open .hb-line:nth-child(2) { opacity: 0; transform: scaleX(0); }
.hamburger.is-open .hb-line:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }

/* Mobile panel */
.mobile-panel {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    padding: 0.75rem 1.25rem 1.25rem;
    background: rgba(18, 12, 4, 0.98);
    border-top: 1px solid rgba(201,168,76,0.1);
}
.mp-link {
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255,253,248,0.75);
    border-radius: 8px;
    border: 1px solid rgba(201,168,76,0.12);
    transition: all 0.2s;
}
.mp-link:hover { color: var(--gold); background: rgba(201,168,76,0.06); }
.mp-entrar {
    margin-top: 0.375rem;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--gold);
    text-align: center;
    border-radius: 8px;
    border: 1.5px solid rgba(201,168,76,0.4);
    background: rgba(201,168,76,0.06);
}
.panel-fade-enter-active, .panel-fade-leave-active { transition: all 0.25s ease; }
.panel-fade-enter-from, .panel-fade-leave-to { opacity: 0; transform: translateY(-8px); }

/* ═══════════════════════════════════════════
   HERO
═══════════════════════════════════════════ */
.hero-sec {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100svh;
    overflow: hidden;
}

/* Background */
.hero-bg-base {
    position: absolute;
    inset: 0;
    background: var(--dark);
}
.hero-bg-glow {
    position: absolute;
    inset: -8%;
    background:
        radial-gradient(ellipse 80% 60% at 20% 70%, rgba(201,168,76,0.10) 0%, transparent 55%),
        radial-gradient(ellipse 60% 50% at 80% 30%, rgba(180,140,50,0.07) 0%, transparent 55%),
        radial-gradient(ellipse 100% 80% at 50% 50%, rgba(201,168,76,0.04) 0%, transparent 65%);
    animation: kenBurns 18s ease-in-out infinite alternate;
}
@keyframes kenBurns {
    0%   { opacity: 0.65; transform: scale(1)    translate(0%,   0%); }
    33%  { opacity: 0.85; transform: scale(1.07) translate(-2%,  1%); }
    66%  { opacity: 0.90; transform: scale(1.05) translate(2%,  -1%); }
    100% { opacity: 1;    transform: scale(1.1)  translate(0%,   0%); }
}

/* Canvas particles */
.hero-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    z-index: 3;
    pointer-events: none;
}

/* Ornament rings */
.hero-ornament {
    position: absolute;
    inset: 0;
    pointer-events: none;
}
.orn-ring {
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(201,168,76,0.07);
}
.orn-ring-1 {
    width: 600px; height: 600px;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    animation: spin 40s linear infinite;
}
.orn-ring-2 {
    width: 900px; height: 900px;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    border-color: rgba(201,168,76,0.04);
    animation: spin 65s linear infinite reverse;
}
.orn-ring-3 {
    width: 1200px; height: 1200px;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    border-color: rgba(201,168,76,0.03);
    animation: spin 90s linear infinite;
}
.orn-line {
    position: absolute;
    background: rgba(201,168,76,0.06);
}
.orn-line-h { height: 1px; width: 100%; top: 50%; }
.orn-line-v { width: 1px; height: 100%; left: 50%; }
@keyframes spin {
    to { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Overlay */
.hero-overlay {
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 80% 70% at 50% 40%, rgba(26,18,8,0.2) 0%, rgba(26,18,8,0.75) 100%);
}

/* Content */
.hero-body {
    position: relative;
    z-index: 10;
    text-align: center;
    padding: 2rem 1.5rem;
    max-width: 900px;
    width: 100%;
}
.hero-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.625rem;
    margin-bottom: 2rem;
    padding: 0.375rem 1.125rem;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--gold);
    border: 1px solid rgba(201,168,76,0.28);
    border-radius: 100px;
    background: rgba(201,168,76,0.06);
    backdrop-filter: blur(8px);
}
.eyebrow-pulse {
    display: block;
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--gold);
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50%       { transform: scale(1.4); opacity: 0.6; }
}

.hero-title {
    display: flex;
    flex-direction: column;
    font-family: 'Playfair Display', Georgia, serif;
    line-height: 0.9;
    margin-bottom: 1.5rem;
}
.ht-top {
    font-size: clamp(3.5rem, 10vw, 8.5rem);
    font-weight: 900;
    color: var(--white);
    letter-spacing: -0.02em;
    text-shadow: 0 4px 40px rgba(201,168,76,0.2);
}
.ht-bottom {
    font-size: clamp(3.5rem, 10vw, 8.5rem);
    font-weight: 400;
    font-style: italic;
    color: var(--gold);
    letter-spacing: -0.01em;
    text-shadow: 0 4px 60px rgba(201,168,76,0.35), 0 0 120px rgba(201,168,76,0.15);
}

.hero-rule {
    width: 64px;
    height: 2px;
    margin: 0 auto 1.75rem;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    border-radius: 2px;
}

.hero-sub {
    font-size: clamp(1rem, 2.2vw, 1.2rem);
    line-height: 1.8;
    color: rgba(255,253,248,0.62);
    margin-bottom: 2.75rem;
    max-width: 560px;
    margin-inline: auto;
}
.hero-cursor {
    display: inline-block;
    color: var(--gold);
    font-weight: 300;
    margin-left: 1px;
    transition: opacity 0.1s;
}
.hero-cursor.cursor-off { opacity: 0; }

.hero-ctas {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.cta-ghost {
    padding: 0.875rem 2.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gold);
    letter-spacing: 0.04em;
    border: 1.5px solid rgba(201,168,76,0.45);
    border-radius: 10px;
    transition: all 0.3s ease;
    backdrop-filter: blur(8px);
}
.cta-ghost:hover {
    border-color: var(--gold);
    background: rgba(201,168,76,0.1);
    transform: translateY(-2px);
}
.cta-gold {
    padding: 0.875rem 2.25rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--dark-2);
    letter-spacing: 0.04em;
    background: var(--gold);
    border-radius: 10px;
    transition: all 0.3s ease;
    box-shadow: 0 8px 32px rgba(201,168,76,0.35);
}
.cta-gold:hover {
    background: var(--gold-light);
    transform: translateY(-2px);
    box-shadow: 0 14px 40px rgba(201,168,76,0.5);
}

/* Scroll cue */
.scroll-cue {
    position: absolute;
    bottom: 2.5rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    z-index: 10;
}
.sc-label {
    font-size: 0.65rem;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: rgba(201,168,76,0.45);
}
.sc-bar {
    display: block;
    width: 1px;
    height: 48px;
    background: linear-gradient(to bottom, rgba(201,168,76,0.5), transparent);
    animation: scrollBar 2s ease-in-out infinite;
}
@keyframes scrollBar {
    0%   { opacity: 0; transform: scaleY(0); transform-origin: top; }
    50%  { opacity: 1; transform: scaleY(1); }
    100% { opacity: 0; transform: scaleY(1); transform-origin: bottom; }
}

/* Hero bottom fade */
.hero-fade-bottom {
    position: absolute;
    inset-inline: 0;
    bottom: 0;
    height: 120px;
    background: linear-gradient(to top, var(--cream), transparent);
    pointer-events: none;
    z-index: 5;
}

/* ═══════════════════════════════════════════
   SOBRE NÓS
═══════════════════════════════════════════ */
.sobre-sec {
    background: var(--cream);
    padding: 7rem 2rem;
}
.sobre-inner {
    display: grid;
    gap: 5rem;
    max-width: 1200px;
    margin-inline: auto;
    align-items: center;
}
@media(min-width:1024px) { .sobre-inner { grid-template-columns: 1fr 1fr; gap: 6rem; } }

/* Shared helpers */
.sec-label {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 0.75rem;
}
.sec-h2 {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: clamp(2.2rem, 5vw, 3.8rem);
    line-height: 1.12;
    letter-spacing: -0.01em;
}
.sec-h2.dark-h  { color: var(--ink); }
.sec-h2.light-h { color: var(--white); }
.sec-h2 em { font-style: italic; color: var(--gold); }

.sobre-rule {
    width: 48px;
    height: 2px;
    margin: 1.5rem 0;
    background: linear-gradient(90deg, var(--gold), var(--gold-light), transparent);
    border-radius: 2px;
}
.sobre-para {
    font-size: 1rem;
    line-height: 1.8;
    color: var(--ink-2);
}
.mt-4 { margin-top: 1rem; }

/* Stats */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-top: 2.5rem;
    padding-top: 2.5rem;
    border-top: 1px solid rgba(201,168,76,0.2);
}
.stat-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.stat-num {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 2.4rem;
    font-weight: 900;
    color: var(--gold);
    line-height: 1;
}
.stat-lbl {
    font-size: 0.78rem;
    color: var(--ink-2);
    font-weight: 500;
    line-height: 1.4;
}

/* Arrow link */
.link-arrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 2.5rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: 0.04em;
    padding: 0.75rem 1.75rem;
    border: 1.5px solid rgba(201,168,76,0.4);
    border-radius: 8px;
    transition: all 0.25s ease;
}
.link-arrow:hover {
    border-color: var(--gold);
    background: rgba(201,168,76,0.08);
    transform: translateY(-2px);
}
.link-arrow.dark-arrow { color: var(--ink); border-color: rgba(26,18,8,0.25); }
.link-arrow.dark-arrow:hover { background: rgba(26,18,8,0.06); }

/* Image */
.sobre-img-col { display: flex; flex-direction: column; gap: 2rem; align-items: center; }
.sobre-frame {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 30px 80px rgba(201,168,76,0.15), 0 0 0 1px rgba(201,168,76,0.15);
    max-width: 480px;
    width: 100%;
}
.sobre-img { width: 100%; object-fit: contain; border-radius: 20px; }

/* Corner accents */
.fc {
    position: absolute;
    width: 22px; height: 22px;
    border-color: rgba(201,168,76,0.7);
    border-style: solid;
}
.fc-tl { top: 12px; left: 12px; border-width: 2px 0 0 2px; border-radius: 4px 0 0 0; }
.fc-tr { top: 12px; right: 12px; border-width: 2px 2px 0 0; border-radius: 0 4px 0 0; }
.fc-bl { bottom: 12px; left: 12px; border-width: 0 0 2px 2px; border-radius: 0 0 0 4px; }
.fc-br { bottom: 12px; right: 12px; border-width: 0 2px 2px 0; border-radius: 0 0 4px 0; }

/* ═══════════════════════════════════════════
   EVENTOS
═══════════════════════════════════════════ */
.eventos-sec {
    background: var(--dark-2);
    padding: 7rem 2rem;
    border-top: 1px solid rgba(201,168,76,0.1);
}
.eventos-inner { max-width: 1200px; margin-inline: auto; }

.sec-header { text-align: center; margin-bottom: 4rem; }
.sec-rule {
    width: 56px;
    height: 2px;
    margin: 1.5rem auto 0;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
    border-radius: 2px;
}
.light-lbl { color: rgba(201,168,76,0.7); }

/* Grid */
.eventos-grid {
    display: grid;
    gap: 1.75rem;
    grid-template-columns: 1fr;
}
@media(min-width:640px)  { .eventos-grid { grid-template-columns: 1fr 1fr; } }
@media(min-width:1024px) { .eventos-grid { grid-template-columns: repeat(3, 1fr); } }

/* Card */
.ev-card {
    border-radius: 16px;
    overflow: hidden;
    background: rgba(255,253,248,0.03);
    border: 1px solid rgba(201,168,76,0.1);
    transition: transform 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease;
    cursor: pointer;
}
.ev-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 24px 64px rgba(0,0,0,0.55), 0 0 0 1px rgba(201,168,76,0.25);
    border-color: rgba(201,168,76,0.25);
}
.ev-img-wrap { position: relative; overflow: hidden; aspect-ratio: 4/3; }
.ev-img {
    width: 100%; height: 100%;
    object-fit: cover;
    object-position: center;
    transition: transform 0.6s ease;
}
.ev-card:hover .ev-img { transform: scale(1.06); }
.ev-img-grad {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(26,18,8,0.8) 0%, transparent 60%);
}
.ev-tag {
    position: absolute;
    top: 1rem; left: 1rem;
    padding: 0.3rem 0.75rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--gold);
    background: rgba(26,18,8,0.75);
    border: 1px solid rgba(201,168,76,0.35);
    border-radius: 100px;
    backdrop-filter: blur(8px);
}
.ev-body {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.ev-date {
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gold);
}
.ev-title {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--white);
    line-height: 1.3;
}
.ev-desc {
    font-size: 0.875rem;
    line-height: 1.6;
    color: rgba(255,253,248,0.55);
    flex: 1;
}
.ev-link {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    margin-top: 0.75rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--gold);
    transition: gap 0.2s;
}
.ev-card:hover .ev-link { gap: 0.625rem; }

.ev-more { text-align: center; margin-top: 3.5rem; }
.btn-outline-gold {
    display: inline-block;
    padding: 0.875rem 2.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--gold);
    border: 1.5px solid rgba(201,168,76,0.45);
    border-radius: 10px;
    letter-spacing: 0.04em;
    transition: all 0.3s ease;
}
.btn-outline-gold:hover {
    border-color: var(--gold);
    background: rgba(201,168,76,0.08);
    transform: translateY(-2px);
}

/* ═══════════════════════════════════════════
   PATROCINADORES
═══════════════════════════════════════════ */
.sponsors-sec {
    background: var(--cream-2);
    padding: 6rem 2rem;
    border-top: 1px solid rgba(201,168,76,0.12);
    overflow: hidden;
}
.sponsors-header {
    text-align: center;
    margin-bottom: 3.5rem;
    max-width: 600px;
    margin-inline: auto;
}

/* Marquee */
.marquee-wrap {
    overflow: hidden;
    padding: 1rem 0;
    margin-bottom: 3rem;
    -webkit-mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
    mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
}
.marquee-track {
    display: flex;
    gap: 0;
    width: max-content;
    animation: marquee 28s linear infinite;
}
@keyframes marquee {
    from { transform: translateX(0); }
    to   { transform: translateX(-33.333%); }
}
.marquee-wrap:hover .marquee-track { animation-play-state: paused; }

.sp-item {
    display: inline-flex;
    align-items: center;
    padding: 0.875rem 2.5rem;
    margin-right: 1.5rem;
    font-size: 0.85rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--ink-2);
    border: 1px solid rgba(201,168,76,0.3);
    border-radius: 100px;
    background: rgba(255,255,255,0.6);
    white-space: nowrap;
    transition: all 0.25s ease;
}
.sp-item:hover {
    border-color: var(--gold);
    background: rgba(201,168,76,0.08);
    color: var(--ink);
}
.sp-logo {
    max-height: 40px;
    max-width: 120px;
    object-fit: contain;
    filter: grayscale(1) opacity(0.7);
    transition: filter 0.25s ease;
}
.sp-item:hover .sp-logo {
    filter: grayscale(0) opacity(1);
}
.sp-cta {
    text-align: center;
    margin-top: 0.5rem;
}

/* ═══════════════════════════════════════════
   CONTACTO
═══════════════════════════════════════════ */
.contacto-sec {
    background: var(--dark);
    padding: 7rem 2rem;
    border-top: 1px solid rgba(201,168,76,0.1);
}
.contacto-inner { max-width: 1200px; margin-inline: auto; }
.contacto-sub {
    font-size: 1rem;
    line-height: 1.75;
    color: rgba(255,253,248,0.55);
    margin-top: 1.25rem;
    max-width: 520px;
    margin-inline: auto;
}
.contacto-grid {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: 1fr;
    margin-top: 3.5rem;
}
@media(min-width:640px)  { .contacto-grid { grid-template-columns: repeat(3, 1fr); } }

.ct-card {
    background: rgba(255,253,248,0.04);
    border: 1px solid rgba(201,168,76,0.12);
    border-radius: 16px;
    padding: 2.25rem 1.75rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    transition: transform 0.3s ease, border-color 0.3s ease, background 0.3s ease;
}
.ct-card:hover {
    transform: translateY(-6px);
    border-color: rgba(201,168,76,0.3);
    background: rgba(201,168,76,0.05);
}
.ct-icon {
    font-size: 2rem;
    line-height: 1;
    margin-bottom: 0.25rem;
}
.ct-label {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--gold);
}
.ct-val {
    font-size: 0.95rem;
    line-height: 1.6;
    color: rgba(255,253,248,0.72);
    word-break: break-word;
    transition: color 0.2s;
}
a.ct-val:hover { color: var(--gold); }

/* ═══════════════════════════════════════════
   FOOTER
═══════════════════════════════════════════ */
.site-footer {
    background: var(--dark-2);
    border-top: 1px solid rgba(201,168,76,0.12);
}
.footer-gold-bar {
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--gold), var(--gold-light), var(--gold), transparent);
}
.footer-inner {
    display: grid;
    grid-template-columns: 1fr;
    gap: 3rem;
    max-width: 1200px;
    margin-inline: auto;
    padding: 4.5rem 2rem 3rem;
}
@media(min-width:768px) {
    .footer-inner { grid-template-columns: 1.8fr 1fr 1fr; gap: 4rem; }
}

.ft-col { display: flex; flex-direction: column; gap: 1.25rem; }
.ft-brand { gap: 1.5rem; }

.ft-logo-wrap {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
}
.ft-logo { width: 40px; height: 40px; object-fit: contain; }
.ft-logo-name {
    font-family: 'Playfair Display', Georgia, serif;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: -0.01em;
}
.ft-tagline {
    font-size: 0.9rem;
    line-height: 1.7;
    color: rgba(255,253,248,0.45);
    max-width: 280px;
}

.ft-socials { display: flex; gap: 0.75rem; }
.ft-social-btn {
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    border: 1px solid rgba(201,168,76,0.25);
    color: rgba(201,168,76,0.6);
    transition: all 0.25s ease;
}
.ft-social-btn:hover {
    border-color: var(--gold);
    color: var(--gold);
    background: rgba(201,168,76,0.1);
    transform: translateY(-2px);
}

.ft-heading {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 0.25rem;
}
.ft-list {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}
.ft-list a, .ft-list :deep(a) {
    font-size: 0.9rem;
    color: rgba(255,253,248,0.5);
    transition: color 0.2s;
}
.ft-list a:hover, .ft-list :deep(a):hover { color: var(--gold); }

.footer-copy {
    border-top: 1px solid rgba(201,168,76,0.1);
    text-align: center;
    padding: 1.5rem 2rem;
    font-size: 0.8rem;
    color: rgba(255,253,248,0.3);
}
.footer-copy a {
    color: rgba(201,168,76,0.5);
    transition: color 0.2s;
}
.footer-copy a:hover { color: var(--gold); }

/* ═══════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════ */
@media(max-width:767px) {
    .desk-links { display: none; }
        .sponsors-sec { padding: 4rem 1.5rem; }
    .contacto-sec { padding: 4rem 1.5rem; }
    .site-footer  { padding: 3rem 1.5rem 1.5rem; }
    .footer-inner { grid-template-columns: 1fr; gap: 2rem; }
    .hero-title   { font-size: clamp(2.5rem, 12vw, 5rem); }
    .stats-grid   { grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
    .ev-grid      { grid-template-columns: 1fr; }
    .contacto-grid { grid-template-columns: 1fr; }
    .nav-actions  { gap: 0.5rem; }
}
@media(max-width:480px) {
    .hero-title   { font-size: clamp(2rem, 14vw, 3.5rem); }
    .cta-ghost, .cta-gold { padding: 0.75rem 1.5rem; font-size: 0.8rem; }
}
</style>
