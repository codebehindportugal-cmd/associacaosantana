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
const maxVideos = 5;
const videos = ref([{ id: 1, url: '', estado: 'pronto', erro: '' }]); // estado: pronto | a-enviar | enviado | erro
let seqVideo = 1;
const videosPreenchidos = computed(() => videos.value.filter((v) => v.url.trim() && v.estado !== 'enviado'));
const videosEnviados = computed(() => videos.value.filter((v) => v.estado === 'enviado').length);
const adicionarVideo = () => {
    if (videos.value.length < maxVideos) videos.value.push({ id: ++seqVideo, url: '', estado: 'pronto', erro: '' });
};
const removerVideo = (video) => {
    videos.value = videos.value.filter((v) => v.id !== video.id);
    if (!videos.value.length) adicionarVideo();
};
const linkValido = (url) => /^https?:\/\/\S+\.\S+/i.test(url.trim());

const pendentes = computed(() => fotos.value.filter((f) => f.estado !== 'enviada'));
const enviadas = computed(() => fotos.value.filter((f) => f.estado === 'enviada').length);
const textoBotao = computed(() => {
    if (aEnviar.value) return 'A enviar…';
    const partes = [];
    if (pendentes.value.length) partes.push(`${pendentes.value.length} foto(s)`);
    if (videosPreenchidos.value.length) partes.push(`${videosPreenchidos.value.length} vídeo(s)`);
    return partes.length ? `Enviar ${partes.join(' e ')}` : 'Enviar';
});
const progresso = computed(() => (fotos.value.length ? Math.round((enviadas.value / fotos.value.length) * 100) : 0));

let seq = 0;
const escolher = (event) => {
    erroGeral.value = '';
    const todos = Array.from(event.target.files ?? []);
    const novos = todos.filter((f) => f.type.startsWith('image/') || /\.(heic|heif)$/i.test(f.name));
    if (todos.some((f) => f.type.startsWith('video/'))) {
        erroGeral.value = 'Vídeos não podem ser enviados como ficheiro — cola o link do vídeo mais abaixo.';
    }
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

const enviarVideo = async (video) => {
    video.estado = 'a-enviar';
    video.erro = '';
    try {
        const data = new FormData();
        data.append('video_url', video.url.trim());
        data.append('nome', nome.value.trim());
        data.append('contacto', contacto.value.trim());
        data.append('autorizo', autorizo.value ? '1' : '0');
        data.append('recaptcha_token', await obterToken('enviar_video'));

        const res = await fetch(route('eventos.fotos-publico.video', props.evento.id), {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: data,
        });
        if (res.ok) { video.estado = 'enviado'; return; }
        const corpo = await res.json().catch(() => ({}));
        const erros = corpo.errors ? Object.values(corpo.errors).flat() : [];
        video.erro = erros[0] || (res.status === 429 ? 'Muitos envios seguidos — espera um minuto.' : '') || corpo.message || `Erro ${res.status}`;
        video.estado = 'erro';
    } catch {
        video.estado = 'erro';
        video.erro = 'Sem ligação. Tenta novamente.';
    }
};

const enviar = async () => {
    erroGeral.value = '';
    if (!nome.value.trim()) { erroGeral.value = 'Indica o teu nome.'; return; }
    if (!pendentes.value.length && !videosPreenchidos.value.length) { erroGeral.value = 'Escolhe pelo menos uma fotografia ou cola o link de um vídeo.'; return; }
    const invalido = videosPreenchidos.value.find((v) => !linkValido(v.url));
    if (invalido) { invalido.estado = 'erro'; invalido.erro = 'Link inválido (deve começar por https://).'; erroGeral.value = 'Verifica os links dos vídeos.'; return; }
    if (!autorizo.value) { erroGeral.value = 'É preciso autorizar a publicação.'; return; }

    aEnviar.value = true;
    for (const foto of pendentes.value) {
        // eslint-disable-next-line no-await-in-loop
        await enviarUma(foto);
    }
    for (const video of videosPreenchidos.value) {
        // eslint-disable-next-line no-await-in-loop
        await enviarVideo(video);
    }
    aEnviar.value = false;

    const fotosOk = fotos.value.every((f) => f.estado === 'enviada');
    const videosOk = videos.value.every((v) => v.estado === 'enviado' || !v.url.trim());
    if (fotosOk && videosOk) {
        concluido.value = true;
    } else {
        erroGeral.value = 'Alguns envios falharam. Podes tentar outra vez.';
    }
};

const recomecar = () => {
    fotos.value.forEach((f) => URL.revokeObjectURL(f.preview));
    fotos.value = [];
    videos.value = [{ id: ++seqVideo, url: '', estado: 'pronto', erro: '' }];
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
                    <p class="mt-2 text-stone-600">
                        Recebemos <template v-if="enviadas">{{ enviadas }} foto(s)</template><template v-if="enviadas && videosEnviados"> e </template><template v-if="videosEnviados">{{ videosEnviados }} link(s) de vídeo</template>.
                        As fotos aparecem na página do evento depois de aprovadas; os vídeos são descarregados e publicados pela associação.
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <button type="button" class="rounded-lg bg-amber-600 px-5 py-3 font-bold text-white hover:bg-amber-700" @click="recomecar">Enviar mais fotos</button>
                        <Link :href="route('eventos.public.show', evento.id)" class="rounded-lg border border-amber-300 px-5 py-3 font-bold text-amber-800 hover:bg-amber-100">Ver o evento</Link>
                    </div>
                </div>

                <form v-else class="space-y-5 rounded-xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6" @submit.prevent="enviar">
                    <p class="text-sm text-stone-600">Estiveste no evento? Envia-nos as tuas fotografias e os links dos teus vídeos. Depois de aprovados pela associação, ficam na galeria do evento.</p>

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
                            <span class="text-xs text-stone-500">Até {{ maxFotos }} fotos · JPG, PNG ou WEBP · vídeos só por link (em baixo)</span>
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

                    <!-- Vídeos: só links -->
                    <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                        <p class="font-bold text-stone-800">🎬 Tens vídeos?</p>
                        <p class="mt-0.5 text-xs text-stone-600">Para não enviares ficheiros pesados, partilha só o <b>link</b> (Google Drive, WeTransfer, YouTube, Dropbox, OneDrive…). Confirma que o link está aberto a quem o tiver. A associação descarrega e publica o vídeo.</p>
                        <div class="mt-3 space-y-2">
                            <div v-for="video in videos" :key="video.id">
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="video.url"
                                        type="url"
                                        inputmode="url"
                                        maxlength="2048"
                                        :disabled="aEnviar || video.estado === 'enviado'"
                                        class="min-w-0 flex-1 rounded-md border-sky-300 text-sm"
                                        :class="{ 'border-red-400': video.estado === 'erro', 'bg-emerald-50': video.estado === 'enviado' }"
                                        placeholder="https://drive.google.com/…"
                                        @input="video.estado === 'erro' && (video.estado = 'pronto')"
                                    >
                                    <span v-if="video.estado === 'enviado'" class="text-sm font-bold text-emerald-700">Enviado ✓</span>
                                    <span v-else-if="video.estado === 'a-enviar'" class="text-sm font-bold text-amber-700">A enviar…</span>
                                    <button v-else-if="videos.length > 1 && !aEnviar" type="button" class="grid h-8 w-8 place-items-center rounded-full text-lg font-bold text-stone-500 hover:bg-sky-100" aria-label="Remover link" @click="removerVideo(video)">×</button>
                                </div>
                                <p v-if="video.estado === 'erro'" class="mt-1 text-xs font-bold text-red-600">{{ video.erro }}</p>
                            </div>
                        </div>
                        <button v-if="videos.length < maxVideos && !aEnviar" type="button" class="mt-2 text-sm font-bold text-sky-700 underline" @click="adicionarVideo">+ Adicionar outro link</button>
                    </div>

                    <div v-if="aEnviar" class="h-2 overflow-hidden rounded-full bg-amber-100">
                        <div class="h-full bg-amber-600 transition-all" :style="{ width: `${progresso}%` }"></div>
                    </div>

                    <label class="flex items-start gap-2 text-sm text-stone-600">
                        <input v-model="autorizo" type="checkbox" :disabled="aEnviar" class="mt-0.5 rounded border-stone-300 text-amber-600">
                        <span>Confirmo que as fotos e vídeos são meus e autorizo a ARDC Santana a publicá-los no site e nas redes sociais da associação. As pessoas que aparecem concordam com a publicação.
                            <Link :href="route('legal.privacidade')" class="font-semibold text-amber-700 underline">Política de privacidade</Link>.
                        </span>
                    </label>

                    <p v-if="erroGeral" class="rounded-md bg-red-50 p-3 text-sm font-bold text-red-700">{{ erroGeral }}</p>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-amber-600 px-6 py-4 text-lg font-black text-white shadow-md transition hover:bg-amber-700 disabled:opacity-60"
                        :disabled="aEnviar || (!pendentes.length && !videosPreenchidos.length)"
                    >
                        {{ textoBotao }}
                    </button>
                    <p class="text-center text-xs text-stone-400">Nada fica visível sem ser aprovado pela associação.</p>
                </form>
            </section>
        </main>
    </PublicShell>
</template>
