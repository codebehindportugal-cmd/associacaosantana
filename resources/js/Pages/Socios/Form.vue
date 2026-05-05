<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ socio: Object });
const form = useForm(props.socio ?? { numero_socio: '', nome: '', email: '', telefone: '', morada: '', data_nascimento: '', data_inscricao: new Date().toISOString().slice(0, 10), estado: 'ativo' });
const submit = () => props.socio ? form.put(route('socios.update', props.socio.id)) : form.post(route('socios.store'));
</script>

<template>
    <AppLayout>
        <h1 class="mb-6 text-2xl font-bold">{{ socio ? 'Editar sócio' : 'Novo sócio' }}</h1>
        <form class="grid max-w-3xl gap-4 rounded-lg bg-white p-6 shadow-sm md:grid-cols-2" @submit.prevent="submit">
            <input v-model="form.numero_socio" class="rounded-md border-slate-300" placeholder="Número">
            <input v-model="form.nome" class="rounded-md border-slate-300" placeholder="Nome">
            <input v-model="form.email" class="rounded-md border-slate-300" placeholder="Email">
            <input v-model="form.telefone" class="rounded-md border-slate-300" placeholder="Telefone">
            <input v-model="form.data_nascimento" type="date" class="rounded-md border-slate-300">
            <input v-model="form.data_inscricao" type="date" class="rounded-md border-slate-300">
            <select v-model="form.estado" class="rounded-md border-slate-300"><option>ativo</option><option>inativo</option></select>
            <textarea v-model="form.morada" class="rounded-md border-slate-300 md:col-span-2" placeholder="Morada"></textarea>
            <button class="rounded-md bg-slate-900 px-4 py-2 text-white md:col-span-2">Guardar</button>
        </form>
    </AppLayout>
</template>
