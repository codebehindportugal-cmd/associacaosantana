<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    evento: Object,
    fotosPendentes: { type: Array, default: () => [] },
});

const linkCopiado = ref(false);
const copiarLink = async () => {
    try {
        await navigator.clipboard.writeText(props.evento.url_envio_fotos);
        linkCopiado.value = true;
        setTimeout(() => { linkCopiado.value = false; }, 2000);
    } catch { window.prompt('Copia o link:', props.evento.url_envio_fotos); }
};
const fotoAberta = ref(null);
const fotosPorAprovar = computed(() => props.fotosPendentes.filter((f) => f.tipo !== 'link'));
const videosPorTratar = computed(() => props.fotosPendentes.filter((f) => f.tipo === 'link'));
const tratarVideo = (video) => {
    if (!confirm('Já carregaste este vídeo no site? O link sai da lista.')) return;
    router.delete(route('eventos.media.destroy', video.id), { preserveScroll: true });
};
const rejeitarVideo = (video) => {
    if (!confirm('Ignorar este vídeo e remover o link da lista?')) return;
    router.delete(route('eventos.media.destroy', video.id), { preserveScroll: true });
};
const aprovarFoto = (foto) => router.post(route('eventos.media.aprovar', foto.id), {}, { preserveScroll: true });
const rejeitarFoto = (foto) => {
    if (!confirm('Rejeitar e apagar esta foto?')) return;
    router.delete(route('eventos.media.destroy', foto.id), { preserveScroll: true });
};
const aprovarTodas = () => {
    if (!confirm(`Aprovar e publicar as ${fotosPorAprovar.value.length} fotos pendentes?`)) return;
    router.post(route('eventos.fotos-publico.aprovar-todas', props.evento.id), {}, { preserveScroll: true });
};

const linhasPrograma = (evento) => (evento.programa ?? [])
    .flatMap((grupo) => grupo.items ?? [])
    .join('\n');

const form = useForm({
    titulo: props.evento.titulo ?? '',
    subtitulo: props.evento.subtitulo ?? '',
    data_inicio: props.evento.data_inicio ?? '',
    data_fim: props.evento.data_fim ?? '',
    periodo: props.evento.periodo ?? '',
    localizacao: props.evento.localizacao ?? '',
    badge: props.evento.badge ?? '',
    descricao: props.evento.descricao ?? '',
    facebook_post_url: props.evento.facebook_post_url ?? '',
    link_externo_url: props.evento.link_externo_url ?? '',
    link_externo_texto: props.evento.link_externo_texto ?? '',
    estado: props.evento.estado ?? 'publicado',
    destaque: Boolean(props.evento.destaque),
    fotos_publico_ativo: Boolean(props.evento.fotos_publico_ativo),
    ordem: props.evento.ordem ?? 0,
    programa_texto: linhasPrograma(props.evento),
    cartaz: null,
    inscricoes_ativas: Boolean(props.evento.inscricoes_ativas),
    inscricoes_limite: props.evento.inscricoes_limite ?? '',
    inscricoes_opcoes_texto: (props.evento.inscricoes_opcoes ?? [])
        .map((o) => (typeof o === 'string' ? o : (o.preco !== null && o.preco !== undefined ? `${o.nome} = ${o.preco}` : o.nome)))
        .join('\n'),
    inscricoes_pede_idades: Boolean(props.evento.inscricoes_pede_idades),
    inscricoes_preco: props.evento.inscricoes_preco ?? '',
    inscricoes_preco_crianca: props.evento.inscricoes_preco_crianca ?? '',
    inscricoes_idade_crianca: props.evento.inscricoes_idade_crianca ?? '',
    inscricoes_pagamento_online: Boolean(props.evento.inscricoes_pagamento_online),
});

const guardar = () => {
    form
        .transform((data) => ({
            ...data,
            destaque: data.destaque ? 1 : 0,
            fotos_publico_ativo: data.fotos_publico_ativo ? 1 : 0,
            inscricoes_ativas: data.inscricoes_ativas ? 1 : 0,
            inscricoes_pede_idades: data.inscricoes_pede_idades ? 1 : 0,
            inscricoes_limite: data.inscricoes_limite === '' ? null : data.inscricoes_limite,
            inscricoes_preco: data.inscricoes_preco === '' ? null : data.inscricoes_preco,
            inscricoes_preco_crianca: data.inscricoes_preco_crianca === '' ? null : data.inscricoes_preco_crianca,
            inscricoes_idade_crianca: data.inscricoes_idade_crianca === '' ? null : data.inscricoes_idade_crianca,
            inscricoes_pagamento_online: data.inscricoes_pagamento_online ? 1 : 0,
            _method: 'put',
        }))
        .post(route('eventos.update', props.evento.id), {
            forceFormData: true,
            preserveScroll: true,
        });
};

const uploadMedia = (event) => {
    const ficheiros = Array.from(event.target.files ?? []);
    if (!ficheiros.length) return;

    const data = new FormData();
    ficheiros.forEach((ficheiro) => data.append('ficheiros[]', ficheiro));

    router.post(route('eventos.media.store', props.evento.id), data, {
        preserveScroll: true,
        onFinish: () => {
            event.target.value = '';
        },
    });
};

const apagarMedia = (media) => {
    router.delete(route('eventos.media.destroy', media.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Editar ${evento.titulo}`" />

    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.14em] text-slate-400">Editar evento</p>
                <h1 class="text-2xl font-black">{{ evento.titulo }}</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('eventos.show', evento.id)" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-white">Abrir evento</Link>
                <Link :href="route('eventos.index')" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-white">Voltar</Link>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[320px_1fr]">
            <aside class="rounded-lg bg-white p-5 shadow-sm">
                <img v-if="evento.cartaz" :src="evento.cartaz" :alt="evento.titulo" class="aspect-[4/5] w-full rounded-md bg-slate-100 object-cover">
                <div v-else class="grid aspect-[4/5] place-items-center rounded-md bg-slate-100 text-sm font-bold text-slate-400">Sem cartaz</div>

                <label class="mt-4 block cursor-pointer rounded-md border border-slate-300 px-4 py-3 text-center text-sm font-black hover:bg-slate-50">
                    Trocar cartaz
                    <input type="file" accept="image/*" class="hidden" @change="form.cartaz = $event.target.files[0]">
                </label>
            </aside>

            <form class="rounded-lg bg-white p-5 shadow-sm" @submit.prevent="guardar">
                <div class="grid gap-3 md:grid-cols-4">
                    <input v-model="form.titulo" required placeholder="Titulo" class="rounded-md border-slate-300 md:col-span-2">
                    <input v-model="form.subtitulo" placeholder="Subtitulo" class="rounded-md border-slate-300">
                    <input v-model="form.localizacao" placeholder="Local" class="rounded-md border-slate-300">
                    <input v-model="form.data_inicio" type="date" class="rounded-md border-slate-300">
                    <input v-model="form.data_fim" type="date" class="rounded-md border-slate-300">
                    <input v-model="form.periodo" placeholder="Periodo" class="rounded-md border-slate-300">
                    <input v-model="form.badge" placeholder="Etiqueta" class="rounded-md border-slate-300">
                    <input v-model="form.facebook_post_url" type="url" placeholder="URL do post do Facebook" class="rounded-md border-slate-300 md:col-span-2">
                    <select v-model="form.estado" class="rounded-md border-slate-300">
                        <option value="publicado">Publicado</option>
                        <option value="rascunho">Rascunho</option>
                    </select>
                    <label class="flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-bold">
                        <input v-model="form.destaque" type="checkbox" class="rounded border-slate-300">
                        Destaque
                    </label>
                    <input v-model.number="form.ordem" type="number" min="0" class="rounded-md border-slate-300">
                    <textarea v-model="form.descricao" placeholder="Descricao" rows="5" class="rounded-md border-slate-300 md:col-span-4"></textarea>
                    <textarea v-model="form.programa_texto" placeholder="Programa: uma linha por item" rows="5" class="rounded-md border-slate-300 md:col-span-4"></textarea>

                    <!-- Fotos do público -->
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 md:col-span-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="flex items-center gap-2 font-black text-stone-800">
                                <input v-model="form.fotos_publico_ativo" type="checkbox" class="rounded border-slate-300">
                                📷 Aceitar fotos enviadas pelo público
                            </label>
                            <a v-if="fotosPendentes.length" href="#fotos-pendentes" class="rounded bg-rose-600 px-2 py-1 text-xs font-black text-white">{{ fotosPendentes.length }} por tratar</a>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">As pessoas enviam fotos (e links de vídeos) por este link. As fotos só aparecem no site depois de aprovadas aqui em baixo; os vídeos são carregados por vocês. (Guarda o evento para ativar.)</p>
                        <div v-if="evento.fotos_publico_ativo" class="mt-3 flex flex-wrap items-center gap-2">
                            <input :value="evento.url_envio_fotos" readonly class="min-w-0 flex-1 rounded-md border-emerald-300 bg-white text-sm" @focus="$event.target.select()">
                            <button type="button" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-black text-white" @click="copiarLink">{{ linkCopiado ? 'Copiado ✓' : 'Copiar link' }}</button>
                            <a :href="evento.url_envio_fotos" target="_blank" rel="noopener" class="rounded-md border border-emerald-300 bg-white px-4 py-2 text-sm font-black text-emerald-800">Abrir</a>
                        </div>
                        <p v-else-if="evento.estado !== 'publicado'" class="mt-2 text-xs font-bold text-amber-700">Atenção: o evento tem de estar publicado para o link funcionar.</p>
                    </div>

                    <!-- Link externo -->
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-4 md:col-span-4">
                        <h3 class="font-black text-stone-800">🔗 Botão com link externo</h3>
                        <p class="mb-3 text-xs text-slate-500">Opcional. Ex.: quando as inscrições são feitas noutro site. Aparece como botão na página do evento e no destaque da homepage (abre num separador novo).</p>
                        <div class="grid gap-3 md:grid-cols-3">
                            <input v-model="form.link_externo_url" type="url" placeholder="https://… (vazio = sem botão)" class="rounded-md border-sky-300 md:col-span-2">
                            <input v-model="form.link_externo_texto" maxlength="80" placeholder="Texto do botão (ex.: Inscrições)" class="rounded-md border-sky-300">
                        </div>
                    </div>

                    <!-- Inscrições -->
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 md:col-span-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-black text-stone-800">📝 Inscrições</h3>
                            <Link :href="route('eventos.inscricoes', evento.id)" class="text-sm font-bold text-amber-700 underline">
                                Ver inscrições ({{ evento.inscricoes_total ?? 0 }} · {{ evento.pessoas_inscritas ?? 0 }} pessoas)
                            </Link>
                        </div>
                        <div class="grid gap-3 md:grid-cols-3">
                            <label class="flex items-center gap-2 rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-bold">
                                <input v-model="form.inscricoes_ativas" type="checkbox" class="rounded border-slate-300">
                                Inscrições abertas
                            </label>
                            <input v-model="form.inscricoes_limite" type="number" min="1" placeholder="Limite de pessoas (vazio = sem limite)" class="rounded-md border-amber-300">
                            <label class="flex items-center gap-2 rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-bold">
                                <input v-model="form.inscricoes_pede_idades" type="checkbox" class="rounded border-slate-300">
                                Pedir crianças + idades
                            </label>
                            <input v-model="form.inscricoes_preco" type="number" min="0" step="0.01" placeholder="Preço por pessoa € (vazio = grátis)" class="rounded-md border-amber-300">
                            <input v-model="form.inscricoes_preco_crianca" type="number" min="0" step="0.01" placeholder="Preço criança € (vazio = igual adulto)" class="rounded-md border-amber-300">
                            <input v-model="form.inscricoes_idade_crianca" type="number" min="1" max="17" placeholder="Criança até que idade (ex.: 10)" class="rounded-md border-amber-300">
                            <label class="flex items-center gap-2 rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-bold">
                                <input v-model="form.inscricoes_pagamento_online" type="checkbox" class="rounded border-slate-300">
                                💳 Pagamento online (Viva)
                            </label>
                            <textarea v-model="form.inscricoes_opcoes_texto" rows="3" class="rounded-md border-amber-300 md:col-span-3" placeholder="Opções de escolha (uma por linha), com preço opcional:&#10;Só caminhar = 5&#10;Caminhar e almoçar = 12.50"></textarea>
                        </div>
                    </div>
                </div>

                <div v-if="Object.keys(form.errors).length" class="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700">
                    <div v-for="(erro, campo) in form.errors" :key="campo">{{ erro }}</div>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <button class="rounded-md bg-emerald-700 px-5 py-3 font-black text-white disabled:opacity-60" :disabled="form.processing">{{ form.processing ? 'A guardar...' : 'Guardar alteracoes' }}</button>
                    <Link :href="route('eventos.index')" class="rounded-md border border-slate-300 px-5 py-3 font-black hover:bg-slate-50">Cancelar</Link>
                </div>
            </form>
        </div>

        <div v-if="fotosPendentes.length" id="fotos-pendentes"></div>
        <section v-if="videosPorTratar.length" class="mt-6 rounded-lg border-2 border-sky-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-black">🎬 Vídeos enviados por link ({{ videosPorTratar.length }})</h2>
            <p class="mb-4 text-sm text-slate-500">Abre o link, descarrega o vídeo e carrega-o em "Fotos e vídeos do evento" (mais abaixo). Depois marca como tratado.</p>
            <div class="divide-y divide-slate-100">
                <div v-for="video in videosPorTratar" :key="video.id" class="flex flex-wrap items-center gap-3 py-3">
                    <div class="min-w-0 flex-1">
                        <a :href="video.url" target="_blank" rel="noopener noreferrer" class="block truncate font-bold text-sky-700 underline">{{ video.url }}</a>
                        <p class="truncate text-xs text-slate-500">{{ video.enviado_nome }} · {{ video.enviado_contacto || 'sem contacto' }} · {{ video.enviado_em }}</p>
                    </div>
                    <a :href="video.url" target="_blank" rel="noopener noreferrer" class="rounded-md bg-sky-700 px-3 py-1.5 text-xs font-black text-white">Abrir link ↗</a>
                    <button type="button" class="rounded-md bg-emerald-700 px-3 py-1.5 text-xs font-black text-white" @click="tratarVideo(video)">✓ Já carreguei</button>
                    <button type="button" class="rounded-md border border-rose-300 px-3 py-1.5 text-xs font-black text-rose-700 hover:bg-rose-50" @click="rejeitarVideo(video)">✕ Ignorar</button>
                </div>
            </div>
        </section>

        <section v-if="fotosPorAprovar.length" class="mt-6 rounded-lg border-2 border-rose-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black">📷 Fotos do público por aprovar ({{ fotosPorAprovar.length }})</h2>
                    <p class="text-sm text-slate-500">Ainda não estão visíveis no site. Aprova as que queres publicar; as rejeitadas são apagadas.</p>
                </div>
                <button type="button" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-black text-white" @click="aprovarTodas">Aprovar todas</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="foto in fotosPorAprovar" :key="foto.id" class="rounded-md border border-slate-200 p-2">
                    <button type="button" class="block w-full" @click="fotoAberta = foto">
                        <img :src="foto.url" :alt="`Foto de ${foto.enviado_nome}`" loading="lazy" class="aspect-video w-full rounded bg-slate-100 object-cover">
                    </button>
                    <div class="mt-2 text-xs">
                        <p class="truncate font-bold">{{ foto.enviado_nome }}</p>
                        <p class="truncate text-slate-500">{{ foto.enviado_contacto || 'sem contacto' }} · {{ foto.enviado_em }}</p>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <button type="button" class="rounded-md bg-emerald-700 px-2 py-1.5 text-xs font-black text-white" @click="aprovarFoto(foto)">✓ Aprovar</button>
                        <button type="button" class="rounded-md border border-rose-300 px-2 py-1.5 text-xs font-black text-rose-700 hover:bg-rose-50" @click="rejeitarFoto(foto)">✕ Rejeitar</button>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="fotoAberta" class="fixed inset-0 z-50 grid place-items-center bg-black/85 p-4" @click.self="fotoAberta = null">
            <div class="w-full max-w-5xl">
                <img :src="fotoAberta.url" alt="" class="mx-auto max-h-[78vh] rounded-lg object-contain">
                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                    <span class="mr-2 text-sm font-bold text-white">{{ fotoAberta.enviado_nome }}</span>
                    <button type="button" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-black text-white" @click="aprovarFoto(fotoAberta); fotoAberta = null">✓ Aprovar</button>
                    <button type="button" class="rounded-md bg-rose-600 px-4 py-2 text-sm font-black text-white" @click="rejeitarFoto(fotoAberta); fotoAberta = null">✕ Rejeitar</button>
                    <button type="button" class="rounded-md border border-white/30 px-4 py-2 text-sm font-black text-white" @click="fotoAberta = null">Fechar</button>
                </div>
            </div>
        </div>

        <section class="mt-6 rounded-lg bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black">Fotos e videos do evento</h2>
                    <p class="text-sm text-slate-500">Carrega aqui os ficheiros para aparecerem na ficha do evento e na home.</p>
                </div>
                <label class="cursor-pointer rounded-md bg-slate-900 px-4 py-2 text-sm font-black text-white">
                    Adicionar fotos/videos
                    <input type="file" multiple accept="image/*,video/mp4,video/webm,video/quicktime" class="hidden" @change="uploadMedia">
                </label>
            </div>

            <div v-if="evento.media?.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="media in evento.media" :key="media.id" class="rounded-md border border-slate-200 p-2">
                    <img v-if="media.tipo === 'foto'" :src="media.miniatura || media.caminho" :alt="media.titulo" loading="lazy" class="aspect-video w-full rounded object-cover">
                    <video v-else :src="media.caminho" controls preload="none" class="aspect-video w-full rounded bg-black object-cover"></video>
                    <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                        <span class="truncate font-bold">{{ media.titulo }}</span>
                        <button type="button" class="text-rose-700 underline" @click="apagarMedia(media)">Remover</button>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-md bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">
                Ainda nao ha fotos ou videos neste evento.
            </div>
        </section>
    </AppLayout>
</template>
