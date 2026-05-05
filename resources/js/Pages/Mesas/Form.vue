<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ mesa: Object });
const form = useForm(props.mesa ?? { numero: '', nome: '', capacidade: 10, localizacao: 'sala', estado: 'livre' });
const submit = () => props.mesa ? form.put(route('mesas.update', props.mesa.id)) : form.post(route('mesas.store'));
</script>

<template>
    <AppLayout>
        <h1 class="mb-6 text-2xl font-bold">{{ mesa ? 'Editar mesa' : 'Nova mesa' }}</h1>
        <form class="max-w-xl space-y-4 rounded-lg bg-white p-6 shadow-sm" @submit.prevent="submit">
            <input v-model="form.numero" class="w-full rounded-md border-slate-300" placeholder="Número">
            <input v-model="form.nome" class="w-full rounded-md border-slate-300" placeholder="Nome">
            <input v-model="form.capacidade" type="number" class="w-full rounded-md border-slate-300" placeholder="Capacidade">
            <select v-model="form.localizacao" class="w-full rounded-md border-slate-300"><option>sala</option><option>interior</option><option>exterior</option><option>bar</option></select>
            <select v-model="form.estado" class="w-full rounded-md border-slate-300"><option>livre</option><option>ocupada</option><option>reservada</option></select>
            <button class="rounded-md bg-slate-900 px-4 py-2 text-white">Guardar</button>
        </form>
    </AppLayout>
</template>
