<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    eventos: Array,
});

const novo = useForm({
    titulo: '',
    subtitulo: '',
    data_inicio: '',
    data_fim: '',
    periodo: '',
    localizacao: '',
    badge: 'Evento',
    descricao: '',
    estado: 'publicado',
    destaque: false,
    ordem: 0,
    programa_texto: '',
    cartaz: null,
    link_externo_url: '',
    link_externo_texto: '',
    fotos_publico_ativo: false,
    inscricoes_ativas: false,
    inscricoes_limite: '',
    inscricoes_opcoes_texto: '',
    inscricoes_pede_idades: false,
    inscricoes_preco: '',
    inscricoes_preco_crianca: '',
    inscricoes_idade_crianca: '',
    inscricoes_pagamento_online: false,
});

const cartazNovo = ref(null);
const eventos = computed(() => props.eventos ?? []);

const guardarNovo = () => {
    novo.transform((data) => ({
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
    })).post(route('eventos.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            novo.reset();
            novo.estado = 'publicado';
            novo.badge = 'Evento';
            if (cartazNovo.value) cartazNovo.value.value = '';
        },
    });
};

const apagar = (evento) => {
    if (!confirm(`Apagar o evento "${evento.titulo}"?`)) return;
    router.delete(route('eventos.destroy', evento.id), { preserveScroll: true });
};

// Eventos com fotos do público à espera de aprovação (aviso no topo)
const comFotosPendentes = computed(() => eventos.value.filter((e) => e.fotos_pendentes_total));

const estadoLabel = (estado) => ({ publicado: 'Publicado', rascunho: 'Rascunho' }[estado] ?? estado);

const dataEvento = (evento) => {
    if (!evento.data_inicio) return evento.periodo || 'Sem data definida';
    if (evento.data_fim && evento.data_fim !== evento.data_inicio) return `${evento.data_inicio} a ${evento.data_fim}`;
    return evento.data_inicio;
};
</script>

<template>
    <Head title="Eventos" />

    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-6 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-[30px] font-extrabold leading-tight">Eventos da associação</h1>
                    <p class="text-[15px] text-suave">Cria eventos, abre a ficha do evento e edita cartazes, programa, fotos e vídeos.</p>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <a :href="route('home')" target="_blank" class="btn-sec h-12">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" /><path d="M18 14v6H4V6h6" /></svg>
                        Ver home
                    </a>
                    <a href="#novo" class="btn-pri h-12">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        Novo evento
                    </a>
                </div>
            </div>

            <Link
                v-for="evento in comFotosPendentes"
                :key="`pend-${evento.id}`"
                :href="`${route('eventos.edit', evento.id)}#fotos-pendentes`"
                class="flex items-center gap-3 rounded-[14px] bg-perigo-claro px-[18px] py-3.5 text-[15px] font-semibold text-perigo-texto"
            >
                <svg class="shrink-0" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h4l2-3h6l2 3h4v12H3z" /><circle cx="12" cy="13" r="4" /></svg>
                <span class="min-w-0 grow"><strong class="font-extrabold">{{ evento.fotos_pendentes_total }} {{ evento.fotos_pendentes_total === 1 ? 'foto do público por aprovar' : 'fotos do público por aprovar' }}</strong> em {{ evento.titulo }}</span>
                <span class="whitespace-nowrap font-extrabold underline">Rever agora</span>
            </Link>

            <section aria-labelledby="t-existentes" class="flex flex-col gap-3.5">
                <div class="flex flex-wrap items-end justify-between gap-2.5">
                    <div class="flex flex-col gap-1">
                        <h2 id="t-existentes" class="text-xl font-extrabold">Eventos existentes</h2>
                        <p class="text-sm text-suave-2">Usa Abrir para ver como correu o evento, ou Editar para alterar informação.</p>
                    </div>
                    <span class="rounded-full border border-linha bg-white px-3 py-1.5 text-sm font-bold text-suave">{{ eventos.length }} eventos</span>
                </div>

                <div v-if="!eventos.length" class="rounded-[14px] border border-linha bg-white p-6 text-center font-bold text-suave-2">
                    Ainda não existem eventos na base de dados. Corre o EventoSeeder ou cria um novo evento abaixo.
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <article v-for="evento in eventos" :key="evento.id" class="grid overflow-hidden rounded-[14px] border border-linha bg-white sm:grid-cols-[140px_minmax(0,1fr)]">
                        <img v-if="evento.cartaz" :src="evento.cartaz" :alt="evento.titulo" class="h-40 w-full bg-[#E3E6E1] object-cover sm:h-full sm:min-h-60">
                        <div v-else class="grid h-28 place-items-center bg-[#E3E6E1] p-2 text-center text-[13px] font-bold text-suave-2 sm:h-full sm:min-h-60">Sem cartaz</div>

                        <div class="flex min-w-0 flex-col gap-2 p-[18px]">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="pill" :class="evento.estado === 'publicado' ? 'bg-verde-claro text-verde-escuro' : 'bg-fundo text-suave'">{{ estadoLabel(evento.estado) }}</span>
                                <span v-if="evento.destaque" class="pill bg-laranja-claro text-laranja-texto">Destaque</span>
                                <span v-if="evento.inscricoes_ativas" class="pill bg-verde-claro2 text-verde-escuro">Inscrições abertas</span>
                                <Link v-if="evento.fotos_pendentes_total" :href="`${route('eventos.edit', evento.id)}#fotos-pendentes`" class="pill bg-perigo text-white">{{ evento.fotos_pendentes_total }} por aprovar</Link>
                            </div>
                            <div class="text-sm font-bold text-suave">{{ dataEvento(evento) }}</div>
                            <h3 class="truncate text-xl font-extrabold leading-tight">{{ evento.titulo }}</h3>
                            <p class="text-sm font-semibold text-suave-2">{{ evento.subtitulo || evento.localizacao || 'Sem subtítulo' }}</p>
                            <p v-if="evento.descricao" class="line-clamp-2 text-sm leading-normal text-suave">{{ evento.descricao }}</p>
                            <p v-else class="text-sm italic text-suave-2">Sem descrição ainda.</p>

                            <div class="flex flex-wrap gap-1.5 text-xs font-semibold text-suave-2">
                                <span class="rounded-md bg-fundo px-2 py-0.5">{{ evento.media?.length || 0 }} fotos/vídeos</span>
                                <span class="rounded-md bg-fundo px-2 py-0.5">Ordem {{ evento.ordem }}</span>
                                <span v-if="evento.updated_at" class="rounded-md bg-fundo px-2 py-0.5">Atualizado {{ evento.updated_at }}</span>
                            </div>

                            <div class="mt-auto flex flex-wrap gap-2 pt-2">
                                <Link :href="route('eventos.show', evento.id)" class="btn-pri h-11 flex-1 sm:flex-none">Abrir</Link>
                                <Link :href="route('eventos.edit', evento.id)" class="btn-sec h-11 flex-1 sm:flex-none">Editar</Link>
                                <Link :href="route('eventos.inscricoes', evento.id)" class="btn-sec h-11 flex-1 sm:flex-none">
                                    Inscrições
                                    <span v-if="evento.inscricoes_ativas" class="rounded-full bg-verde px-2 text-xs font-extrabold text-white">{{ evento.pessoas_inscritas ?? 0 }}</span>
                                </Link>
                                <button type="button" class="ml-auto grid h-11 w-11 shrink-0 place-items-center rounded-[10px] border border-linha-forte bg-white text-perigo hover:bg-perigo-claro" :aria-label="`Apagar o evento ${evento.titulo}`" title="Apagar" @click="apagar(evento)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <form id="novo" aria-labelledby="t-novo" class="flex scroll-mt-6 flex-col rounded-[14px] border border-linha bg-white" @submit.prevent="guardarNovo">
                <div class="flex flex-col gap-1 border-b border-linha-fraca px-4 py-5 sm:px-6">
                    <h2 id="t-novo" class="text-xl font-extrabold">Novo evento</h2>
                    <p class="text-sm text-suave-2">Só o título é obrigatório. O resto podes completar depois em Editar.</p>
                </div>

                <fieldset class="grid gap-3.5 border-b border-linha-fraca px-4 py-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                    <legend class="legenda">1 · O essencial</legend>
                    <label class="rotulo sm:col-span-2 lg:col-span-4">Título *
                        <input v-model="novo.titulo" required placeholder="Ex.: Festas de Santa Ana 2026" class="campo">
                    </label>
                    <label class="rotulo">Subtítulo<input v-model="novo.subtitulo" placeholder="Subtítulo" class="campo"></label>
                    <label class="rotulo">Local<input v-model="novo.localizacao" placeholder="Local" class="campo"></label>
                    <label class="rotulo">Etiqueta<input v-model="novo.badge" placeholder="Etiqueta" class="campo"></label>
                    <label class="rotulo">Data de início<input v-model="novo.data_inicio" type="date" class="campo"></label>
                    <label class="rotulo">Data de fim<input v-model="novo.data_fim" type="date" class="campo"></label>
                    <label class="rotulo">Período (se não houver data)<input v-model="novo.periodo" placeholder="Período, ex: Julho 2026" class="campo"></label>
                    <label class="rotulo sm:col-span-2 lg:col-span-4">Descrição
                        <textarea v-model="novo.descricao" placeholder="Descrição" rows="3" class="campo-area"></textarea>
                    </label>
                    <label class="rotulo sm:col-span-2 lg:col-span-4">Programa
                        <textarea v-model="novo.programa_texto" placeholder="Programa: uma linha por item" rows="3" class="campo-area"></textarea>
                    </label>
                    <label class="rotulo sm:col-span-2 lg:col-span-4">Cartaz (imagem)
                        <input ref="cartazNovo" type="file" accept="image/*" class="min-h-12 w-full rounded-[10px] border border-dashed border-linha-forte bg-fundo p-2.5 text-sm text-tinta" @change="novo.cartaz = $event.target.files[0]">
                    </label>
                </fieldset>

                <fieldset class="grid gap-3.5 border-b border-linha-fraca px-4 py-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                    <legend class="legenda">2 · Visibilidade no site</legend>
                    <label class="rotulo">Estado
                        <select v-model="novo.estado" class="campo">
                            <option value="publicado">Publicado</option>
                            <option value="rascunho">Rascunho</option>
                        </select>
                    </label>
                    <label class="rotulo">Ordem<input v-model.number="novo.ordem" type="number" min="0" placeholder="Ordem" class="campo"></label>
                    <label class="caixa self-end">
                        <input v-model="novo.destaque" type="checkbox" class="h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde">
                        Destaque na homepage
                    </label>
                    <label class="caixa self-end">
                        <input v-model="novo.fotos_publico_ativo" type="checkbox" class="h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde">
                        Aceitar fotos do público
                    </label>
                </fieldset>

                <fieldset class="grid gap-3.5 border-b border-linha-fraca px-4 py-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                    <legend class="legenda">3 · Botão com link externo <span class="font-semibold normal-case tracking-normal">(opcional)</span></legend>
                    <p class="text-[13px] text-suave sm:col-span-2 lg:col-span-4">Ex.: quando as inscrições são feitas noutro site. Aparece como botão na página do evento e no destaque da homepage (abre num separador novo).</p>
                    <label class="rotulo">Endereço do link<input v-model="novo.link_externo_url" type="url" placeholder="https://… (vazio = sem botão)" class="campo"></label>
                    <label class="rotulo">Texto do botão<input v-model="novo.link_externo_texto" maxlength="80" placeholder="Ex.: Inscrições" class="campo"></label>
                </fieldset>

                <fieldset class="grid gap-3.5 border-b border-linha-fraca px-4 py-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
                    <legend class="legenda">4 · Inscrições</legend>
                    <label class="caixa self-end">
                        <input v-model="novo.inscricoes_ativas" type="checkbox" class="h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde">
                        Inscrições abertas
                    </label>
                    <label class="rotulo">Limite de pessoas<input v-model="novo.inscricoes_limite" type="number" min="1" placeholder="Vazio = sem limite" class="campo"></label>
                    <label class="caixa self-end">
                        <input v-model="novo.inscricoes_pede_idades" type="checkbox" class="h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde">
                        Pedir crianças + idades
                    </label>
                    <label class="rotulo">Preço por pessoa (€)<input v-model="novo.inscricoes_preco" type="number" min="0" step="0.01" placeholder="Vazio = grátis" class="campo"></label>
                    <label class="rotulo">Preço criança (€)<input v-model="novo.inscricoes_preco_crianca" type="number" min="0" step="0.01" placeholder="Vazio = igual adulto" class="campo"></label>
                    <label class="rotulo">Criança até que idade<input v-model="novo.inscricoes_idade_crianca" type="number" min="1" max="17" placeholder="Ex.: 10" class="campo"></label>
                    <label class="caixa self-end">
                        <input v-model="novo.inscricoes_pagamento_online" type="checkbox" class="h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde">
                        Pagamento online (Viva)
                    </label>
                    <label class="rotulo sm:col-span-2 lg:col-span-4">Opções de escolha (uma por linha, com preço opcional)
                        <textarea v-model="novo.inscricoes_opcoes_texto" rows="2" class="campo-area" placeholder="Só caminhar = 5&#10;Caminhar e almoçar = 12.50"></textarea>
                    </label>
                </fieldset>

                <div v-if="Object.keys(novo.errors).length" class="mx-4 mt-4 rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto sm:mx-6">
                    <div v-for="(erro, campo) in novo.errors" :key="campo">{{ erro }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-3.5 px-4 py-5 sm:px-6">
                    <button class="btn-pri h-[52px] px-6 text-base disabled:opacity-60" :disabled="novo.processing">Criar evento</button>
                    <span class="text-[13px] text-suave">Depois de criar, abre Editar para carregar fotos e vídeos.</span>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.pill { @apply rounded-full px-2.5 py-0.5 text-xs font-extrabold; }
.legenda { @apply col-span-full float-left mb-1 w-full text-[13px] font-extrabold uppercase tracking-[0.06em] text-suave-2; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta focus:border-verde focus:ring-verde; }
.campo-area { @apply w-full rounded-[10px] border border-linha-forte bg-white px-3.5 py-3 text-base text-tinta focus:border-verde focus:ring-verde; }
.caixa { @apply flex h-12 items-center gap-3 rounded-[10px] border border-linha-forte bg-white px-3.5 text-[15px] font-bold text-tinta; }
</style>
