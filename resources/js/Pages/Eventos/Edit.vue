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
        <div class="mx-auto flex max-w-[1200px] flex-col gap-6 font-sans text-tinta tabular-nums">
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div class="flex min-w-0 flex-col gap-1">
                        <Link :href="route('eventos.index')" class="inline-flex items-center gap-1 text-sm font-bold text-verde hover:text-verde-escuro">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                            Eventos
                        </Link>
                        <p class="text-[13px] font-extrabold uppercase tracking-[0.1em] text-suave-2">Editar evento</p>
                        <h1 class="text-[30px] font-extrabold leading-tight">{{ evento.titulo }}</h1>
                    </div>
                    <Link :href="route('eventos.show', evento.id)" class="btn-sec h-12">Abrir evento</Link>
                </div>
                <nav aria-label="Secções da página" class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                    <a href="#dados" class="tab bg-tinta text-white">Dados</a>
                    <a href="#fotos-publico" class="tab border border-linha-forte bg-white">Fotos do público</a>
                    <a href="#inscricoes" class="tab border border-linha-forte bg-white">Inscrições</a>
                    <a v-if="fotosPendentes.length" href="#fotos-pendentes" class="tab border border-perigo-claro bg-perigo-claro text-perigo-texto">
                        Por tratar <span class="rounded-full bg-perigo px-1.5 text-xs text-white">{{ fotosPendentes.length }}</span>
                    </a>
                    <a href="#galeria" class="tab border border-linha-forte bg-white">Galeria</a>
                </nav>
            </div>

            <div id="dados" class="grid scroll-mt-6 items-start gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                <aside class="flex flex-col gap-3 rounded-[14px] border border-linha bg-white p-4 lg:sticky lg:top-4">
                    <img v-if="evento.cartaz" :src="evento.cartaz" :alt="evento.titulo" class="mx-auto aspect-[4/5] w-full max-w-[260px] rounded-[10px] bg-[#E3E6E1] object-cover">
                    <div v-else class="mx-auto grid aspect-[4/5] w-full max-w-[260px] place-items-center rounded-[10px] bg-[#E3E6E1] text-sm font-bold text-suave-2">Sem cartaz</div>
                    <label class="btn-sec h-12 cursor-pointer">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 20h16" /></svg>
                        Trocar cartaz
                        <input type="file" accept="image/*" class="hidden" @change="form.cartaz = $event.target.files[0]">
                    </label>
                    <p v-if="form.cartaz" class="truncate text-center text-xs font-semibold text-verde">Novo cartaz: {{ form.cartaz.name }} (guarda para aplicar)</p>
                </aside>

                <form class="flex min-w-0 flex-col rounded-[14px] border border-linha bg-white" @submit.prevent="guardar">
                    <fieldset class="bloco">
                        <legend class="legenda">Informação do evento</legend>
                        <label class="rotulo sm:col-span-3">Título *<input v-model="form.titulo" required placeholder="Título" class="campo"></label>
                        <label class="rotulo">Subtítulo<input v-model="form.subtitulo" placeholder="Subtítulo" class="campo"></label>
                        <label class="rotulo">Local<input v-model="form.localizacao" placeholder="Local" class="campo"></label>
                        <label class="rotulo">Data de início<input v-model="form.data_inicio" type="date" class="campo"></label>
                        <label class="rotulo">Data de fim<input v-model="form.data_fim" type="date" class="campo"></label>
                        <label class="rotulo">Período<input v-model="form.periodo" placeholder="Período" class="campo"></label>
                        <label class="rotulo">Etiqueta<input v-model="form.badge" placeholder="Etiqueta" class="campo"></label>
                        <label class="rotulo sm:col-span-3">Descrição<textarea v-model="form.descricao" placeholder="Descrição" rows="4" class="campo-area"></textarea></label>
                        <label class="rotulo sm:col-span-3">Programa
                            <textarea v-model="form.programa_texto" placeholder="Programa: uma linha por item" rows="5" class="campo-area"></textarea>
                            <span class="text-xs font-normal text-suave-2">Uma linha por item.</span>
                        </label>
                    </fieldset>

                    <fieldset class="bloco">
                        <legend class="legenda">Visibilidade e ligações</legend>
                        <label class="rotulo">Estado
                            <select v-model="form.estado" class="campo">
                                <option value="publicado">Publicado</option>
                                <option value="rascunho">Rascunho</option>
                            </select>
                        </label>
                        <label class="rotulo">Ordem<input v-model.number="form.ordem" type="number" min="0" class="campo"></label>
                        <label class="caixa self-end">
                            <input v-model="form.destaque" type="checkbox" class="chk">
                            Destaque
                        </label>
                        <label class="rotulo sm:col-span-3">URL do post do Facebook<input v-model="form.facebook_post_url" type="url" placeholder="URL do post do Facebook" class="campo"></label>
                        <div class="sm:col-span-3">
                            <p class="text-[15px] font-extrabold">Botão com link externo <span class="font-semibold text-suave-2">(opcional)</span></p>
                            <p class="text-[13px] text-suave">Ex.: quando as inscrições são feitas noutro site. Aparece como botão na página do evento e no destaque da homepage (abre num separador novo).</p>
                        </div>
                        <label class="rotulo">Endereço do link<input v-model="form.link_externo_url" type="url" placeholder="https://… (vazio = sem botão)" class="campo"></label>
                        <label class="rotulo">Texto do botão<input v-model="form.link_externo_texto" maxlength="80" placeholder="Ex.: Inscrições" class="campo"></label>
                    </fieldset>

                    <fieldset id="fotos-publico" class="bloco scroll-mt-6">
                        <legend class="legenda">Fotos enviadas pelo público</legend>
                        <div class="flex flex-wrap items-center gap-2 sm:col-span-3">
                            <label class="caixa grow">
                                <input v-model="form.fotos_publico_ativo" type="checkbox" class="chk">
                                Aceitar fotos enviadas pelo público
                            </label>
                            <a v-if="fotosPendentes.length" href="#fotos-pendentes" class="pill bg-perigo text-white">{{ fotosPendentes.length }} por tratar</a>
                        </div>
                        <p class="text-[13px] text-suave sm:col-span-3">As pessoas enviam fotos (e links de vídeos) por este link. As fotos só aparecem no site depois de aprovadas aqui em baixo; os vídeos são carregados por vocês. (Guarda o evento para ativar.)</p>
                        <div v-if="evento.fotos_publico_ativo" class="flex flex-wrap items-center gap-2 sm:col-span-3">
                            <input :value="evento.url_envio_fotos" readonly class="campo min-w-0 flex-1 bg-fundo text-sm" @focus="$event.target.select()">
                            <button type="button" class="btn-pri h-12" @click="copiarLink">{{ linkCopiado ? 'Copiado' : 'Copiar link' }}</button>
                            <a :href="evento.url_envio_fotos" target="_blank" rel="noopener" class="btn-sec h-12">Abrir</a>
                        </div>
                        <p v-else-if="evento.estado !== 'publicado'" class="text-[13px] font-bold text-laranja-texto sm:col-span-3">Atenção: o evento tem de estar publicado para o link funcionar.</p>
                    </fieldset>

                    <fieldset id="inscricoes" class="bloco scroll-mt-6">
                        <legend class="legenda">Inscrições</legend>
                        <Link :href="route('eventos.inscricoes', evento.id)" class="flex min-h-11 items-center justify-between gap-2 rounded-[10px] bg-verde-claro px-3.5 py-2 text-sm font-bold text-verde-escuro hover:bg-verde-claro2 sm:col-span-3">
                            Ver inscrições ({{ evento.inscricoes_total ?? 0 }} · {{ evento.pessoas_inscritas ?? 0 }} pessoas)
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </Link>
                        <label class="caixa self-end"><input v-model="form.inscricoes_ativas" type="checkbox" class="chk">Inscrições abertas</label>
                        <label class="rotulo">Limite de pessoas<input v-model="form.inscricoes_limite" type="number" min="1" placeholder="Vazio = sem limite" class="campo"></label>
                        <label class="caixa self-end"><input v-model="form.inscricoes_pede_idades" type="checkbox" class="chk">Pedir crianças + idades</label>
                        <label class="rotulo">Preço por pessoa (€)<input v-model="form.inscricoes_preco" type="number" min="0" step="0.01" placeholder="Vazio = grátis" class="campo"></label>
                        <label class="rotulo">Preço criança (€)<input v-model="form.inscricoes_preco_crianca" type="number" min="0" step="0.01" placeholder="Vazio = igual adulto" class="campo"></label>
                        <label class="rotulo">Criança até que idade<input v-model="form.inscricoes_idade_crianca" type="number" min="1" max="17" placeholder="Ex.: 10" class="campo"></label>
                        <label class="caixa"><input v-model="form.inscricoes_pagamento_online" type="checkbox" class="chk">Pagamento online (Viva)</label>
                        <label class="rotulo sm:col-span-3">Opções de escolha (uma por linha, com preço opcional)
                            <textarea v-model="form.inscricoes_opcoes_texto" rows="3" class="campo-area" placeholder="Só caminhar = 5&#10;Caminhar e almoçar = 12.50"></textarea>
                        </label>
                    </fieldset>

                    <div v-if="Object.keys(form.errors).length" class="mx-4 mt-4 rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto sm:mx-6">
                        <div v-for="(erro, campo) in form.errors" :key="campo">{{ erro }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2.5 px-4 py-5 sm:px-6">
                        <button class="btn-pri h-[52px] px-6 text-base disabled:opacity-60" :disabled="form.processing">{{ form.processing ? 'A guardar...' : 'Guardar alterações' }}</button>
                        <Link :href="route('eventos.index')" class="btn-sec h-[52px] px-5 text-base">Cancelar</Link>
                    </div>
                </form>
            </div>

            <div v-if="fotosPendentes.length" id="fotos-pendentes" class="-mb-6 scroll-mt-6"></div>
            <section v-if="videosPorTratar.length" class="flex flex-col gap-3 rounded-[14px] border border-l-4 border-linha border-l-azul bg-white p-4 sm:p-6">
                <div>
                    <h2 class="text-lg font-extrabold">Vídeos enviados por link ({{ videosPorTratar.length }})</h2>
                    <p class="text-sm text-suave">Abre o link, descarrega o vídeo e carrega-o em "Fotos e vídeos do evento" (mais abaixo). Depois marca como tratado.</p>
                </div>
                <div class="divide-y divide-linha-fraca">
                    <div v-for="video in videosPorTratar" :key="video.id" class="flex flex-wrap items-center gap-2 py-3">
                        <div class="min-w-0 flex-1 basis-60">
                            <a :href="video.url" target="_blank" rel="noopener noreferrer" class="block truncate font-bold text-azul underline">{{ video.url }}</a>
                            <p class="truncate text-xs text-suave-2">{{ video.enviado_nome }} · {{ video.enviado_contacto || 'sem contacto' }} · {{ video.enviado_em }}</p>
                        </div>
                        <a :href="video.url" target="_blank" rel="noopener noreferrer" class="btn-sec h-11 text-sm">Abrir link</a>
                        <button type="button" class="btn-pri h-11 text-sm" @click="tratarVideo(video)">Já carreguei</button>
                        <button type="button" class="btn-sec h-11 text-sm text-perigo hover:bg-perigo-claro" @click="rejeitarVideo(video)">Ignorar</button>
                    </div>
                </div>
            </section>

            <section v-if="fotosPorAprovar.length" class="flex flex-col gap-4 rounded-[14px] border border-l-4 border-linha border-l-perigo bg-white p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-extrabold">Fotos do público por aprovar ({{ fotosPorAprovar.length }})</h2>
                        <p class="text-sm text-suave">Ainda não estão visíveis no site. Aprova as que queres publicar; as rejeitadas são apagadas.</p>
                    </div>
                    <button type="button" class="btn-pri h-11" @click="aprovarTodas">Aprovar todas</button>
                </div>
                <div class="grid gap-3 min-[480px]:grid-cols-2 lg:grid-cols-4">
                    <div v-for="foto in fotosPorAprovar" :key="foto.id" class="rounded-[14px] border border-linha p-2">
                        <button type="button" class="block w-full" :aria-label="`Ver foto de ${foto.enviado_nome} em grande`" @click="fotoAberta = foto">
                            <img :src="foto.url" :alt="`Foto de ${foto.enviado_nome}`" loading="lazy" class="aspect-video w-full rounded-[10px] bg-[#E3E6E1] object-cover">
                        </button>
                        <div class="mt-2 px-1 text-xs">
                            <p class="truncate text-sm font-bold">{{ foto.enviado_nome }}</p>
                            <p class="truncate text-suave-2">{{ foto.enviado_contacto || 'sem contacto' }} · {{ foto.enviado_em }}</p>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <button type="button" class="btn-pri h-11 px-2 text-sm" @click="aprovarFoto(foto)">Aprovar</button>
                            <button type="button" class="btn-sec h-11 px-2 text-sm text-perigo hover:bg-perigo-claro" @click="rejeitarFoto(foto)">Rejeitar</button>
                        </div>
                    </div>
                </div>
            </section>

            <section id="galeria" class="flex scroll-mt-6 flex-col gap-4 rounded-[14px] border border-linha bg-white p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-extrabold">Fotos e vídeos do evento</h2>
                        <p class="text-sm text-suave">Carrega aqui os ficheiros para aparecerem na ficha do evento e na home.</p>
                    </div>
                    <label class="btn-pri h-12 cursor-pointer">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M4 20h16" /></svg>
                        Adicionar fotos/vídeos
                        <input type="file" multiple accept="image/*,video/mp4,video/webm,video/quicktime" class="hidden" @change="uploadMedia">
                    </label>
                </div>

                <div v-if="evento.media?.length" class="grid gap-3 min-[480px]:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                    <div v-for="media in evento.media" :key="media.id" class="rounded-[14px] border border-linha p-2">
                        <img v-if="media.tipo === 'foto'" :src="media.miniatura || media.caminho" :alt="media.titulo" loading="lazy" class="aspect-video w-full rounded-[10px] bg-[#E3E6E1] object-cover">
                        <video v-else :src="media.caminho" controls preload="none" class="aspect-video w-full rounded-[10px] bg-black object-cover"></video>
                        <div class="mt-2 flex items-center justify-between gap-2 px-1">
                            <span class="min-w-0 truncate text-sm font-bold">{{ media.titulo }}</span>
                            <button type="button" class="grid h-11 w-11 shrink-0 place-items-center rounded-[10px] border border-linha-forte text-perigo hover:bg-perigo-claro" aria-label="Remover ficheiro" title="Remover" @click="apagarMedia(media)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="rounded-[10px] bg-fundo p-6 text-center text-sm font-bold text-suave-2">
                    Ainda não há fotos ou vídeos neste evento.
                </div>
            </section>
        </div>

        <div v-if="fotoAberta" class="fixed inset-0 z-50 grid place-items-center bg-tinta/90 p-4 font-sans" @click.self="fotoAberta = null">
            <div class="w-full max-w-5xl">
                <img :src="fotoAberta.url" alt="" class="mx-auto max-h-[78vh] rounded-[14px] object-contain">
                <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                    <span class="mr-2 text-sm font-bold text-white">{{ fotoAberta.enviado_nome }}</span>
                    <button type="button" class="btn-pri h-11" @click="aprovarFoto(fotoAberta); fotoAberta = null">Aprovar</button>
                    <button type="button" class="inline-flex h-11 items-center rounded-[10px] bg-perigo px-4 text-sm font-bold text-white" @click="rejeitarFoto(fotoAberta); fotoAberta = null">Rejeitar</button>
                    <button type="button" class="inline-flex h-11 items-center rounded-[10px] border border-white/30 px-4 text-sm font-bold text-white" @click="fotoAberta = null">Fechar</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.tab { @apply inline-flex h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-4 text-sm font-bold; }
.pill { @apply rounded-full px-2.5 py-1 text-xs font-extrabold; }
.bloco { @apply grid gap-3.5 border-b border-linha-fraca px-4 py-5 sm:grid-cols-3 sm:px-6; }
.legenda { @apply col-span-full float-left mb-1 w-full text-[13px] font-extrabold uppercase tracking-[0.06em] text-suave-2; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta focus:border-verde focus:ring-verde; }
.campo-area { @apply w-full rounded-[10px] border border-linha-forte bg-white px-3.5 py-3 text-base text-tinta focus:border-verde focus:ring-verde; }
.caixa { @apply flex h-12 items-center gap-3 rounded-[10px] border border-linha-forte bg-white px-3.5 text-[15px] font-bold text-tinta; }
.chk { @apply h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde; }
</style>
