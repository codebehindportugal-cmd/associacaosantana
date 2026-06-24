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

const apagarImagem = (imagem) => {
    if (!confirm('Remover esta imagem?')) return;
    router.delete(route('patrocinadores.imagens.destroy', imagem.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Patrocinadores" />

    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black">Patrocinadores</h1>
                <p class="mt-1 text-sm text-slate-500">Gere logos, fotos e visibilidade dos patrocinadores no site.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="route('patrocinios.ecra')" target="_blank" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800">Ecrã da festa</a>
                <a :href="route('patrocinios.index')" target="_blank" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold hover:bg-white">Ver página pública</a>
            </div>
        </div>

        <form class="mb-8 rounded-lg bg-white p-5 shadow-sm" @submit.prevent="criar">
            <h2 class="mb-4 font-black">Novo patrocinador</h2>
            <div class="grid gap-3 md:grid-cols-4">
                <input v-model="novo.empresa" required placeholder="Empresa" class="rounded-md border-slate-300 md:col-span-2">
                <input v-model="novo.website" type="url" placeholder="Website com https://" class="rounded-md border-slate-300 md:col-span-2">
                <input v-model="novo.descricao" placeholder="Descrição curta" class="rounded-md border-slate-300 md:col-span-2">
                <input v-model.number="novo.ordem" type="number" min="0" placeholder="Ordem" class="rounded-md border-slate-300">
                <input ref="logoNovo" type="file" accept="image/*,.svg" class="rounded-md border border-slate-300 p-2 text-sm" @change="novo.logotipo = $event.target.files[0]">
                <label class="flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-bold">
                    <input v-model="novo.mostrar_no_slider" type="checkbox" class="rounded border-slate-300">
                    Slider
                </label>
                <label class="flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-bold">
                    <input v-model="novo.ativo" type="checkbox" class="rounded border-slate-300">
                    Ativo
                </label>
            </div>
            <button class="mt-4 rounded-md bg-slate-900 px-5 py-3 font-black text-white disabled:opacity-60" :disabled="novo.processing">Criar patrocinador</button>
        </form>

        <div v-if="!items.length" class="rounded-lg bg-white p-6 text-center font-bold text-slate-500 shadow-sm">
            Ainda não existem patrocinadores.
        </div>

        <div class="grid gap-4">
            <article v-for="sponsor in items" :key="sponsor.id" class="rounded-lg bg-white p-5 shadow-sm">
                <!-- Linha principal: logo / campos / botões -->
                <div class="grid gap-4 lg:grid-cols-[180px_1fr_auto]">
                    <div class="grid min-h-32 place-items-center rounded-md bg-slate-50 p-4">
                        <img :src="sponsor.logo_url" :alt="sponsor.empresa" class="max-h-24 max-w-full object-contain">
                    </div>

                    <div class="grid gap-3 md:grid-cols-4">
                        <input v-model="sponsor.empresa" class="rounded-md border-slate-300 md:col-span-2">
                        <input v-model="sponsor.website" type="url" class="rounded-md border-slate-300 md:col-span-2" placeholder="Website">
                        <input v-model="sponsor.descricao" class="rounded-md border-slate-300 md:col-span-2" placeholder="Descrição">
                        <input v-model.number="sponsor.ordem" type="number" min="0" class="rounded-md border-slate-300">
                        <input type="file" accept="image/*,.svg" class="rounded-md border border-slate-300 p-2 text-sm" @change="sponsor.novoLogo = $event.target.files[0]">
                        <label class="flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-bold">
                            <input v-model="sponsor.mostrar_no_slider" type="checkbox" class="rounded border-slate-300">
                            Slider
                        </label>
                        <label class="flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-bold">
                            <input v-model="sponsor.ativo" type="checkbox" class="rounded border-slate-300">
                            Ativo
                        </label>
                    </div>

                    <div class="flex flex-row gap-2 lg:flex-col">
                        <button type="button" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-bold text-white" @click="atualizar(sponsor)">Guardar</button>
                        <button type="button" class="rounded-md border border-rose-200 px-4 py-2 text-sm font-bold text-rose-700 hover:bg-rose-50" @click="apagar(sponsor)">Apagar</button>
                    </div>
                </div>

                <!-- Secção de imagens do ecrã -->
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <h3 class="mb-3 text-sm font-black uppercase tracking-wide text-slate-500">
                        Fotos para o ecrã
                        <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-600">{{ (sponsor.images || []).length }}</span>
                    </h3>

                    <!-- Miniaturas existentes -->
                    <div v-if="sponsor.images?.length" class="mb-4 flex flex-wrap gap-3">
                        <div
                            v-for="img in sponsor.images"
                            :key="img.id"
                            class="group relative h-24 w-36 overflow-hidden rounded-md border border-slate-200 bg-slate-50"
                        >
                            <img :src="img.url" :alt="sponsor.empresa" class="h-full w-full object-contain p-1">
                            <button
                                type="button"
                                class="absolute right-1 top-1 hidden rounded bg-rose-600 p-1 text-white group-hover:flex"
                                title="Remover imagem"
                                @click="apagarImagem(img)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div class="absolute bottom-0 inset-x-0 bg-black/40 px-1.5 py-0.5 text-center text-xs text-white/80">
                                #{{ img.ordem }}
                            </div>
                        </div>
                    </div>

                    <div v-else class="mb-4 rounded-md bg-slate-50 p-3 text-sm text-slate-400">
                        Sem fotos adicionadas — no ecrã usa o logótipo.
                    </div>

                    <!-- Upload nova imagem -->
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-48">
                            <label class="mb-1 block text-xs font-bold text-slate-500">Nova foto</label>
                            <input
                                type="file"
                                accept="image/*,.svg"
                                class="w-full rounded-md border border-slate-300 p-2 text-sm"
                                @change="sponsor.novaImagem = $event.target.files[0]"
                            >
                        </div>
                        <div class="w-24">
                            <label class="mb-1 block text-xs font-bold text-slate-500">Ordem</label>
                            <input v-model.number="sponsor.imagemOrdem" type="number" min="0" class="w-full rounded-md border-slate-300 text-sm">
                        </div>
                        <button
                            type="button"
                            class="rounded-md bg-slate-700 px-4 py-2 text-sm font-bold text-white hover:bg-slate-800 disabled:opacity-40"
                            :disabled="!sponsor.novaImagem"
                            @click="adicionarImagem(sponsor)"
                        >
                            Adicionar
                        </button>
                    </div>
                </div>
            </article>
        </div>
    </AppLayout>
</template>
