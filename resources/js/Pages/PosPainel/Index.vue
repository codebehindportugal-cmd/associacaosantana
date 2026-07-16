<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    terminais: Array,
    chamadasComissao: Array,
    chamadasCliente: Array,
});

let timer = null;
onMounted(() => {
    // Atualiza o painel a cada 15s
    timer = setInterval(() => router.reload({ only: ['terminais', 'chamadasComissao', 'chamadasCliente'] }), 15000);
});
onUnmounted(() => clearInterval(timer));

const tituloTipo = (tipo) => ({ bar: 'Bar', cafe: 'Café', restaurante: 'Restaurante', reservas: 'Reservas', cotas: 'Cotas' }[tipo] || tipo);

const atenderComissao = (chamada) => {
    fetch(route('comissao.atender', chamada.id), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
    }).then(() => router.reload({ only: ['chamadasComissao'] }));
};

const mostrarPin = ref(false);
const pinForm = useForm({ pin: '' });
const guardarPin = () => {
    pinForm.post(route('pos-painel.pin'), {
        preserveScroll: true,
        onSuccess: () => {
            pinForm.reset();
            mostrarPin.value = false;
        },
    });
};
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black">Painel POS — Comissão</h1>
                <p class="mt-1 text-sm text-slate-500">Estado dos terminais, chamadas pendentes e PIN da comissão.</p>
            </div>
            <button type="button" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white" @click="mostrarPin = !mostrarPin">
                Alterar PIN da comissão
            </button>
        </div>

        <section v-if="mostrarPin" class="mb-6 rounded-lg bg-white p-5 shadow-sm">
            <form class="flex max-w-md items-end gap-3" @submit.prevent="guardarPin">
                <div class="flex-1">
                    <label class="mb-1 block text-sm font-bold text-slate-600">Novo PIN (4 a 8 dígitos)</label>
                    <input v-model="pinForm.pin" type="password" inputmode="numeric" class="w-full rounded-md border-slate-300" placeholder="****">
                </div>
                <button class="rounded-md bg-emerald-700 px-4 py-2 font-bold text-white" :disabled="pinForm.processing">Guardar</button>
            </form>
            <div v-if="pinForm.errors.pin" class="mt-2 text-sm font-bold text-red-700">{{ pinForm.errors.pin }}</div>
        </section>

        <!-- Chamadas pendentes -->
        <div class="mb-6 grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-lg font-black">🔔 Chamadas à comissão</h2>
                <div v-if="!chamadasComissao.length" class="rounded-md bg-slate-50 p-5 text-center text-sm font-bold text-slate-500">Sem chamadas pendentes.</div>
                <div v-for="chamada in chamadasComissao" :key="chamada.id" class="flex items-center justify-between border-t border-slate-100 py-3">
                    <div>
                        <div class="font-black">{{ chamada.operador_nome }} — {{ chamada.local }}</div>
                        <div class="text-xs text-slate-500">{{ chamada.criado_em }}</div>
                    </div>
                    <button type="button" class="rounded-md bg-emerald-700 px-3 py-1.5 text-sm font-bold text-white" @click="atenderComissao(chamada)">Atender</button>
                </div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-lg font-black">🙋 Clientes a chamar funcionário</h2>
                <div v-if="!chamadasCliente.length" class="rounded-md bg-slate-50 p-5 text-center text-sm font-bold text-slate-500">Sem chamadas pendentes.</div>
                <div v-for="chamada in chamadasCliente" :key="chamada.id" class="flex items-center justify-between border-t border-slate-100 py-3">
                    <div>
                        <div class="font-black">{{ chamada.mesa }}</div>
                        <div class="text-xs text-slate-500">{{ chamada.pos ? 'POS: ' + chamada.pos + ' · ' : '' }}{{ chamada.ha_quanto }}</div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Terminais -->
        <section class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-black">Terminais POS</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="terminal in terminais" :key="terminal.id" class="rounded-lg border border-slate-200 p-4" :class="terminal.ativo ? '' : 'opacity-50'">
                    <div class="flex items-center justify-between">
                        <div class="font-black">{{ terminal.nome }}</div>
                        <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="terminal.ativo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'">
                            {{ terminal.ativo ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>
                    <div class="mt-1 text-sm text-slate-500">{{ tituloTipo(terminal.tipo) }} · {{ terminal.localizacao }}</div>
                    <div class="mt-2 text-xs text-slate-500">
                        <template v-if="terminal.ultimo_operador">
                            Último login: <strong>{{ terminal.ultimo_operador }}</strong> ({{ terminal.ultimo_login }})
                        </template>
                        <template v-else>Nunca usado</template>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
