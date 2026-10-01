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

const euros = (v) => Number(v).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });

function eliminarOpcao(id) {
    if (!confirm('Eliminar esta opção?')) return;
    router.delete(route('alugueres.opcoes.destroy', id));
}
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[820px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-col gap-1">
                <a :href="route('alugueres.index')" class="inline-flex w-fit items-center gap-1 text-sm font-bold text-verde hover:text-verde-escuro">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                    Calendário
                </a>
                <h1 class="text-[30px] font-extrabold leading-tight">Opções do Salão</h1>
                <p class="text-[15px] text-suave">Configura as opções que aparecem ao criar um aluguer.</p>
            </div>

            <!-- Lista de opções -->
            <section class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <ul v-if="opcoes.length" class="divide-y divide-linha-fraca">
                    <li v-for="o in opcoes" :key="o.id">
                        <!-- Ver -->
                        <div v-if="editando !== o.id" class="flex items-center gap-3 px-4 py-3 sm:px-5" :class="o.ativo ? '' : 'opacity-70'">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold" :class="o.ativo ? 'text-tinta' : 'text-suave'">{{ o.nome }}</span>
                                    <span v-if="!o.ativo" class="rounded-full bg-fundo px-2 py-0.5 text-xs font-bold text-suave">Inativa</span>
                                </div>
                                <p v-if="o.descricao" class="text-sm text-suave">{{ o.descricao }}</p>
                            </div>
                            <span class="shrink-0 text-base font-extrabold" :class="o.preco_extra > 0 ? 'text-verde' : 'text-suave'">{{ o.preco_extra > 0 ? `+${euros(o.preco_extra)}` : 'Incluída' }}</span>
                            <button type="button" class="btn-sec h-11 shrink-0" @click="iniciarEditar(o)">Editar</button>
                        </div>

                        <!-- Editar inline -->
                        <form v-else class="grid gap-3 border-l-4 border-verde bg-fundo/60 px-4 py-4 sm:grid-cols-[1fr_1fr_2fr] sm:px-5" @submit.prevent="guardarOpcao(o.id)">
                            <p class="text-[13px] font-extrabold uppercase tracking-[0.06em] text-verde-escuro sm:col-span-3">A editar opção</p>
                            <label class="rotulo sm:col-span-3">Nome *
                                <input v-model="editForm.nome" type="text" class="campo" required />
                                <span v-if="editForm.errors.nome" class="text-xs text-perigo">{{ editForm.errors.nome }}</span>
                            </label>
                            <label class="rotulo sm:col-span-3">Descrição<input v-model="editForm.descricao" type="text" class="campo" /></label>
                            <label class="rotulo">Preço extra (€)<input v-model="editForm.preco_extra" type="number" step="0.01" min="0" class="campo" placeholder="0.00" /></label>
                            <label class="rotulo">Ordem<input v-model="editForm.ordem" type="number" min="0" class="campo" /></label>
                            <label class="flex h-12 cursor-pointer items-center gap-3 rounded-[10px] border border-linha-forte bg-white px-3.5 text-[15px] font-bold sm:col-span-3">
                                <input v-model="editForm.ativo" type="checkbox" class="h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde" />
                                Opção ativa (visível ao criar aluguer)
                            </label>
                            <div class="flex flex-wrap items-center gap-2 sm:col-span-3">
                                <button type="submit" :disabled="editForm.processing" class="btn-pri h-12 disabled:opacity-60">Guardar</button>
                                <button type="button" class="btn-sec h-12" @click="editando = null">Cancelar</button>
                                <button type="button" class="btn-sec ml-auto h-12 text-perigo hover:bg-perigo-claro" @click="eliminarOpcao(o.id)">Eliminar</button>
                            </div>
                        </form>
                    </li>
                </ul>
                <div v-else class="p-10 text-center text-suave-2">Ainda não há opções configuradas.</div>
            </section>

            <!-- Nova opção -->
            <section class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <h2 class="border-b border-linha-fraca px-4 py-4 text-lg font-extrabold sm:px-5">Adicionar nova opção</h2>
                <form @submit.prevent="criarOpcao">
                    <div class="grid gap-3 px-4 py-4 sm:grid-cols-[1fr_1fr_2fr] sm:px-5">
                        <label class="rotulo sm:col-span-3">Nome *
                            <input v-model="novaForm.nome" type="text" class="campo" placeholder="Ex: Com climatização" required />
                            <span v-if="novaForm.errors.nome" class="text-xs text-perigo">{{ novaForm.errors.nome }}</span>
                        </label>
                        <label class="rotulo sm:col-span-3">Descrição (opcional)<input v-model="novaForm.descricao" type="text" class="campo" placeholder="Breve descrição da opção" /></label>
                        <label class="rotulo">Preço extra (€)<input v-model="novaForm.preco_extra" type="number" step="0.01" min="0" class="campo" placeholder="0.00" /></label>
                        <label class="rotulo">Ordem<input v-model="novaForm.ordem" type="number" min="0" class="campo" placeholder="0" /></label>
                    </div>
                    <div class="border-t border-linha-fraca bg-fundo/60 px-4 py-4 sm:px-5">
                        <button type="submit" :disabled="novaForm.processing" class="btn-pri h-[52px] px-6 text-base disabled:opacity-60">Adicionar opção</button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta focus:border-verde focus:ring-verde; }
</style>
