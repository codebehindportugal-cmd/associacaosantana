<script setup>
import { Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ proximoNumero: String });
const form = useForm({ numero_socio: props.proximoNumero, nome: '', telefone: '', email: '' });
</script>

<template>
    <main class="flex min-h-screen items-center justify-center bg-gray-900 p-5 text-white">
        <form class="w-full max-w-lg rounded-lg bg-gray-800 p-5" @submit.prevent="form.post(route('pos.cotas.socio.novo'))">
            <h1 class="mb-5 text-3xl font-black">NOVO SÓCIO</h1>
            <label class="mb-3 block font-black">Número de sócio<input v-model="form.numero_socio" class="mt-1 w-full rounded bg-gray-900 p-4 text-xl text-white"></label>
            <label class="mb-3 block font-black">Nome<input v-model="form.nome" class="mt-1 w-full rounded bg-gray-900 p-4 text-xl text-white"></label>
            <label class="mb-3 block font-black">Telefone<input v-model="form.telefone" class="mt-1 w-full rounded bg-gray-900 p-4 text-xl text-white"></label>
            <label class="mb-3 block font-black">Email<input v-model="form.email" class="mt-1 w-full rounded bg-gray-900 p-4 text-xl text-white"></label>
            <div v-if="Object.keys(form.errors).length" class="mb-3 rounded bg-red-700 p-3 font-bold">{{ Object.values(form.errors)[0] }}</div>
            <button class="w-full rounded bg-emerald-600 p-5 text-xl font-black">✅ CRIAR SÓCIO</button>
            <Link :href="route('pos.cotas.index')" class="mt-3 block rounded bg-gray-700 p-4 text-center font-black">← CANCELAR</Link>
        </form>
    </main>
</template>
