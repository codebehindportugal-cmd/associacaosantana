<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ users: Array, roles: Array, posTerminais: Array });

const editingId = ref(null);
const editingPosId = ref(null);
const createForm = useForm({ name: '', email: '', password: '', role: props.roles?.[0] ?? '' });
const editForm = useForm({ name: '', email: '', password: '', role: '' });
const posForm = useForm({ nome: '', pin: '', localizacao: '', tipo: 'bar', ativo: true });
const editPosForm = useForm({ nome: '', pin: '', localizacao: '', tipo: 'bar', ativo: true });
const tiposPos = [
    ['bar', 'Bar'],
    ['cafe', 'Café'],
    ['restaurante', 'Restaurante'],
    ['reservas', 'Reservas'],
    ['cotas', 'Cotas'],
];

// Formatação para a UI (os valores enviados continuam os nomes reais dos perfis)
const rotuloPerfil = (role) => {
    if (!role) return 'sem perfil';
    const t = String(role).replace(/[_-]+/g, ' ');
    return t.charAt(0).toUpperCase() + t.slice(1);
};
const corPerfil = (role) => {
    const r = String(role ?? '');
    if (r === 'admin') return 'bg-tinta text-white';
    if (r.includes('bar')) return 'bg-[#E8EEFA] text-azul';
    if (r.includes('tesour')) return 'bg-laranja-claro text-laranja-texto';
    if (r.includes('comiss')) return 'bg-[#F2EAF7] text-roxo';
    if (!r) return 'bg-fundo text-suave';
    return 'bg-verde-claro text-verde-escuro';
};
const iniciais = (nome) => String(nome ?? '').trim().split(/\s+/).filter(Boolean).map((p) => p[0]).filter((_, i, a) => i === 0 || i === a.length - 1).join('').toUpperCase();
const rotuloTipo = (tipo) => tiposPos.find(([v]) => v === tipo)?.[1] ?? tipo;

const criar = () => {
    createForm.post(route('users.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name', 'email', 'password'),
    });
};

const editar = (user) => {
    editingId.value = user.id;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.role = user.roles?.[0]?.name ?? props.roles?.[0] ?? '';
};

const cancelar = () => {
    editingId.value = null;
    editForm.reset();
};

const guardar = (user) => {
    editForm.patch(route('users.update', user.id), {
        preserveScroll: true,
        onSuccess: cancelar,
    });
};

const erroApagar = ref('');
const apagar = (user) => {
    if (confirm(`Apagar o utilizador ${user.name}?`)) {
        erroApagar.value = '';
        useForm({}).delete(route('users.destroy', user.id), {
            preserveScroll: true,
            onError: (erros) => { erroApagar.value = Object.values(erros).join(' '); },
        });
    }
};

const criarPos = () => {
    posForm.post(route('users.pos.store'), {
        preserveScroll: true,
        onSuccess: () => posForm.reset('nome', 'pin', 'localizacao'),
    });
};

const editarPos = (terminal) => {
    editingPosId.value = terminal.id;
    editPosForm.nome = terminal.nome;
    editPosForm.pin = '';
    editPosForm.localizacao = terminal.localizacao ?? '';
    editPosForm.tipo = terminal.tipo;
    editPosForm.ativo = Boolean(terminal.ativo);
};

const cancelarPos = () => {
    editingPosId.value = null;
    editPosForm.reset();
};

const guardarPos = (terminal) => {
    editPosForm.patch(route('users.pos.update', terminal.id), {
        preserveScroll: true,
        onSuccess: cancelarPos,
    });
};

const erroApagarPos = ref('');
const apagarPos = (terminal) => {
    if (confirm(`Apagar o acesso POS ${terminal.nome}?`)) {
        erroApagarPos.value = '';
        useForm({}).delete(route('users.pos.destroy', terminal.id), {
            preserveScroll: true,
            onError: (erros) => { erroApagarPos.value = Object.values(erros).join(' '); },
        });
    }
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-[30px] font-extrabold leading-tight">Utilizadores</h1>
                    <p class="text-[15px] text-suave">Criar acessos e gerir permissões de cada pessoa.</p>
                </div>
                <nav aria-label="Secções" class="flex gap-2">
                    <a href="#pessoas" class="pilula">Pessoas <span class="text-xs text-suave-2">{{ users?.length ?? 0 }}</span></a>
                    <a href="#pos" class="pilula">Acessos POS <span class="text-xs text-suave-2">{{ posTerminais?.length ?? 0 }}</span></a>
                </nav>
            </div>

            <!-- ── Pessoas do backoffice ─────────────────────────────── -->
            <div id="pessoas" class="flex scroll-mt-6 flex-wrap items-end justify-between gap-x-4 gap-y-1">
                <h2 class="text-xl font-extrabold">Pessoas do backoffice</h2>
                <div v-if="erroApagar" role="alert" class="order-last w-full rounded-[10px] bg-perigo-claro p-3 font-semibold text-perigo-texto">{{ erroApagar }}</div>
                <p class="text-[13px] text-suave">Cada pessoa entra com email e password. O perfil decide o que vê.</p>
            </div>

            <form class="cartao grid gap-3 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_1fr_1fr_1fr_auto]" @submit.prevent="criar">
                <h3 class="flex items-center gap-2.5 text-base font-extrabold sm:col-span-2 lg:col-span-5">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-verde-claro text-verde" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg></span>
                    Adicionar pessoa
                </h3>
                <label class="rotulo">Nome<input v-model="createForm.name" class="campo" placeholder="Ex.: Maria Santos"></label>
                <label class="rotulo">Email<input v-model="createForm.email" type="email" class="campo" placeholder="nome@exemplo.pt"></label>
                <label class="rotulo">Password<input v-model="createForm.password" type="password" class="campo" placeholder="Password"></label>
                <label class="rotulo">Perfil
                    <select v-model="createForm.role" class="campo">
                        <option v-for="role in roles" :key="role" :value="role">{{ rotuloPerfil(role) }}</option>
                    </select>
                </label>
                <button class="btn-pri h-12 self-end disabled:opacity-50 sm:col-span-2 lg:col-span-1" :disabled="createForm.processing">Criar utilizador</button>
                <div v-if="Object.keys(createForm.errors).length" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto sm:col-span-2 lg:col-span-5">
                    <div v-for="erro in createForm.errors" :key="erro">{{ erro }}</div>
                </div>
            </form>

            <div class="cartao overflow-hidden">
                <div class="hidden grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto] gap-3 border-b border-linha-fraca px-5 py-3 text-[13px] font-semibold text-suave md:grid">
                    <span>Pessoa</span><span>Perfil</span><span class="text-right">Ações</span>
                </div>
                <ul class="divide-y divide-linha-fraca">
                    <li v-for="user in users" :key="user.id">
                        <form v-if="editingId === user.id" class="grid gap-3 border-l-4 border-verde bg-verde-claro/40 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_auto]" @submit.prevent="guardar(user)">
                            <p class="text-[13px] font-extrabold text-verde-escuro sm:col-span-2 lg:col-span-5">A editar {{ user.name }}</p>
                            <label class="rotulo">Nome<input v-model="editForm.name" class="campo"></label>
                            <label class="rotulo">Email<input v-model="editForm.email" type="email" class="campo"></label>
                            <label class="rotulo">Perfil
                                <select v-model="editForm.role" class="campo">
                                    <option v-for="role in roles" :key="role" :value="role">{{ rotuloPerfil(role) }}</option>
                                </select>
                            </label>
                            <label class="rotulo">Nova password (opcional)<input v-model="editForm.password" type="password" class="campo" placeholder="Em branco: mantém"></label>
                            <div class="flex gap-2 self-end sm:col-span-2 lg:col-span-1">
                                <button type="button" class="btn-sec h-12 flex-1" @click="cancelar">Cancelar</button>
                                <button type="submit" class="btn-pri h-12 flex-1" :disabled="editForm.processing">Guardar</button>
                            </div>
                            <div v-if="Object.keys(editForm.errors).length" class="text-sm font-bold text-perigo-texto sm:col-span-2 lg:col-span-5">
                                <div v-for="erro in editForm.errors" :key="erro">{{ erro }}</div>
                            </div>
                        </form>
                        <div v-else class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-4 py-3 sm:px-5 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto]">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-verde-claro text-sm font-extrabold text-verde-escuro" aria-hidden="true">{{ iniciais(user.name) }}</span>
                                <div class="min-w-0">
                                    <div class="truncate font-bold">{{ user.name }}</div>
                                    <div class="truncate text-[13px] text-suave">{{ user.email }}</div>
                                    <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-extrabold md:hidden" :class="corPerfil(user.roles?.[0]?.name)">{{ rotuloPerfil(user.roles?.[0]?.name) }}</span>
                                </div>
                            </div>
                            <div class="hidden md:block"><span class="rounded-full px-3 py-1 text-[13px] font-extrabold" :class="corPerfil(user.roles?.[0]?.name)">{{ rotuloPerfil(user.roles?.[0]?.name) }}</span></div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn-sec h-11" @click="editar(user)">Editar</button>
                                <button type="button" class="btn-apagar" :aria-label="`Apagar o utilizador ${user.name}`" title="Apagar" @click="apagar(user)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- ── Acessos POS ───────────────────────────────────────── -->
            <div id="pos" class="mt-4 flex scroll-mt-6 flex-wrap items-end justify-between gap-x-4 gap-y-1">
                <h2 class="text-xl font-extrabold">Acessos POS</h2>
                <div v-if="erroApagarPos" role="alert" class="order-last w-full rounded-[10px] bg-perigo-claro p-3 font-semibold text-perigo-texto">{{ erroApagarPos }}</div>
                <p class="text-[13px] text-suave">Gerir terminais, localizações e PINs usados no login do POS.</p>
            </div>

            <form class="cartao grid gap-3 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_1fr_1fr_1fr_auto_auto]" @submit.prevent="criarPos">
                <h3 class="flex items-center gap-2.5 text-base font-extrabold sm:col-span-2 lg:col-span-6">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-verde-claro text-verde" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg></span>
                    Adicionar terminal POS
                </h3>
                <label class="rotulo">Nome do terminal<input v-model="posForm.nome" class="campo" placeholder="Ex.: Bar 2"></label>
                <label class="rotulo">Localização/ponto<input v-model="posForm.localizacao" class="campo" placeholder="Ex.: Tenda"></label>
                <label class="rotulo">Tipo
                    <select v-model="posForm.tipo" class="campo">
                        <option v-for="[valor, label] in tiposPos" :key="valor" :value="valor">{{ label }}</option>
                    </select>
                </label>
                <label class="rotulo">PIN<input v-model="posForm.pin" type="password" class="campo" placeholder="PIN"></label>
                <label class="caixa self-end"><input v-model="posForm.ativo" type="checkbox" class="chk">Ativo</label>
                <button class="btn-pri h-12 self-end disabled:opacity-50" :disabled="posForm.processing">Criar POS</button>
                <div v-if="Object.keys(posForm.errors).length" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto sm:col-span-2 lg:col-span-6">
                    <div v-for="erro in posForm.errors" :key="erro">{{ erro }}</div>
                </div>
            </form>

            <div class="cartao overflow-hidden">
                <div class="hidden grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_auto] gap-3 border-b border-linha-fraca px-5 py-3 text-[13px] font-semibold text-suave md:grid">
                    <span>Terminal</span><span>Tipo</span><span>Estado</span><span class="text-right">Ações</span>
                </div>
                <ul class="divide-y divide-linha-fraca">
                    <li v-if="!posTerminais?.length" class="p-6 text-center text-sm font-bold text-suave-2">Ainda não há terminais POS.</li>
                    <li v-for="terminal in posTerminais" :key="terminal.id">
                        <form v-if="editingPosId === terminal.id" class="grid gap-3 border-l-4 border-verde bg-verde-claro/40 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_1fr_auto_auto]" @submit.prevent="guardarPos(terminal)">
                            <p class="text-[13px] font-extrabold text-verde-escuro sm:col-span-2 lg:col-span-6">A editar {{ terminal.nome }}</p>
                            <label class="rotulo">Nome do terminal<input v-model="editPosForm.nome" class="campo"></label>
                            <label class="rotulo">Localização/ponto<input v-model="editPosForm.localizacao" class="campo"></label>
                            <label class="rotulo">Tipo
                                <select v-model="editPosForm.tipo" class="campo">
                                    <option v-for="[valor, label] in tiposPos" :key="valor" :value="valor">{{ label }}</option>
                                </select>
                            </label>
                            <label class="rotulo">Novo PIN (opcional)<input v-model="editPosForm.pin" type="password" class="campo" placeholder="Em branco: mantém"></label>
                            <label class="caixa self-end"><input v-model="editPosForm.ativo" type="checkbox" class="chk">Ativo</label>
                            <div class="flex gap-2 self-end">
                                <button type="button" class="btn-sec h-12 flex-1" @click="cancelarPos">Cancelar</button>
                                <button type="submit" class="btn-pri h-12 flex-1" :disabled="editPosForm.processing">Guardar</button>
                            </div>
                            <div v-if="Object.keys(editPosForm.errors).length" class="text-sm font-bold text-perigo-texto sm:col-span-2 lg:col-span-6">
                                <div v-for="erro in editPosForm.errors" :key="erro">{{ erro }}</div>
                            </div>
                        </form>
                        <div v-else class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-4 py-3 sm:px-5 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
                            <div class="min-w-0">
                                <div class="truncate font-bold">{{ terminal.nome }}</div>
                                <div class="truncate text-[13px] text-suave">{{ terminal.localizacao ?? '—' }}</div>
                                <div class="mt-1 flex flex-wrap items-center gap-2 md:hidden">
                                    <span class="rounded-full bg-fundo px-2.5 py-0.5 text-xs font-bold text-suave">{{ rotuloTipo(terminal.tipo) }}</span>
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold" :class="terminal.ativo ? 'text-verde-escuro' : 'text-suave'"><span class="h-2 w-2 rounded-full" :class="terminal.ativo ? 'bg-verde-ok' : 'bg-suave-2/50'"></span>{{ terminal.ativo ? 'Ativo' : 'Inativo' }}</span>
                                </div>
                            </div>
                            <div class="hidden md:block"><span class="rounded-full bg-fundo px-3 py-1 text-[13px] font-bold text-suave">{{ rotuloTipo(terminal.tipo) }}</span></div>
                            <div class="hidden md:block"><span class="inline-flex items-center gap-2 text-sm font-bold" :class="terminal.ativo ? 'text-verde-escuro' : 'text-suave'"><span class="h-2 w-2 rounded-full" :class="terminal.ativo ? 'bg-verde-ok' : 'bg-suave-2/50'"></span>{{ terminal.ativo ? 'Ativo' : 'Inativo' }}</span></div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn-sec h-11" @click="editarPos(terminal)">Editar</button>
                                <button type="button" class="btn-apagar" :aria-label="`Apagar o acesso POS ${terminal.nome}`" title="Apagar" @click="apagarPos(terminal)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.btn-apagar { @apply grid h-11 w-11 shrink-0 place-items-center rounded-[10px] border border-linha-forte bg-white text-perigo hover:bg-perigo-claro; }
.pilula { @apply inline-flex h-11 items-center gap-2 rounded-full border border-linha-forte bg-white px-4 text-sm font-bold text-tinta hover:bg-fundo; }
.cartao { @apply rounded-[14px] border border-linha bg-white; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta focus:border-verde focus:ring-verde; }
.caixa { @apply flex h-12 cursor-pointer items-center gap-3 rounded-[10px] border border-linha-forte bg-white px-3.5 text-[15px] font-bold text-tinta; }
.chk { @apply h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde; }
</style>
