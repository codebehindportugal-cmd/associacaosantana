<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ terminais: Array });
const escolhido = ref(null);
const form = useForm({ terminal_id: '', pin: '' });
const icone = (tipo) => ({ bar: '🍺', restaurante: '🍽️', cotas: '💳' }[tipo] || '🔐');
const tituloTipo = (tipo) => ({ bar: 'Bar', restaurante: 'Restaurante', cotas: 'Cotas' }[tipo] || tipo);
const terminal = computed(() => (props.terminais ?? []).find((item) => item.id === escolhido.value));
const escolher = (item) => { escolhido.value = item.id; form.terminal_id = item.id; form.pin = ''; };
const entrar = () => form.post(route('pos.login.store'));
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <section class="mx-auto max-w-5xl">
            <h1 class="mb-6 text-center text-4xl font-black">POS Associação de Santana</h1>
            <div class="grid gap-4 md:grid-cols-3">
                <button v-for="item in terminais" :key="item.id" type="button" class="rounded-lg border-2 p-5 text-left" :class="escolhido === item.id ? 'border-emerald-400 bg-gray-800' : 'border-gray-700 bg-gray-800/60'" @click="escolher(item)">
                    <div class="text-5xl">{{ icone(item.tipo) }}</div>
                    <div class="mt-3 text-2xl font-black">{{ item.nome }}</div>
                    <div class="font-bold text-gray-300">{{ item.localizacao }}</div>
                    <div class="mt-3 inline-flex rounded bg-gray-700 px-3 py-1 text-sm font-black uppercase">{{ tituloTipo(item.tipo) }}</div>
                </button>
            </div>
            <form v-if="terminal" class="mx-auto mt-6 max-w-md rounded-lg bg-gray-800 p-5" @submit.prevent="entrar">
                <h2 class="mb-4 text-center text-2xl font-black">{{ terminal.nome }}</h2>
                <input v-model="form.pin" type="password" inputmode="numeric" autocomplete="off" autofocus class="w-full rounded-lg border-gray-600 bg-gray-900 p-4 text-center text-3xl font-black text-white" placeholder="PIN">
                <div v-if="form.errors.pin" class="mt-3 rounded bg-red-600 p-3 text-center font-bold">{{ form.errors.pin }}</div>
                <button class="mt-4 w-full rounded-lg bg-emerald-600 p-5 text-xl font-black disabled:opacity-50" :disabled="form.processing">ENTRAR</button>
            </form>
        </section>
    </main>
</template>
