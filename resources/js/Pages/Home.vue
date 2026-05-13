<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    upcomingEvents: Array,
    pastEvents: Array,
});

const canvasHost = ref(null);
const featured = computed(() => props.upcomingEvents?.[0] ?? null);
const upcoming = computed(() => props.upcomingEvents ?? []);
const archived = computed(() => props.pastEvents ?? []);

let renderer;
let scene;
let camera;
let animationFrame;
let observer;
let cleanup = () => {};

const initScene = async () => {
    if (!canvasHost.value) return;
    const THREE = await import('three');

    scene = new THREE.Scene();
    scene.fog = new THREE.Fog(0x061536, 8, 34);

    camera = new THREE.PerspectiveCamera(48, 1, 0.1, 100);
    camera.position.set(0, 1.2, 11);

    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    canvasHost.value.appendChild(renderer.domElement);

    const group = new THREE.Group();
    scene.add(group);

    scene.add(new THREE.AmbientLight(0xffffff, 0.7));
    const blueLight = new THREE.PointLight(0x3aa7ff, 120, 26);
    blueLight.position.set(-5, 4, 6);
    scene.add(blueLight);
    const goldLight = new THREE.PointLight(0xffc84a, 100, 22);
    goldLight.position.set(5, -1, 6);
    scene.add(goldLight);

    const grid = new THREE.GridHelper(28, 42, 0x2b78d0, 0x133d72);
    grid.position.y = -3.2;
    grid.material.transparent = true;
    grid.material.opacity = 0.38;
    group.add(grid);

    const ringMaterial = new THREE.MeshStandardMaterial({
        color: 0xf4b52a,
        emissive: 0x8f5b00,
        emissiveIntensity: 0.35,
        metalness: 0.7,
        roughness: 0.22,
    });

    [3.2, 4.45, 5.7].forEach((radius, index) => {
        const ring = new THREE.Mesh(new THREE.TorusGeometry(radius, 0.025, 12, 160), ringMaterial);
        ring.rotation.x = Math.PI / 2;
        ring.position.y = -1.3 - index * 0.18;
        group.add(ring);
    });

    const shield = new THREE.Group();
    const shieldShape = new THREE.Shape();
    shieldShape.moveTo(0, 1.8);
    shieldShape.lineTo(1.45, 1.15);
    shieldShape.lineTo(1.18, -1.3);
    shieldShape.quadraticCurveTo(0.55, -2.0, 0, -2.25);
    shieldShape.quadraticCurveTo(-0.55, -2.0, -1.18, -1.3);
    shieldShape.lineTo(-1.45, 1.15);
    shieldShape.lineTo(0, 1.8);
    const shieldMesh = new THREE.Mesh(
        new THREE.ExtrudeGeometry(shieldShape, { depth: 0.16, bevelEnabled: true, bevelSize: 0.045, bevelThickness: 0.04 }),
        new THREE.MeshStandardMaterial({ color: 0xf8fbff, metalness: 0.22, roughness: 0.28 }),
    );
    shieldMesh.position.z = -0.08;
    shield.add(shieldMesh);

    const stripe = new THREE.Mesh(
        new THREE.BoxGeometry(0.34, 4.4, 0.2),
        new THREE.MeshStandardMaterial({ color: 0x0b4ea2, emissive: 0x07306b, emissiveIntensity: 0.28 }),
    );
    stripe.rotation.z = -0.78;
    stripe.position.z = 0.08;
    shield.add(stripe);

    const crestText = new THREE.Mesh(
        new THREE.TorusGeometry(0.52, 0.025, 8, 64),
        new THREE.MeshStandardMaterial({ color: 0x0b4ea2, emissive: 0x0b4ea2, emissiveIntensity: 0.4 }),
    );
    crestText.position.set(0, 0.05, 0.17);
    shield.add(crestText);
    shield.position.set(-2.9, 0.1, 0);
    shield.rotation.y = 0.36;
    group.add(shield);

    const loader = new THREE.TextureLoader();
    const posterGroup = new THREE.Group();
    (upcoming.value ?? []).slice(0, 2).forEach((event, index) => {
        const texture = loader.load(event.poster);
        texture.colorSpace = THREE.SRGBColorSpace;
        const panel = new THREE.Mesh(
            new THREE.PlaneGeometry(2.45, 3.28),
            new THREE.MeshStandardMaterial({ map: texture, roughness: 0.35, metalness: 0.08 }),
        );
        panel.position.set(index === 0 ? 1.15 : 3.75, index === 0 ? 0.25 : -0.1, index === 0 ? 0 : -1.2);
        panel.rotation.y = index === 0 ? -0.28 : -0.48;
        panel.rotation.z = index === 0 ? 0.02 : -0.03;
        posterGroup.add(panel);

        const frame = new THREE.Mesh(
            new THREE.BoxGeometry(2.6, 3.42, 0.08),
            new THREE.MeshStandardMaterial({ color: 0xf4b52a, metalness: 0.72, roughness: 0.18 }),
        );
        frame.position.copy(panel.position);
        frame.position.z -= 0.08;
        frame.rotation.copy(panel.rotation);
        posterGroup.add(frame);
    });
    group.add(posterGroup);

    const particleGeometry = new THREE.BufferGeometry();
    const positions = new Float32Array(420 * 3);
    for (let i = 0; i < positions.length; i += 3) {
        positions[i] = (Math.random() - 0.5) * 22;
        positions[i + 1] = (Math.random() - 0.5) * 10;
        positions[i + 2] = (Math.random() - 0.5) * 18;
    }
    particleGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const particles = new THREE.Points(
        particleGeometry,
        new THREE.PointsMaterial({ color: 0x8fd6ff, size: 0.035, transparent: true, opacity: 0.75 }),
    );
    scene.add(particles);

    const resize = () => {
        const rect = canvasHost.value.getBoundingClientRect();
        renderer.setSize(rect.width, rect.height, false);
        camera.aspect = rect.width / Math.max(1, rect.height);
        camera.updateProjectionMatrix();
    };

    observer = new ResizeObserver(resize);
    observer.observe(canvasHost.value);
    resize();

    const clock = new THREE.Clock();
    const animate = () => {
        const elapsed = clock.getElapsedTime();
        group.rotation.y = Math.sin(elapsed * 0.22) * 0.08;
        shield.rotation.y = 0.36 + Math.sin(elapsed * 0.8) * 0.08;
        posterGroup.children.forEach((child, index) => {
            child.position.y += Math.sin(elapsed * 0.9 + index) * 0.0008;
        });
        particles.rotation.y = elapsed * 0.025;
        particles.rotation.x = Math.sin(elapsed * 0.2) * 0.08;
        renderer.render(scene, camera);
        animationFrame = requestAnimationFrame(animate);
    };
    animate();

    cleanup = () => {
        cancelAnimationFrame(animationFrame);
        observer?.disconnect();
        scene.traverse((object) => {
            object.geometry?.dispose?.();
            if (Array.isArray(object.material)) {
                object.material.forEach((material) => material.dispose?.());
            } else {
                object.material?.map?.dispose?.();
                object.material?.dispose?.();
            }
        });
        renderer?.dispose();
        renderer?.domElement?.remove();
    };
};

onMounted(initScene);
onBeforeUnmount(() => cleanup());
</script>

<template>
    <Head title="Associacao de Santana" />

    <main class="min-h-screen bg-[#04101f] text-white">
        <section class="relative min-h-screen overflow-hidden">
            <div ref="canvasHost" class="absolute inset-0" aria-hidden="true" />
            <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(4,16,31,0.96)_0%,rgba(4,16,31,0.74)_42%,rgba(4,16,31,0.34)_100%)]" />
            <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-[#04101f] to-transparent" />

            <nav class="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-5 py-5 lg:px-8">
                <Link href="/" class="flex items-center gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded bg-white text-base font-black text-[#0b4ea2]">AR</span>
                    <span class="text-sm font-black uppercase tracking-[0.24em] text-amber-300">ARDC Santana</span>
                </Link>
                <div class="flex items-center gap-2">
                    <Link :href="route('login')" class="rounded-md border border-white/25 px-4 py-2 text-sm font-bold text-white hover:bg-white hover:text-[#04101f]">Area reservada</Link>
                    <Link :href="route('pos.login')" class="hidden rounded-md bg-amber-400 px-4 py-2 text-sm font-black text-[#04101f] hover:bg-amber-300 sm:inline-flex">POS</Link>
                </div>
            </nav>

            <div class="relative z-10 mx-auto flex min-h-[calc(100vh-88px)] max-w-7xl items-center px-5 pb-16 lg:px-8">
                <div class="max-w-3xl">
                    <p class="mb-5 inline-flex rounded bg-amber-400 px-4 py-2 text-sm font-black uppercase tracking-[0.18em] text-[#04101f]">
                        Associacao Recreativa Desportiva Cultural
                    </p>
                    <h1 class="text-5xl font-black leading-none sm:text-7xl lg:text-8xl">
                        Santana em modo futuro
                    </h1>
                    <p class="mt-6 max-w-2xl text-xl font-semibold leading-relaxed text-cyan-100">
                        Uma casa da comunidade em Carvalhal Benfeito, com eventos, cultura, desporto, restaurante e noites que juntam geracoes.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="#eventos" class="rounded-md bg-amber-400 px-5 py-3 font-black text-[#04101f] hover:bg-amber-300">Proximos eventos</a>
                        <a href="#arquivo" class="rounded-md border border-white/30 px-5 py-3 font-black text-white hover:bg-white hover:text-[#04101f]">Arquivo</a>
                    </div>
                    <div v-if="featured" class="mt-10 grid max-w-2xl gap-3 sm:grid-cols-3">
                        <div class="border-l-2 border-amber-300 pl-4">
                            <div class="text-sm font-bold uppercase text-cyan-200">Destaque</div>
                            <div class="text-xl font-black">{{ featured.title }}</div>
                        </div>
                        <div class="border-l-2 border-cyan-300 pl-4">
                            <div class="text-sm font-bold uppercase text-cyan-200">Data</div>
                            <div class="text-xl font-black">{{ featured.date }}</div>
                        </div>
                        <div class="border-l-2 border-white pl-4">
                            <div class="text-sm font-bold uppercase text-cyan-200">Local</div>
                            <div class="text-xl font-black">{{ featured.subtitle }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="eventos" class="bg-[#eef5ff] py-16 text-slate-950">
            <div class="mx-auto max-w-7xl px-5 lg:px-8">
                <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="font-black uppercase tracking-[0.2em] text-[#0b4ea2]">Agenda</p>
                        <h2 class="mt-2 text-4xl font-black">Proximos Eventos</h2>
                    </div>
                    <p class="max-w-xl font-semibold text-slate-600">Cartazes, horarios e destaques da associacao.</p>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <article v-for="evento in upcoming" :key="evento.title" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                        <div class="grid gap-0 md:grid-cols-[260px_1fr]">
                            <img :src="evento.poster" :alt="evento.title" class="h-full min-h-80 w-full object-cover">
                            <div class="p-5 sm:p-6">
                                <div class="mb-4 flex flex-wrap items-center gap-2">
                                    <span class="rounded bg-[#0b4ea2] px-3 py-1 text-xs font-black uppercase tracking-[0.14em] text-white">{{ evento.badge }}</span>
                                    <span class="font-black text-amber-700">{{ evento.date }}</span>
                                </div>
                                <h3 class="text-3xl font-black">{{ evento.title }}</h3>
                                <p class="mt-1 font-bold text-slate-500">{{ evento.subtitle }} · {{ evento.location }}</p>
                                <p class="mt-4 leading-relaxed text-slate-700">{{ evento.description }}</p>

                                <div class="mt-5 space-y-3">
                                    <div v-for="linha in evento.schedule" :key="linha.day" class="rounded-md bg-slate-50 p-3 ring-1 ring-slate-200">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <strong class="text-[#0b4ea2]">{{ linha.day }}</strong>
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
                    <p class="font-black uppercase tracking-[0.2em] text-amber-300">Memoria</p>
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
                <span>Associacao Recreativa Desportiva Cultural de Santana</span>
                <span>Carvalhal Benfeito</span>
            </div>
        </footer>
    </main>
</template>
