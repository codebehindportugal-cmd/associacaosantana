<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ titulo: String, itemsPorMesa: Array, tem_urgentes: Boolean, modoBar: Boolean });
const aAtualizar = ref(false);
const ultimaAtualizacao = ref(new Date());
const novosItems = ref(new Set());
const idsConhecidos = ref(new Set());
let intervalo = null;
let limparDestaque = null;

const totalItems = computed(() => (props.itemsPorMesa ?? []).reduce((total, grupo) => total + grupo.items.length, 0));
const idsAtuais = () => new Set((props.itemsPorMesa ?? []).flatMap((grupo) => grupo.items.map((item) => item.id)));
const atualizar = () => {
    aAtualizar.value = true;
    router.reload({ only: ['itemsPorMesa', 'tem_urgentes'], preserveScroll: true, onFinish: () => { aAtualizar.value = false; ultimaAtualizacao.value = new Date(); } });
};
watch(() => props.itemsPorMesa, () => {
    const atuais = idsAtuais();
    const novos = [...atuais].filter((id) => !idsConhecidos.value.has(id));
    if (idsConhecidos.value.size && novos.length) {
        novosItems.value = new Set(novos);
        clearTimeout(limparDestaque);
        limparDestaque = setTimeout(() => { novosItems.value = new Set(); }, 12000);
    }
    idsConhecidos.value = atuais;
}, { deep: true, immediate: true });
onMounted(() => { intervalo = setInterval(atualizar, 5000); });
onBeforeUnmount(() => { clearInterval(intervalo); clearTimeout(limparDestaque); });
const pronto = (id) => router.patch(route('secao.items.pronto', id), {}, { preserveScroll: true });
const hora = computed(() => ultimaAtualizacao.value.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
</script>

<template>
    <main class="min-h-screen bg-[#1a1a2e] p-5 text-white sm:p-8">
        <div v-if="tem_urgentes" class="mb-5 animate-pulse rounded-2xl bg-amber-600 p-5 text-center text-2xl font-black text-white shadow-lg">ATENÇÃO — HÁ MESAS A TERMINAR</div>
        <header class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div><h1 class="text-5xl font-black tracking-normal">{{ titulo }}</h1><p class="mt-2 text-sm text-white/70">{{ totalItems }} pedidos pendentes · atualização automática</p></div>
            <div class="rounded-lg bg-white/10 px-4 py-3 text-right"><div class="text-xs font-semibold uppercase text-white/60">{{ aAtualizar ? 'A procurar novos pedidos' : 'Último refresh' }}</div><div class="text-2xl font-bold">{{ hora }}</div></div>
        </header>
        <div v-if="!itemsPorMesa?.length" class="rounded-lg border border-white/10 bg-white/10 p-10 text-center text-2xl font-bold text-white/80">Sem pedidos pendentes</div>
        <div v-else class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            <section v-for="grupo in itemsPorMesa" :key="grupo.mesa" class="relative rounded-lg p-6" :class="grupo.urgente ? 'border-4 border-amber-500 bg-amber-950/70' : 'bg-white/10'">
                <span v-if="grupo.urgente" class="absolute right-4 top-4 rounded-full bg-amber-600 px-3 py-1 text-sm font-black">A TERMINAR</span>
                <h2 class="mb-4 text-3xl font-bold">{{ grupo.mesa }}</h2>
                <div v-for="item in grupo.items" :key="item.id" class="mb-3 flex items-center justify-between gap-4 rounded-md p-4 text-slate-900 transition-all duration-500" :class="item.prioridade ? 'bg-amber-900 text-white ring-4 ring-amber-500' : (item.observacoes ? 'bg-amber-100 ring-4 ring-amber-400' : (novosItems.has(item.id) ? 'bg-amber-200 ring-4 ring-amber-400' : 'bg-white'))">
                    <div>
                        <span class="block text-[1.2rem] font-bold">{{ item.quantidade }}x {{ item.produto?.nome }}</span>
                        <span v-if="item.prioridade" class="mt-1 block text-sm font-black text-amber-200">A TERMINAR</span>
                        <span v-else-if="novosItems.has(item.id)" class="mt-1 block text-sm font-bold text-amber-800">Novo pedido</span>
                        <span v-if="item.observacoes" class="mt-3 block rounded-md bg-red-600 px-3 py-2 text-lg font-black text-white">
                            ATENÇÃO: {{ item.observacoes }}
                        </span>
                    </div>
                    <button v-if="!modoBar" class="min-h-[60px] rounded-md px-5 py-3 font-black text-white" :class="item.prioridade ? 'bg-amber-500 text-lg' : 'bg-emerald-600'" @click="pronto(item.id)">PRONTO</button>
                </div>
            </section>
        </div>
        <div class="fixed bottom-3 right-4 text-xs font-bold text-white/50">Último refresh: {{ hora }}</div>
    </main>
</template>
