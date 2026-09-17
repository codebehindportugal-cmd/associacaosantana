<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import PublicShell from '@/Components/PublicShell.vue';
import { useRecaptcha } from '@/Composables/useRecaptcha';

const props = defineProps({
    evento: Object,
    maxFotos: { type: Number, default: 20 },
});

const { obterToken } = useRecaptcha();

const nome = ref('');
const contacto = ref('');
const autorizo = ref(false);
const fotos = ref([]); // { id, file, preview, estado: 'pronta'|'a-enviar'|'enviada'|'erro', erro }
const aEnviar = ref(false);
const concluido = ref(false);
const erroGeral = ref('');
const inputFotos = ref(null);

const pendentes = computed(() => fotos.value.filter((f) => f.estado !== 'enviada'));
const enviadas = computed(() => fotos.value.filter((f) => f.estado === 'enviada').length);
const progresso = computed(() => (fotos.value.length ? Math.round((enviadas.value / fotos.value.length) * 100) : 0));

let seq = 0;
const escolher = (event) => {
    erroGeral.value = '';
    const novos = Array.from(event.target.files ?? []).filter((f) => f.type.startsWith('image/') || /\.(heic|heif)$/i.test(f.name));
    const espaco = props.maxFotos - fotos.value.length;
    if (novos.length > espaco) erroGeral.value = `Podes enviar no máximo ${props.maxFotos} fotos de cada vez.`;
    novos.slice(0, Math.max(0, espaco)).forEach((file) => {
        fotos.value.push({ id: ++seq, file, preview: URL.createObjectURL(file), estado: 'pronta', erro: '' });
    });
    event.target.value = '';
};

const remover = (foto) => {
    URL.revokeObjectURL(foto.preview);
    fotos.value = fotos.value.filter((f) => f.id !== foto.id);
};

onBeforeUnmount(() => fotos.value.forEach((f) => URL.revokeObjectURL(f.preview)));

// Reduz a foto no telemóvel antes de enviar (mais rápido e evita limites de upload)
const comprimir = async (file, maxLado = 2400, qualidade = 0.85) => {
    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const escala = Math.min(1, maxLado / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * escala);
        canvas.height = Math.round(bitmap.height * escala);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', qualidade));
        if (!blob) return file;
        const base = file.name.replace(/\.[^.]+$/, '') || 'foto';
        return new File([blob], `${base}.jpg`, { type: 'image/jpeg' });
    } catch {
        return file;
    }
};

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const enviarUma = async (foto) => {
    foto.estado = 'a-enviar';
    foto.erro = '';
    try {
        const ficheiro = await comprimir(foto.file);
        const data = new FormData();
        data.append('foto', ficheiro);
        data.append('nome', nome.value.trim());
        data.append('contacto', contacto.value.trim());
        data.append('autorizo', autorizo.value ? '1' : '0');
        data.append('recaptcha_token', await obterToken('enviar_fotos'));

        const res = await fetch(route('eventos.fotos-publico.store', props.evento.id), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: data,
        });

        if (res.ok) {
            foto.estado = 'enviada';
            return;
        }

        const corpo = await res.json().catch(() => ({}));
        const erros = corpo.errors ? Object.values(corpo.errors).flat() : [];
        foto.erro = erros[0]
            || (res.status === 413 ? 'Fotografia demasiado grande.' : '')
            || (res.status === 429 ? 'Muitos envios seguidos — espera um minuto e tenta de novo.' : '')
            || (res.status === 419 ? 'A sessão expirou — atualiza a página.' : '')
            || corpo.message
            || `Erro ${res.status}`;
        foto.estado = 'erro';
    } catch {
        foto.estado = 'erro';
        foto.erro = 'Sem ligação. Tenta novamente.';
    }
};

const enviar = async () => {
    erroGeral.value = '';
    if (!nome.value.trim()) { erroGeral.value = 'Indica o teu nome.'; return; }
    if (!pendentes.value.length) { erroGeral.value = 'Escolhe pelo menos uma fotografia.'; return; }
    if (!autorizo.value) { erroGeral.value = 'É preciso autorizar a publicação das fotos.'; return; }

    aEnviar.value = true;
    for (const foto of pendentes.value) {
        // eslint-disable-next-line no-await-in-loop
        await enviarUma(foto);
    }
    aEnviar.value = false;

    if (fotos.value.every((f) => f.estado === 'enviada')) {
        concluido.value = true;
    } else {
        erroGeral.value = 'Algumas fotos não foram enviadas. Podes tentar outra vez.';
    }
};

const recomecar = () => {
    fotos.value.forEach((f) => URL.revokeObjectURL(f.preview));
    fotos.value = [];
    concluido.value = false;
};
</script>

<template>
    <Head :title="`Enviar fotos · ${evento.titulo}`">
        <meta head-key="robots" name="robots" content="noindex">
    </Head>

    <PublicShell>
        <main class="min-h-screen px-4 py-10">
            <section class="mx-auto max-w-2xl">
                <header class="mb-6 flex items-center gap-4">
                    <img v-if="evento.cartaz" :src="evento.cartaz" alt="" class="h-20 w-20 shrink-0 rounded-xl border border-amber-200 object-cover">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-700">Partilha as tuas fotos</p>
                        <h1 class="mt-1 text-3xl font-bold text-stone-800">{{ evento.titulo }}</h1>
                        <p v-if="evento.subtitulo || evento.data" class="text-sm text-stone-500">{{ [evento.data, evento.subtitulo].filter(Boolean).join(' · ') }}</p>
                    </div>
                </header>

                <!-- Sucesso -->
                <div v-if="concluido" class="rounded-xl border border-emerald-200 bg-white p-8 text-center shadow-sm">
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-100 text-2xl text-emerald-700">✓</div>
                    <h2 class="mt-4 text-2xl font-bold text-stone-800">Obrigado, {{ nome.split(' ')[0] }}!</h2>
                    <p class="mt-2 text-stone-600">Recebemos {{ enviadas }} foto(s). Vão aparecer na página do evento depois de serem aprovadas pela associação.</p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <button type="button" class="rounded-lg bg-amber-600 px-5 py-3 font-bold text-white hover:bg-amber-700" @click="recomecar">Enviar mais fotos</button>
                        <Link :href="route('eventos.public.show', evento.id)" class="rounded-lg border border-amber-300 px-5 py-3 font-bold text-amber-800 hover:bg-amber-100">Ver o evento</Link>
                    </div>
                </div>

                <form v-else class="space-y-5 rounded-xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6" @submit.prevent="enviar">
                    <p class="text-sm text-stone-600">Estiveste no evento? Envia-nos as tuas fotografias. Depois de aprovadas pela associação, ficam na galeria do evento.</p>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm font-bold text-stone-700">
                            O teu nome *
                            <input v-model="nome" required maxlength="120" :disabled="aEnviar" class="mt-1 w-full rounded-md border-stone-300" placeholder="Nome">
                        </label>
                        <label class="block text-sm font-bold text-stone-700">
                            Email ou telefone <span class="font-normal text-stone-400">(opcional)</span>
                            <input v-model="contacto" maxlength="160" :disabled="aEnviar" class="mt-1 w-full rounded-md border-stone-300" placeholder="Para te contactarmos, se for preciso">
                        </label>
                    </div>

                    <div>
                        <button
                            type="button"
                            class="flex w-full flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-amber-300 bg-amber-50 px-4 py-8 text-center transition hover:bg-amber-100 disabled:opacity-50"
                            :disabled="aEnviar || fotos.length >= maxFotos"
                            @click="inputFotos?.click()"
                        >
                            <span class="text-3xl">📷</span>
                            <span class="font-bold text-amber-800">Escolher fotografias</span>
                            <span class="text-xs text-stone-500">Até {{ maxFotos }} fotos · JPG, PNG ou WEBP</span>
                        </button>
                        <input ref="inputFotos" type="file" accept="image/*" multiple class="hidden" @change="escolher">
                    </div>

                    <div v-if="fotos.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        <div v-for="foto in fotos" :key="foto.id" class="relative overflow-hidden rounded-lg border border-amber-200 bg-stone-100">
                            <img :src="foto.preview" alt="" class="aspect-square w-full object-cover" :class="{ 'opacity-50': foto.estado === 'a-enviar' }">
                            <button
                                v-if="!aEnviar && foto.estado !== 'enviada'"
                                type="button"
                                class="absolute right-1 top-1 grid h-7 w-7 place-items-center rounded-full bg-stone-900/70 text-sm font-bold text-white"
                                aria-label="Remover"
                                @click="remover(foto)"
                            >×</button>
                            <span v-if="foto.estado === 'a-enviar'" class="absolute inset-x-0 bottom-0 bg-amber-600/90 py-1 text-center text-xs font-bold text-white">A enviar…</span>
                            <span v-else-if="foto.estado === 'enviada'" class="absolute inset-x-0 bottom-0 bg-emerald-600/90 py-1 text-center text-xs font-bold text-white">Enviada ✓</span>
                            <span v-else-if="foto.estado === 'erro'" class="absolute inset-x-0 bottom-0 bg-red-600/90 px-1 py-1 text-center text-[11px] font-bold leading-tight text-white" :title="foto.erro">{{ foto.erro }}</span>
                        </div>
                    </div>

                    <div v-if="aEnviar" class="h-2 overflow-hidden rounded-full bg-amber-100">
                        <div class="h-full bg-amber-600 transition-all" :style="{ width: `${progresso}%` }"></div>
                    </div>

                    <label class="flex items-start gap-2 text-sm text-stone-600">
                        <input v-model="autorizo" type="checkbox" :disabled="aEnviar" class="mt-0.5 rounded border-stone-300 text-amber-600">
                        <span>Confirmo que as fotos são minhas e autorizo a ARDC Santana a publicá-las no site e nas redes sociais da associação. As pessoas que aparecem nas fotos concordam com a publicação.
                            <Link :href="route('legal.privacidade')" class="font-semibold text-amber-700 underline">Política de privacidade</Link>.
                        </span>
                    </label>

                    <p v-if="erroGeral" class="rounded-md bg-red-50 p-3 text-sm font-bold text-red-700">{{ erroGeral }}</p>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-amber-600 px-6 py-4 text-lg font-black text-white shadow-md transition hover:bg-amber-700 disabled:opacity-60"
                        :disabled="aEnviar || !pendentes.length"
                    >
                        {{ aEnviar ? `A enviar ${enviadas + 1} de ${fotos.length}…` : (pendentes.length ? `Enviar ${pendentes.length} foto(s)` : 'Enviar fotos') }}
                    </button>
                    <p class="text-center text-xs text-stone-400">As fotos só ficam visíveis depois de aprovadas pela associação.</p>
                </form>
            </section>
        </main>
    </PublicShell>
</template>
