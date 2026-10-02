<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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

const erroAtender = ref('');
const atenderComissao = async (chamada) => {
    erroAtender.value = '';
    try {
        const res = await fetch(route('comissao.atender', chamada.id), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
        });
        if (!res.ok) {
            const corpo = await res.json().catch(() => ({}));
            const msgs = corpo.errors ? Object.values(corpo.errors).flat() : [];
            erroAtender.value = msgs.join(' ') || `Não foi possível marcar como atendida (erro ${res.status}). Atualiza a página e tenta de novo.`;
        }
    } catch {
        erroAtender.value = 'Erro de ligação. Verifica a rede e tenta novamente.';
    }
    router.reload({ only: ['chamadasComissao'] });
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

// Apresentação (redesign)
const terminaisAtivos = computed(() => (props.terminais ?? []).filter((t) => t.ativo).length);
const terminaisInativos = computed(() => (props.terminais ?? []).length - terminaisAtivos.value);
const numeroMesa = (texto) => String(texto ?? '').replace(/\D/g, '') || '—';
</script>

<template>
    <AppLayout>
        <div class="font-sans text-tinta tabular-nums">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-extrabold">Painel POS — Comissão</h1>
                    <p class="mt-1 text-[15px] text-suave">Estado dos terminais, chamadas pendentes e PIN da comissão.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="flex items-center gap-2 text-sm text-suave"><span class="h-2 w-2 rounded-full bg-verde-ok" aria-hidden="true"></span>Atualiza sozinho a cada 15 s</span>
                    <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro px-4 text-[15px] font-bold text-white" :aria-expanded="mostrarPin" @click="mostrarPin = !mostrarPin">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
                        Alterar PIN da comissão
                    </button>
                </div>
            </div>

            <section v-if="mostrarPin" class="mb-5 rounded-[14px] border border-linha bg-white p-5">
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="guardarPin">
                    <label class="block min-w-[200px] flex-1 text-sm font-semibold text-suave sm:max-w-xs">Novo PIN (4 a 8 dígitos)
                        <input v-model="pinForm.pin" type="password" inputmode="numeric" class="mt-1 h-11 w-full rounded-[10px] text-tinta focus:border-verde focus:ring-verde" :class="pinForm.errors.pin ? 'border-perigo' : 'border-linha-forte'" placeholder="****">
                    </label>
                    <button class="h-11 rounded-[10px] bg-verde px-5 font-bold text-white hover:bg-verde-escuro disabled:opacity-45" :disabled="pinForm.processing">Guardar</button>
                    <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-5 font-bold" @click="mostrarPin = false">Cancelar</button>
                </form>
                <div v-if="pinForm.errors.pin" role="alert" class="mt-2 text-[13px] font-semibold text-perigo-texto">{{ pinForm.errors.pin }}</div>
            </section>

            <div v-if="erroAtender" role="alert" class="mb-5 rounded-[10px] bg-perigo-claro p-3 text-perigo-texto font-semibold">{{ erroAtender }}</div>

            <div class="mb-5 grid gap-5 xl:grid-cols-2">
                <!-- Chamadas à comissão -->
                <section aria-label="Chamadas à comissão" class="rounded-[14px] bg-white p-5" :class="chamadasComissao.length ? 'border-2 border-laranja' : 'border border-linha'">
                    <div class="flex items-center justify-between gap-3 border-b border-linha pb-3">
                        <h2 class="text-xl font-extrabold">Chamadas à comissão</h2>
                        <span v-if="chamadasComissao.length" class="rounded-full bg-laranja-claro px-3 py-1 text-sm font-bold text-laranja-texto">{{ chamadasComissao.length }} {{ chamadasComissao.length === 1 ? 'pendente' : 'pendentes' }}</span>
                    </div>
                    <p v-if="!chamadasComissao.length" class="py-6 text-center text-[15px] text-suave">Sem chamadas pendentes.</p>
                    <div v-for="chamada in chamadasComissao" :key="chamada.id" class="flex items-center justify-between gap-3 border-b border-linha-fraca py-3 last:border-b-0">
                        <div class="min-w-0">
                            <span class="block text-lg font-bold">{{ chamada.local }}</span>
                            <span class="block text-sm text-suave">{{ chamada.operador_nome }} · {{ chamada.criado_em }}</span>
                        </div>
                        <button type="button" class="h-14 shrink-0 rounded-[10px] bg-verde px-6 text-lg font-bold text-white hover:bg-verde-escuro" @click="atenderComissao(chamada)">Atender</button>
                    </div>
                </section>

                <!-- Clientes a chamar funcionário -->
                <section aria-label="Clientes a chamar funcionário" class="rounded-[14px] border border-linha bg-white p-5">
                    <div class="flex items-center justify-between gap-3 border-b border-linha pb-3">
                        <h2 class="text-xl font-extrabold">Clientes a chamar funcionário</h2>
                        <span v-if="chamadasCliente.length" class="rounded-full bg-verde-claro px-3 py-1 text-sm font-bold text-verde-escuro">{{ chamadasCliente.length }} {{ chamadasCliente.length === 1 ? 'mesa' : 'mesas' }}</span>
                    </div>
                    <p v-if="!chamadasCliente.length" class="py-6 text-center text-[15px] text-suave">Sem chamadas pendentes.</p>
                    <div v-for="chamada in chamadasCliente" :key="chamada.id" class="flex items-center gap-3 border-b border-linha-fraca py-3 last:border-b-0">
                        <span class="flex h-11 min-w-14 items-center justify-center rounded-[10px] bg-verde px-2 text-lg font-extrabold text-white">{{ numeroMesa(chamada.mesa) }}</span>
                        <div class="min-w-0">
                            <span class="block text-lg font-bold">{{ chamada.mesa }}</span>
                            <span class="block text-sm text-suave">{{ chamada.pos ? 'POS: ' + chamada.pos + ' · ' : '' }}{{ chamada.ha_quanto }}</span>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Terminais -->
            <section aria-label="Terminais POS" class="rounded-[14px] border border-linha bg-white p-5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-xl font-extrabold">Terminais POS</h2>
                    <span class="text-sm text-suave">{{ terminaisAtivos }} {{ terminaisAtivos === 1 ? 'ativo' : 'ativos' }} · {{ terminaisInativos }} {{ terminaisInativos === 1 ? 'inativo' : 'inativos' }}</span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    <div v-for="terminal in terminais" :key="terminal.id" class="rounded-[14px] border p-4" :class="terminal.ativo ? 'border-linha bg-white' : 'border-linha-fraca bg-fundo text-suave'">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-lg font-bold">{{ terminal.nome }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="terminal.ativo ? 'bg-verde-claro text-verde-escuro' : 'bg-linha text-suave'">{{ terminal.ativo ? 'Ativo' : 'Inativo' }}</span>
                        </div>
                        <span class="block text-sm text-suave">{{ tituloTipo(terminal.tipo) }} · {{ terminal.localizacao }}</span>
                        <span class="mt-0.5 block text-xs text-suave-2">
                            <template v-if="terminal.ultimo_operador">Último login: <strong class="font-semibold text-suave">{{ terminal.ultimo_operador }}</strong> ({{ terminal.ultimo_login }})</template>
                            <template v-else>Nunca usado</template>
                        </span>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
