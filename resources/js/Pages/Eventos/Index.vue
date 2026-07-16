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
    inscricoes_ativas: false,
    inscricoes_limite: '',
    inscricoes_opcoes_texto: '',
    inscricoes_pede_idades: false,
});

const cartazNovo = ref(null);
const eventos = computed(() => props.eventos ?? []);

const guardarNovo = () => {
    novo.transform((data) => ({
        ...data,
        destaque: data.destaque ? 1 : 0,
        inscricoes_ativas: data.inscricoes_ativas ? 1 : 0,
        inscricoes_pede_idades: data.inscricoes_pede_idades ? 1 : 0,
        inscricoes_limite: data.inscricoes_limite === '' ? null : data.inscricoes_limite,
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

const dataEvento = (evento) => {
    if (!evento.data_inicio) return evento.periodo || 'Sem data definida';
    if (evento.data_fim && evento.data_fim !== evento.data_inicio) return `${evento.data_inicio} a ${evento.data_fim}`;
    return evento.data_inicio;
};
</script>

<template>
    <Head title="Eventos" />

    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black">Eventos da associacao</h1>
                <p class="mt-1 text-sm text-slate-500">Cria eventos, abre a ficha do evento e edita cartazes, programa, fotos e videos.</p>
            </div>
            <a :href="route('home')" target="_blank" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-white">Ver home</a>
        </div>

        <form class="mb-8 rounded-lg bg-white p-5 shadow-sm" @submit.prevent="guardarNovo">
            <h2 class="mb-4 font-black">Novo evento</h2>
            <div class="grid gap-3 md:grid-cols-4">
                <input v-model="novo.titulo" required placeholder="Titulo" class="rounded-md border-slate-300 md:col-span-2">
                <input v-model="novo.subtitulo" placeholder="Subtitulo" class="rounded-md border-slate-300">
                <input v-model="novo.localizacao" placeholder="Local" class="rounded-md border-slate-300">
                <input v-model="novo.data_inicio" type="date" class="rounded-md border-slate-300">
                <input v-model="novo.data_fim" type="date" class="rounded-md border-slate-300">
                <input v-model="novo.periodo" placeholder="Periodo, ex: Julho 2026" class="rounded-md border-slate-300">
                <input v-model="novo.badge" placeholder="Etiqueta" class="rounded-md border-slate-300">
                <select v-model="novo.estado" class="rounded-md border-slate-300">
                    <option value="publicado">Publicado</option>
                    <option value="rascunho">Rascunho</option>
                </select>
                <label class="flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-bold">
                    <input v-model="novo.destaque" type="checkbox" class="rounded border-slate-300">
                    Destaque
                </label>
                <input v-model.number="novo.ordem" type="number" min="0" placeholder="Ordem" class="rounded-md border-slate-300">
                <input ref="cartazNovo" type="file" accept="image/*" class="rounded-md border border-slate-300 p-2 text-sm" @change="novo.cartaz = $event.target.files[0]">
                <textarea v-model="novo.descricao" placeholder="Descricao" rows="3" class="rounded-md border-slate-300 md:col-span-2"></textarea>
                <textarea v-model="novo.programa_texto" placeholder="Programa: uma linha por item" rows="3" class="rounded-md border-slate-300 md:col-span-2"></textarea>

                <!-- Inscrições -->
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 md:col-span-4">
                    <h3 class="mb-3 font-black text-stone-800">📝 Inscrições</h3>
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="flex items-center gap-2 rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-bold">
                            <input v-model="novo.inscricoes_ativas" type="checkbox" class="rounded border-slate-300">
                            Inscrições abertas
                        </label>
                        <input v-model="novo.inscricoes_limite" type="number" min="1" placeholder="Limite de pessoas (vazio = sem limite)" class="rounded-md border-amber-300">
                        <label class="flex items-center gap-2 rounded-md border border-amber-300 bg-white px-3 py-2 text-sm font-bold">
                            <input v-model="novo.inscricoes_pede_idades" type="checkbox" class="rounded border-slate-300">
                            Pedir crianças + idades
                        </label>
                        <textarea v-model="novo.inscricoes_opcoes_texto" rows="2" class="rounded-md border-amber-300 md:col-span-3" placeholder="Opções de escolha (uma por linha), ex.:&#10;Só caminhar&#10;Caminhar e almoçar"></textarea>
                    </div>
                </div>
            </div>
            <div v-if="Object.keys(novo.errors).length" class="mt-3 rounded-md bg-red-50 p-3 text-sm font-bold text-red-700">
                <div v-for="(erro, campo) in novo.errors" :key="campo">{{ erro }}</div>
            </div>
            <button class="mt-4 rounded-md bg-slate-900 px-5 py-3 font-black text-white disabled:opacity-60" :disabled="novo.processing">Criar evento</button>
        </form>

        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-black">Eventos existentes</h2>
                <p class="text-sm text-slate-500">Usa Abrir para ver como correu o evento, ou Editar para alterar informacao.</p>
            </div>
            <span class="rounded bg-white px-3 py-1 text-sm font-black text-slate-600 shadow-sm">{{ eventos.length }} eventos</span>
        </div>

        <div v-if="!eventos.length" class="rounded-lg bg-white p-6 text-center font-bold text-slate-500 shadow-sm">
            Ainda nao existem eventos na base de dados. Corre o EventoSeeder ou cria um novo evento acima.
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <article v-for="evento in eventos" :key="evento.id" class="grid overflow-hidden rounded-lg bg-white shadow-sm sm:grid-cols-[170px_1fr]">
                <img v-if="evento.cartaz" :src="evento.cartaz" :alt="evento.titulo" class="h-full min-h-56 w-full bg-slate-100 object-cover">
                <div v-else class="grid min-h-56 place-items-center bg-slate-100 text-sm font-bold text-slate-400">Sem cartaz</div>

                <div class="flex min-w-0 flex-col p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded bg-slate-900 px-2 py-1 text-xs font-black uppercase text-white">{{ evento.estado }}</span>
                        <span v-if="evento.destaque" class="rounded bg-amber-100 px-2 py-1 text-xs font-black uppercase text-amber-800">Destaque</span>
                        <span class="text-sm font-bold text-slate-500">{{ dataEvento(evento) }}</span>
                    </div>

                    <h3 class="mt-3 truncate text-xl font-black">{{ evento.titulo }}</h3>
                    <p class="mt-1 text-sm font-bold text-slate-500">{{ evento.subtitulo || evento.localizacao || 'Sem subtitulo' }}</p>
                    <p class="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-600">{{ evento.descricao || 'Sem descricao ainda.' }}</p>

                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-bold text-slate-500">
                        <span class="rounded bg-slate-100 px-2 py-1">{{ evento.media?.length || 0 }} fotos/videos</span>
                        <span class="rounded bg-slate-100 px-2 py-1">Ordem {{ evento.ordem }}</span>
                        <span v-if="evento.updated_at" class="rounded bg-slate-100 px-2 py-1">Atualizado {{ evento.updated_at }}</span>
                    </div>

                    <div class="mt-auto flex flex-wrap gap-2 pt-5">
                        <Link :href="route('eventos.show', evento.id)" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white">Abrir</Link>
                        <Link :href="route('eventos.edit', evento.id)" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-slate-50">Editar</Link>
                        <Link :href="route('eventos.inscricoes', evento.id)" class="rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-800 hover:bg-amber-100">
                            Inscrições<span v-if="evento.inscricoes_ativas"> ({{ evento.pessoas_inscritas ?? 0 }})</span>
                        </Link>
                        <button type="button" class="rounded-md border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700 hover:bg-rose-50" @click="apagar(evento)">Apagar</button>
                    </div>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
