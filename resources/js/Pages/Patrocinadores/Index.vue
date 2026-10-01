<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    patrocinadores: {
        type: Array,
        default: () => [],
    },
});

const logoNovo = ref(null);
const items = ref([]);
const novo = useForm({
    empresa: '',
    website: '',
    descricao: '',
    ordem: 0,
    mostrar_no_slider: true,
    ativo: true,
    logotipo: null,
});

const normalizar = (sponsor) => ({
    ...sponsor,
    mostrar_no_slider: Boolean(sponsor.mostrar_no_slider),
    ativo: Boolean(sponsor.ativo),
    novoLogo: null,
    novaImagem: null,
    imagemOrdem: 0,
});

watch(
    () => props.patrocinadores,
    (patrocinadores) => {
        items.value = (patrocinadores || []).map(normalizar);
    },
    { immediate: true },
);

const criar = () => {
    novo.post(route('patrocinadores.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            novo.reset();
            novo.mostrar_no_slider = true;
            novo.ativo = true;
            novo.ordem = 0;
            if (logoNovo.value) logoNovo.value.value = '';
            novoAberto.value = false;
        },
    });
};

const atualizar = (sponsor) => {
    router.post(route('patrocinadores.update', sponsor.id), {
        empresa: sponsor.empresa,
        website: sponsor.website || '',
        descricao: sponsor.descricao || '',
        ordem: sponsor.ordem || 0,
        mostrar_no_slider: sponsor.mostrar_no_slider ? 1 : 0,
        ativo: sponsor.ativo ? 1 : 0,
        logotipo: sponsor.novoLogo || null,
        _method: 'put',
    }, {
        forceFormData: true,
        preserveScroll: true,
    });
};

const apagar = (sponsor) => {
    if (!confirm(`Apagar o patrocinador "${sponsor.empresa}" e todas as suas imagens?`)) return;
    router.delete(route('patrocinadores.destroy', sponsor.id), { preserveScroll: true });
};

const adicionarImagem = (sponsor) => {
    if (!sponsor.novaImagem) return;
    router.post(route('patrocinadores.imagens.store', sponsor.id), {
        imagem: sponsor.novaImagem,
        ordem: sponsor.imagemOrdem || 0,
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            sponsor.novaImagem = null;
            sponsor.imagemOrdem = 0;
        },
    });
};

// Estado de UI: formulário "novo" aberto e cartão de patrocinador expandido
const novoAberto = ref(false);
const abertoId = ref(null);
const alternar = (sponsor) => { abertoId.value = abertoId.value === sponsor.id ? null : sponsor.id; };
const fotosTexto = (n) => (n === 1 ? '1 foto no ecrã' : `${n} fotos no ecrã`);

const apagarImagem = (imagem) => {
    if (!confirm('Remover esta imagem?')) return;
    router.delete(route('patrocinadores.imagens.destroy', imagem.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Patrocinadores" />

    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-4 font-sans text-tinta tabular-nums">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-[30px] font-extrabold leading-tight">Patrocinadores</h1>
                    <p class="text-[15px] text-suave">Gere logos, fotos e visibilidade dos patrocinadores no site.</p>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <a :href="route('patrocinios.ecra')" target="_blank" class="btn-sec h-12">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2" /><path d="M8 20h8M12 16v4" /></svg>
                        Ecrã da festa
                    </a>
                    <a :href="route('patrocinios.index')" target="_blank" class="btn-sec h-12">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" /><path d="M18 14v6H4V6h6" /></svg>
                        Ver página pública
                    </a>
                    <button type="button" class="btn-pri h-12" :aria-expanded="novoAberto" @click="novoAberto = !novoAberto">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        Novo patrocinador
                    </button>
                </div>
            </div>

            <form v-if="novoAberto" class="flex flex-col overflow-hidden rounded-[14px] border border-verde bg-white" @submit.prevent="criar">
                <h2 class="border-b border-linha-fraca px-4 py-4 text-lg font-extrabold sm:px-5">Novo patrocinador</h2>
                <div class="grid gap-3.5 px-4 py-4 sm:grid-cols-2 sm:px-5 lg:grid-cols-4">
                    <label class="rotulo lg:col-span-2">Empresa *<input v-model="novo.empresa" required placeholder="Empresa" class="campo"></label>
                    <label class="rotulo lg:col-span-2">Website<input v-model="novo.website" type="url" placeholder="Website com https://" class="campo"></label>
                    <label class="rotulo sm:col-span-2 lg:col-span-4">Descrição<input v-model="novo.descricao" placeholder="Descrição curta" class="campo"></label>
                    <label class="rotulo">Logótipo<input ref="logoNovo" type="file" accept="image/*,.svg" class="ficheiro" @change="novo.logotipo = $event.target.files[0]"></label>
                    <label class="rotulo">Ordem<input v-model.number="novo.ordem" type="number" min="0" placeholder="Ordem" class="campo"></label>
                    <label class="caixa self-end"><input v-model="novo.mostrar_no_slider" type="checkbox" class="chk">Mostrar no slider</label>
                    <label class="caixa self-end"><input v-model="novo.ativo" type="checkbox" class="chk">Ativo</label>
                </div>
                <div v-if="Object.keys(novo.errors).length" class="mx-4 rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto sm:mx-5">
                    <div v-for="(erro, campo) in novo.errors" :key="campo">{{ erro }}</div>
                </div>
                <div class="flex flex-wrap gap-2.5 px-4 py-4 sm:px-5">
                    <button class="btn-pri h-[52px] px-6 text-base disabled:opacity-60" :disabled="novo.processing">{{ novo.processing ? 'A criar...' : 'Criar patrocinador' }}</button>
                    <button type="button" class="btn-sec h-[52px]" @click="novoAberto = false">Cancelar</button>
                </div>
            </form>

            <div v-if="!items.length" class="rounded-[14px] border border-linha bg-white p-6 text-center font-bold text-suave-2">
                Ainda não existem patrocinadores.
            </div>

            <article v-for="sponsor in items" :key="sponsor.id" class="overflow-hidden rounded-[14px] border bg-white" :class="abertoId === sponsor.id ? 'border-verde' : 'border-linha'">
                <!-- Resumo -->
                <div class="flex items-center gap-3 p-3 sm:gap-4 sm:p-4">
                    <div class="grid h-16 w-20 shrink-0 place-items-center rounded-[10px] bg-fundo p-2 sm:h-[74px] sm:w-[120px]">
                        <img :src="sponsor.logo_url" :alt="sponsor.empresa" class="max-h-full max-w-full object-contain">
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate text-lg font-extrabold">{{ sponsor.empresa }}</h2>
                        <p v-if="sponsor.descricao" class="truncate text-sm text-suave">{{ sponsor.descricao }}</p>
                        <div class="mt-1 flex flex-wrap gap-1.5 text-xs font-bold">
                            <span class="rounded-full px-2 py-0.5" :class="sponsor.ativo ? 'bg-verde-claro text-verde-escuro' : 'bg-fundo text-suave'">{{ sponsor.ativo ? 'Ativo' : 'Inativo' }}</span>
                            <span class="rounded-full px-2 py-0.5" :class="sponsor.mostrar_no_slider ? 'bg-verde-claro text-verde-escuro' : 'bg-fundo text-suave'">{{ sponsor.mostrar_no_slider ? 'No slider' : 'Fora do slider' }}</span>
                            <span class="rounded-full bg-fundo px-2 py-0.5 text-suave">{{ fotosTexto((sponsor.images || []).length) }}</span>
                            <span class="rounded-full bg-fundo px-2 py-0.5 text-suave">Ordem {{ sponsor.ordem }}</span>
                        </div>
                    </div>
                    <button type="button" class="h-11 shrink-0 rounded-[10px] px-4 text-[15px] font-bold" :class="abertoId === sponsor.id ? 'bg-tinta text-white' : 'border border-linha-forte bg-white hover:bg-fundo'" :aria-expanded="abertoId === sponsor.id" @click="alternar(sponsor)">
                        {{ abertoId === sponsor.id ? 'Fechar' : 'Editar' }}
                    </button>
                </div>

                <!-- Edição -->
                <div v-if="abertoId === sponsor.id" class="flex flex-col gap-4 border-t border-linha-fraca bg-fundo/60 p-4">
                    <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="rotulo">Empresa<input v-model="sponsor.empresa" class="campo"></label>
                        <label class="rotulo">Website<input v-model="sponsor.website" type="url" class="campo" placeholder="Website"></label>
                        <label class="rotulo sm:col-span-2 lg:col-span-4">Descrição<input v-model="sponsor.descricao" class="campo" placeholder="Descrição"></label>
                        <label class="rotulo">Trocar logótipo<input type="file" accept="image/*,.svg" class="ficheiro" @change="sponsor.novoLogo = $event.target.files[0]"></label>
                        <label class="rotulo">Ordem<input v-model.number="sponsor.ordem" type="number" min="0" class="campo"></label>
                        <label class="caixa self-end"><input v-model="sponsor.mostrar_no_slider" type="checkbox" class="chk">Mostrar no slider</label>
                        <label class="caixa self-end"><input v-model="sponsor.ativo" type="checkbox" class="chk">Ativo</label>
                    </div>

                    <!-- Fotos do ecrã -->
                    <div class="flex flex-col gap-2.5">
                        <h3 class="text-[13px] font-extrabold uppercase tracking-[0.06em] text-suave-2">Fotos para o ecrã ({{ (sponsor.images || []).length }})</h3>
                        <div v-if="sponsor.images?.length" class="flex flex-wrap gap-2.5">
                            <div v-for="img in sponsor.images" :key="img.id" class="relative h-24 w-36 overflow-hidden rounded-[10px] border border-linha bg-white">
                                <img :src="img.url" :alt="sponsor.empresa" class="h-full w-full object-contain p-1">
                                <button type="button" class="absolute right-1 top-1 grid h-11 w-11 place-items-center rounded-[10px] border border-linha-forte bg-white text-perigo hover:bg-perigo-claro" aria-label="Remover imagem" title="Remover imagem" @click="apagarImagem(img)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                                </button>
                                <span class="absolute bottom-1.5 left-1.5 rounded-full bg-tinta px-1.5 text-[11px] font-extrabold text-white">#{{ img.ordem }}</span>
                            </div>
                        </div>
                        <div v-else class="rounded-[10px] bg-white p-3 text-sm text-suave-2">Sem fotos adicionadas — no ecrã usa o logótipo.</div>

                        <div class="flex flex-wrap items-end gap-2.5">
                            <label class="rotulo min-w-48 flex-1">Nova foto<input type="file" accept="image/*,.svg" class="ficheiro" @change="sponsor.novaImagem = $event.target.files[0]"></label>
                            <label class="rotulo w-24">Ordem<input v-model.number="sponsor.imagemOrdem" type="number" min="0" class="campo"></label>
                            <button type="button" class="h-12 rounded-[10px] bg-tinta px-4 text-[15px] font-bold text-white hover:bg-escuro-2 disabled:opacity-40" :disabled="!sponsor.novaImagem" @click="adicionarImagem(sponsor)">Adicionar foto</button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2.5 border-t border-linha-fraca pt-4">
                        <button type="button" class="btn-pri h-[52px] px-6 text-base" @click="atualizar(sponsor)">Guardar</button>
                        <button type="button" class="btn-sec h-[52px] text-perigo hover:bg-perigo-claro" @click="apagar(sponsor)">Apagar patrocinador</button>
                    </div>
                </div>
            </article>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta focus:border-verde focus:ring-verde; }
.ficheiro { @apply min-h-12 w-full rounded-[10px] border border-dashed border-linha-forte bg-white p-2.5 text-sm text-tinta; }
.caixa { @apply flex h-12 cursor-pointer items-center gap-3 rounded-[10px] border border-linha-forte bg-white px-3.5 text-[15px] font-bold text-tinta; }
.chk { @apply h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde; }
</style>
