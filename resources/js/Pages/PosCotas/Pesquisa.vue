<script setup>
import { Link, router } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
const props = defineProps({ socios: Array, query: String });
const q = ref(props.query || '');
let timeout = null;
const teclas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'.split('');
const pesquisar = () => router.get(route('pos.cotas.socio.pesquisa'), { q: q.value }, { preserveState: true, replace: true });
watch(q, () => { clearTimeout(timeout); timeout = setTimeout(pesquisar, 300); });
onMounted(() => document.querySelector('#pesquisa-socio')?.focus());
const tecla = (t) => { if (t === 'del') q.value = q.value.slice(0, -1); else q.value += t; };
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-4 flex items-center gap-3"><Link :href="route('pos.cotas.index')" class="rounded-lg bg-gray-800 px-4 py-3 font-black">←</Link><input id="pesquisa-socio" v-model="q" class="min-w-0 flex-1 rounded-lg border-gray-700 bg-gray-800 p-4 text-2xl font-black text-white" placeholder="Pesquisar sócio"></header>
        <div class="mb-2 grid grid-cols-7 gap-2 md:grid-cols-10">
            <button v-for="t in teclas" :key="t" class="rounded bg-gray-800 p-3 text-sm font-black sm:text-base" @click="tecla(t)">{{ t }}</button>
        </div>
        <div class="mb-5 grid grid-cols-2 gap-2">
            <button class="rounded bg-gray-800 p-3 font-black" @click="tecla(' ')">ESPAÇO</button>
            <button class="rounded bg-red-700 p-3 font-black" @click="tecla('del')">APAGAR</button>
        </div>
        <div v-if="!socios.length" class="rounded-lg bg-gray-800 p-6 text-center"><div class="mb-4 font-black">Nenhum sócio encontrado</div><Link :href="route('pos.cotas.socio.novo.form')" class="rounded bg-emerald-600 px-5 py-3 font-black">+ NOVO SÓCIO</Link></div>
        <div class="grid gap-3"><Link v-for="socio in socios" :key="socio.id" :href="route('pos.cotas.socio', socio.id)" class="rounded-lg bg-gray-800 p-4"><div class="text-2xl font-black">{{ socio.nome }}</div><div class="font-bold text-gray-300">N.º {{ socio.numero_socio }}</div><div class="mt-2 inline-flex rounded px-3 py-1 font-black" :class="socio.cota_em_dia ? 'bg-emerald-600' : 'bg-red-600'">{{ socio.cota_em_dia ? '✅ EM DIA' : `⚠️ ${socio.meses_em_atraso} meses em atraso` }}</div></Link></div>
    </main>
</template>
