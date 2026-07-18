<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ opcoes: Array });

// ── Formulário nova opção ─────────────────────────────────────────────────────
const novaForm = useForm({
    nome:        '',
    descricao:   '',
    preco_extra: '',
    ordem:       '',
});

function criarOpcao() {
    novaForm.post(route('alugueres.opcoes.store'), {
        onSuccess: () => novaForm.reset(),
    });
}

// ── Edição inline ─────────────────────────────────────────────────────────────
const editando = ref(null);
const editForm = useForm({
    nome:        '',
    descricao:   '',
    preco_extra: '',
    ativo:       true,
    ordem:       '',
});

function iniciarEditar(o) {
    editando.value = o.id;
    editForm.nome        = o.nome;
    editForm.descricao   = o.descricao ?? '';
    editForm.preco_extra = o.preco_extra;
    editForm.ativo       = o.ativo;
    editForm.ordem       = o.ordem;
    editForm.clearErrors();
}

function guardarOpcao(id) {
    editForm.patch(route('alugueres.opcoes.update', id), {
        onSuccess: () => { editando.value = null; },
    });
}

function eliminarOpcao(id) {
    if (!confirm('Eliminar esta opção?')) return;
    router.delete(route('alugueres.opcoes.destroy', id));
}
</script>

<template>
    <AppLayout>
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Opções do Salão</h1>
                <p class="mt-1 text-sm text-slate-500">Configure as opções disponíveis ao criar um aluguer.</p>
            </div>
            <a :href="route('alugueres.index')" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                ← Calendário
            </a>
        </div>

        <!-- Lista de opções -->
        <div class="mb-6 rounded-xl bg-white shadow-sm">
            <div v-if="opcoes.length" class="divide-y">
                <div v-for="o in opcoes" :key="o.id" class="px-5 py-4">
                    <!-- Ver -->
                    <template v-if="editando !== o.id">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-slate-900">{{ o.nome }}</span>
                                    <span v-if="!o.ativo" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-500">Inativa</span>
                                    <span v-if="o.preco_extra > 0" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">+{{ Number(o.preco_extra).toFixed(2) }}€</span>
                                </div>
                                <p v-if="o.descricao" class="mt-0.5 text-sm text-slate-500">{{ o.descricao }}</p>
                            </div>
                            <button @click="iniciarEditar(o)" class="rounded-md border border-slate-200 px-3 py-1.5 text-sm font-bold text-slate-600 hover:bg-slate-50">
                                Editar
                            </button>
                        </div>
                    </template>

                    <!-- Editar inline -->
                    <template v-else>
                        <form @submit.prevent="guardarOpcao(o.id)" class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-bold text-slate-600 uppercase tracking-wide">Nome *</label>
                                <input v-model="editForm.nome" type="text" class="w-full rounded-md border-slate-300 text-sm" required />
                                <p v-if="editForm.errors.nome" class="mt-1 text-xs text-red-600">{{ editForm.errors.nome }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-bold text-slate-600 uppercase tracking-wide">Descrição</label>
                                <input v-model="editForm.descricao" type="text" class="w-full rounded-md border-slate-300 text-sm" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-600 uppercase tracking-wide">Preço extra (€)</label>
                                <input v-model="editForm.preco_extra" type="number" step="0.01" min="0" class="w-full rounded-md border-slate-300 text-sm" placeholder="0.00" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-bold text-slate-600 uppercase tracking-wide">Ordem</label>
                                <input v-model="editForm.ordem" type="number" min="0" class="w-full rounded-md border-slate-300 text-sm" />
                            </div>
                            <div class="flex items-center gap-2 sm:col-span-2">
                                <input v-model="editForm.ativo" type="checkbox" id="ativo" class="rounded" />
                                <label for="ativo" class="text-sm font-semibold cursor-pointer">Opção ativa (visível ao criar aluguer)</label>
                            </div>
                            <div class="flex items-center gap-2 sm:col-span-2">
                                <button type="submit" :disabled="editForm.processing" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white disabled:opacity-60">Guardar</button>
                                <button type="button" @click="editando = null" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold">Cancelar</button>
                                <button type="button" @click="eliminarOpcao(o.id)" class="ml-auto rounded-md border border-red-300 px-3 py-2 text-sm font-bold text-red-600 hover:bg-red-50">Eliminar</button>
                            </div>
                        </form>
                    </template>
                </div>
            </div>
            <div v-else class="p-10 text-center text-slate-400">Ainda não há opções configuradas.</div>
        </div>

        <!-- Nova opção -->
        <div class="rounded-xl bg-white shadow-sm">
            <div class="border-b px-5 py-3">
                <h3 class="font-black text-slate-800">Adicionar nova opção</h3>
            </div>
            <form @submit.prevent="criarOpcao" class="grid gap-4 px-5 py-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-bold text-slate-600 uppercase tracking-wide">Nome *</label>
                    <input v-model="novaForm.nome" type="text" class="w-full rounded-md border-slate-300 text-sm" placeholder="Ex: Com climatização" required />
                    <p v-if="novaForm.errors.nome" class="mt-1 text-xs text-red-600">{{ novaForm.errors.nome }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-bold text-slate-600 uppercase tracking-wide">Descrição (opcional)</label>
                    <input v-model="novaForm.descricao" type="text" class="w-full rounded-md border-slate-300 text-sm" placeholder="Breve descrição da opção" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600 uppercase tracking-wide">Preço extra (€)</label>
                    <input v-model="novaForm.preco_extra" type="number" step="0.01" min="0" class="w-full rounded-md border-slate-300 text-sm" placeholder="0.00" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold text-slate-600 uppercase tracking-wide">Ordem</label>
                    <input v-model="novaForm.ordem" type="number" min="0" class="w-full rounded-md border-slate-300 text-sm" placeholder="0" />
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" :disabled="novaForm.processing" class="rounded-md bg-slate-900 px-5 py-2 text-sm font-bold text-white disabled:opacity-60">
                        Adicionar opção
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
