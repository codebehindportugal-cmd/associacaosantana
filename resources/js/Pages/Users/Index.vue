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
    ['cafe', 'Cafe'],
    ['restaurante', 'Restaurante'],
    ['cotas', 'Cotas'],
];

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
    editForm.put(route('users.update', user.id), {
        preserveScroll: true,
        onSuccess: cancelar,
    });
};

const apagar = (user) => {
    if (confirm(`Apagar o utilizador ${user.name}?`)) {
        useForm({}).delete(route('users.destroy', user.id), { preserveScroll: true });
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
    editPosForm.put(route('users.pos.update', terminal.id), {
        preserveScroll: true,
        onSuccess: cancelarPos,
    });
};

const apagarPos = (terminal) => {
    if (confirm(`Apagar o acesso POS ${terminal.nome}?`)) {
        useForm({}).delete(route('users.pos.destroy', terminal.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Utilizadores</h1>
            <p class="mt-1 text-sm text-slate-500">Criar acessos e gerir permissões de cada pessoa.</p>
        </div>

        <form class="mb-6 grid gap-3 rounded-lg bg-white p-5 shadow-sm md:grid-cols-[1fr_1fr_180px_180px_auto]" @submit.prevent="criar">
            <input v-model="createForm.name" class="rounded-md border-slate-300" placeholder="Nome">
            <input v-model="createForm.email" type="email" class="rounded-md border-slate-300" placeholder="Email">
            <input v-model="createForm.password" type="password" class="rounded-md border-slate-300" placeholder="Password">
            <select v-model="createForm.role" class="rounded-md border-slate-300">
                <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
            </select>
            <button class="rounded-md bg-slate-900 px-4 py-2 font-bold text-white disabled:opacity-50" :disabled="createForm.processing">Criar</button>
            <div v-if="Object.keys(createForm.errors).length" class="text-sm font-bold text-red-700 md:col-span-5">
                <div v-for="erro in createForm.errors" :key="erro">{{ erro }}</div>
            </div>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-3">Nome</th>
                        <th>Email</th>
                        <th>Perfil</th>
                        <th class="pr-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id" class="border-t align-top">
                        <template v-if="editingId === user.id">
                            <td class="p-3"><input v-model="editForm.name" class="w-full rounded-md border-slate-300"></td>
                            <td class="py-3"><input v-model="editForm.email" type="email" class="w-full rounded-md border-slate-300"></td>
                            <td class="py-3">
                                <select v-model="editForm.role" class="w-full rounded-md border-slate-300">
                                    <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                                </select>
                                <input v-model="editForm.password" type="password" class="mt-2 w-full rounded-md border-slate-300" placeholder="Nova password opcional">
                            </td>
                            <td class="space-x-2 p-3 text-right">
                                <button type="button" class="rounded-md border px-3 py-2 font-bold" @click="cancelar">Cancelar</button>
                                <button type="button" class="rounded-md bg-emerald-700 px-3 py-2 font-bold text-white" @click="guardar(user)">Guardar</button>
                            </td>
                        </template>
                        <template v-else>
                            <td class="p-3 font-semibold">{{ user.name }}</td>
                            <td class="py-3 text-slate-600">{{ user.email }}</td>
                            <td class="py-3"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black">{{ user.roles?.[0]?.name ?? 'sem perfil' }}</span></td>
                            <td class="space-x-2 p-3 text-right">
                                <button type="button" class="rounded-md border px-3 py-2 font-bold" @click="editar(user)">Editar</button>
                                <button type="button" class="rounded-md bg-red-600 px-3 py-2 font-bold text-white" @click="apagar(user)">Apagar</button>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mb-6 mt-10">
            <h2 class="text-2xl font-bold">Acessos POS</h2>
            <p class="mt-1 text-sm text-slate-500">Gerir terminais, localizações e PINs usados no login do POS.</p>
        </div>

        <form class="mb-6 grid gap-3 rounded-lg bg-white p-5 shadow-sm md:grid-cols-[1fr_1fr_160px_140px_120px_auto]" @submit.prevent="criarPos">
            <input v-model="posForm.nome" class="rounded-md border-slate-300" placeholder="Nome do terminal">
            <input v-model="posForm.localizacao" class="rounded-md border-slate-300" placeholder="Localização/ponto">
            <select v-model="posForm.tipo" class="rounded-md border-slate-300">
                <option v-for="[valor, label] in tiposPos" :key="valor" :value="valor">{{ label }}</option>
            </select>
            <input v-model="posForm.pin" type="password" class="rounded-md border-slate-300" placeholder="PIN">
            <label class="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 font-bold">
                <input v-model="posForm.ativo" type="checkbox" class="rounded border-slate-300">
                Ativo
            </label>
            <button class="rounded-md bg-slate-900 px-4 py-2 font-bold text-white disabled:opacity-50" :disabled="posForm.processing">Criar POS</button>
            <div v-if="Object.keys(posForm.errors).length" class="text-sm font-bold text-red-700 md:col-span-6">
                <div v-for="erro in posForm.errors" :key="erro">{{ erro }}</div>
            </div>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-3">Terminal</th>
                        <th>Localização</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th class="pr-3 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="terminal in posTerminais" :key="terminal.id" class="border-t align-top">
                        <template v-if="editingPosId === terminal.id">
                            <td class="p-3"><input v-model="editPosForm.nome" class="w-full rounded-md border-slate-300"></td>
                            <td class="py-3"><input v-model="editPosForm.localizacao" class="w-full rounded-md border-slate-300"></td>
                            <td class="py-3">
                                <select v-model="editPosForm.tipo" class="w-full rounded-md border-slate-300">
                                    <option v-for="[valor, label] in tiposPos" :key="valor" :value="valor">{{ label }}</option>
                                </select>
                                <input v-model="editPosForm.pin" type="password" class="mt-2 w-full rounded-md border-slate-300" placeholder="Novo PIN opcional">
                            </td>
                            <td class="py-3">
                                <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 font-bold">
                                    <input v-model="editPosForm.ativo" type="checkbox" class="rounded border-slate-300">
                                    Ativo
                                </label>
                            </td>
                            <td class="space-x-2 p-3 text-right">
                                <button type="button" class="rounded-md border px-3 py-2 font-bold" @click="cancelarPos">Cancelar</button>
                                <button type="button" class="rounded-md bg-emerald-700 px-3 py-2 font-bold text-white" @click="guardarPos(terminal)">Guardar</button>
                            </td>
                        </template>
                        <template v-else>
                            <td class="p-3 font-semibold">{{ terminal.nome }}</td>
                            <td class="py-3 text-slate-600">{{ terminal.localizacao ?? '-' }}</td>
                            <td class="py-3"><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black">{{ terminal.tipo }}</span></td>
                            <td class="py-3"><span class="rounded-full px-3 py-1 text-xs font-black" :class="terminal.ativo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'">{{ terminal.ativo ? 'ativo' : 'inativo' }}</span></td>
                            <td class="space-x-2 p-3 text-right">
                                <button type="button" class="rounded-md border px-3 py-2 font-bold" @click="editarPos(terminal)">Editar</button>
                                <button type="button" class="rounded-md bg-red-600 px-3 py-2 font-bold text-white" @click="apagarPos(terminal)">Apagar</button>
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
