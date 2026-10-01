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
        <main class="min-h-screen pb-16 pt-10 sm:pt-14">
            <section class="mx-auto w-full max-w-[760px] px-4 sm:px-6">
                <header class="mb-6 flex items-center gap-4">
                    <img v-if="evento.cartaz" :src="evento.cartaz" alt="" class="h-20 w-20 shrink-0 rounded-[14px] border border-linha bg-linha object-cover">
                    <div class="min-w-0">
                        <p class="m-0 text-[13px] font-bold uppercase tracking-[0.12em] text-verde">Partilha as tuas fotos</p>
                        <h1 class="m-0 mt-1 text-[clamp(28px,3.6vw,38px)] font-extrabold leading-[1.1] tracking-[-0.01em]">{{ evento.titulo }}</h1>
                        <p v-if="evento.subtitulo || evento.data" class="m-0 mt-1 text-[15px] text-suave">{{ [evento.data, evento.subtitulo].filter(Boolean).join(' · ') }}</p>
                    </div>
                </header>

                <!-- Sucesso -->
                <div v-if="concluido" class="flex flex-col items-center rounded-[14px] border border-linha bg-white p-8 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-verde-claro text-verde">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L19 7" /></svg>
                    </span>
                    <h2 class="m-0 mt-4 text-2xl font-extrabold">Obrigado, {{ nome.split(' ')[0] }}!</h2>
                    <p class="m-0 mt-2 text-base text-suave">
                        Recebemos <template v-if="enviadas">{{ enviadas }} foto(s)</template><template v-if="enviadas && videosEnviados"> e </template><template v-if="videosEnviados">{{ videosEnviados }} link(s) de vídeo</template>.
                        As fotos aparecem na página do evento depois de aprovadas; os vídeos são descarregados e publicados pela associação.
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <button type="button" class="inline-flex h-[52px] items-center rounded-[10px] bg-verde px-[22px] text-base font-bold text-white transition hover:bg-verde-escuro" @click="recomecar">Enviar mais fotos</button>
                        <Link :href="route('eventos.public.show', evento.id)" class="inline-flex h-[52px] items-center rounded-[10px] border border-linha-forte bg-white px-[22px] text-base font-bold text-tinta no-underline transition hover:border-verde hover:text-verde">Ver o evento</Link>
                    </div>
                </div>

                <form v-else class="space-y-5 rounded-[14px] border border-linha bg-white p-5 sm:p-6" @submit.prevent="enviar">
                    <p class="m-0 text-base text-suave">Estiveste no evento? Envia-nos as tuas fotografias e os links dos teus vídeos. Depois de aprovados pela associação, ficam na galeria do evento.</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="flex flex-col gap-1.5 text-[15px] font-bold">
                            O teu nome *
                            <input v-model="nome" required maxlength="120" :disabled="aEnviar" class="campo" placeholder="Nome">
                        </label>
                        <label class="flex flex-col gap-1.5 text-[15px] font-bold">
                            <span>Email ou telefone <span class="font-normal text-suave-2">(opcional)</span></span>
                            <input v-model="contacto" maxlength="160" :disabled="aEnviar" class="campo" placeholder="Para te contactarmos, se for preciso">
                        </label>
                    </div>

                    <div>
                        <button
                            type="button"
                            class="flex w-full flex-col items-center justify-center gap-1.5 rounded-[14px] border-2 border-dashed border-verde bg-verde-claro px-4 py-7 text-center transition hover:bg-verde-claro2 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="aEnviar || fotos.length >= maxFotos"
                            @click="inputFotos?.click()"
                        >
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-verde text-white">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z" /><circle cx="12" cy="13" r="3.5" /></svg>
                            </span>
                            <span class="text-lg font-extrabold text-verde-escuro">Escolher fotografias</span>
                            <span class="text-sm text-suave">Até {{ maxFotos }} fotos · JPG, PNG ou WEBP · vídeos só por link (em baixo)</span>
                        </button>
                        <input ref="inputFotos" type="file" accept="image/*" multiple class="hidden" @change="escolher">
                    </div>

                    <TransitionGroup v-if="fotos.length" tag="div" class="grid grid-cols-3 gap-2 sm:grid-cols-4" enter-active-class="transition duration-200" enter-from-class="opacity-0 scale-95" leave-active-class="transition duration-150" leave-to-class="opacity-0 scale-95">
                        <div v-for="foto in fotos" :key="foto.id" class="relative overflow-hidden rounded-[10px] border border-linha bg-linha">
                            <img :src="foto.preview" alt="" class="aspect-square w-full object-cover" :class="{ 'opacity-50': foto.estado === 'a-enviar' }">
                            <button
                                v-if="!aEnviar && foto.estado !== 'enviada'"
                                type="button"
                                class="absolute right-1.5 top-1.5 flex h-9 w-9 items-center justify-center rounded-full bg-escuro/80 text-white"
                                aria-label="Remover"
                                @click="remover(foto)"
                            ><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg></button>
                            <span v-if="foto.estado === 'a-enviar'" class="absolute inset-x-0 bottom-0 bg-laranja py-1 text-center text-xs font-bold text-white">A enviar…</span>
                            <span v-else-if="foto.estado === 'enviada'" class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 bg-verde-ok py-1 text-center text-xs font-bold text-white">
                                Enviada
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L19 7" /></svg>
                            </span>
                            <span v-else-if="foto.estado === 'erro'" class="absolute inset-x-0 bottom-0 bg-perigo px-1 py-1 text-center text-[11px] font-bold leading-tight text-white" :title="foto.erro">{{ foto.erro }}</span>
                        </div>
                    </TransitionGroup>

                    <!-- Vídeos: só links -->
                    <div class="rounded-[14px] border border-[#C9D6F0] bg-[#EEF3FC] p-4 sm:p-5">
                        <p class="m-0 flex items-center gap-2 text-[17px] font-extrabold">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="text-azul"><rect x="2" y="6" width="14" height="12" rx="2" /><path d="M16 10l6-3v10l-6-3z" /></svg>
                            Tens vídeos?
                        </p>
                        <p class="m-0 mt-1 text-sm text-suave">Para não enviares ficheiros pesados, partilha só o <b>link</b> (Google Drive, WeTransfer, YouTube, Dropbox, OneDrive…). Confirma que o link está aberto a quem o tiver. A associação descarrega e publica o vídeo.</p>
                        <div class="mt-3 space-y-2">
                            <div v-for="video in videos" :key="video.id">
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="video.url"
                                        type="url"
                                        inputmode="url"
                                        maxlength="2048"
                                        :disabled="aEnviar || video.estado === 'enviado'"
                                        class="campo min-w-0 flex-1"
                                        :class="{ '!border-perigo': video.estado === 'erro', '!bg-verde-claro': video.estado === 'enviado' }"
                                        placeholder="https://drive.google.com/…"
                                        @input="video.estado === 'erro' && (video.estado = 'pronto')"
                                    >
                                    <span v-if="video.estado === 'enviado'" class="flex shrink-0 items-center gap-1 text-sm font-bold text-verde">
                                        Enviado
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L19 7" /></svg>
                                    </span>
                                    <span v-else-if="video.estado === 'a-enviar'" class="shrink-0 text-sm font-bold text-laranja-texto">A enviar…</span>
                                    <button v-else-if="videos.length > 1 && !aEnviar" type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-linha bg-white text-tinta hover:bg-fundo" aria-label="Remover link" @click="removerVideo(video)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg></button>
                                </div>
                                <p v-if="video.estado === 'erro'" class="m-0 mt-1 text-sm font-bold text-perigo">{{ video.erro }}</p>
                            </div>
                        </div>
                        <button v-if="videos.length < maxVideos && !aEnviar" type="button" class="mt-3 inline-flex h-11 items-center gap-2 rounded-[10px] border border-[#C9D6F0] bg-white px-4 text-[15px] font-bold text-azul hover:bg-[#F6F9FE]" @click="adicionarVideo">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            Adicionar outro link
                        </button>
                    </div>

                    <div v-if="aEnviar" class="h-2 overflow-hidden rounded-full bg-verde-claro" role="progressbar" :aria-valuenow="progresso" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full bg-verde transition-all" :style="{ width: `${progresso}%` }"></div>
                    </div>

                    <label class="flex cursor-pointer items-start gap-3 rounded-[10px] bg-fundo p-3.5 text-[15px] text-suave">
                        <input v-model="autorizo" type="checkbox" :disabled="aEnviar" class="mt-0.5 h-5 w-5 shrink-0 rounded border-linha-forte text-verde focus:ring-verde">
                        <span>Confirmo que as fotos e vídeos são meus e autorizo a ARDC Santana a publicá-los no site e nas redes sociais da associação. As pessoas que aparecem concordam com a publicação.
                            <Link :href="route('legal.privacidade')" class="font-bold text-verde underline">Política de privacidade</Link>.
                        </span>
                    </label>

                    <p v-if="erroGeral" class="m-0 rounded-[10px] bg-perigo-claro p-3.5 text-[15px] font-bold text-perigo-texto" role="alert">{{ erroGeral }}</p>

                    <button
                        type="submit"
                        class="h-[60px] w-full rounded-[10px] bg-verde px-6 text-lg font-extrabold text-white transition hover:bg-verde-escuro disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="aEnviar || (!pendentes.length && !videosPreenchidos.length)"
                    >
                        {{ textoBotao }}
                    </button>
                    <p class="m-0 text-center text-[13px] text-suave-2">Nada fica visível sem ser aprovado pela associação.</p>
                </form>
            </section>
        </main>
    </PublicShell>
</template>

<style scoped>
.campo {
    width: 100%;
    height: 50px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid #D5D9D3;
    background: #FFFFFF;
    color: #16201C;
    font-size: 16px;
    font-weight: 400;
}
.campo:focus { border-color: #0F6B4F; box-shadow: 0 0 0 3px rgb(15 107 79 / 0.15); outline: none; }
.campo:disabled { background: #F4F5F2; }
</style>
