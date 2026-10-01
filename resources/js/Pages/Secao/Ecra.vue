<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ titulo: String, itemsPorMesa: Array, tem_urgentes: Boolean, modoBar: Boolean, agora: String });
const aAtualizar = ref(false);
const ultimaAtualizacao = ref(new Date());
const novosItems = ref(new Set());
const idsConhecidos = ref(new Set());
let intervalo = null;
let limparDestaque = null;
let relogio = null;

// Tempo de espera: calculado a partir de `desde` (ISO) e atualizado a cada 30 s.
// Corrige a diferença entre o relógio da TV e o do servidor com `agora`.
const agoraLocal = ref(Date.now());
const desvioRelogio = ref(0);
watch(() => props.agora, (valor) => {
    const servidor = valor ? Date.parse(valor) : NaN;
    desvioRelogio.value = Number.isNaN(servidor) ? 0 : servidor - Date.now();
    agoraLocal.value = Date.now();
}, { immediate: true });
const minutosEspera = (grupo) => {
    const inicio = grupo?.desde ? Date.parse(grupo.desde) : NaN;
    if (Number.isNaN(inicio)) return null;
    return Math.max(0, Math.floor((agoraLocal.value + desvioRelogio.value - inicio) / 60000));
};
const corEspera = (minutos) => {
    if (minutos >= 15) return 'bg-perigo';
    if (minutos >= 10) return 'bg-laranja';
    return 'bg-escuro-2';
};

// Cor da secção (barra do cabeçalho), sempre com o nome escrito ao lado
const corSecao = computed(() => ({
    FRANGO: { fundo: 'bg-secao-grelhados', borda: 'border-secao-grelhados' },
    COMIDA: { fundo: 'bg-secao-cozinha', borda: 'border-secao-cozinha' },
    BEBIDAS: { fundo: 'bg-secao-bar', borda: 'border-secao-bar' },
    BAR: { fundo: 'bg-secao-bar', borda: 'border-secao-bar' },
    SOBREMESAS: { fundo: 'bg-secao-sobremesas', borda: 'border-secao-sobremesas' },
    ACOMPANHAMENTOS: { fundo: 'bg-secao-acompanhamentos', borda: 'border-secao-acompanhamentos' },
}[props.titulo] ?? { fundo: 'bg-secao-servico', borda: 'border-secao-servico' }));

const textoContagem = computed(() => (totalItems.value === 1 ? '1 pedido por preparar' : `${totalItems.value} pedidos por preparar`));

// Botão "Pronto" por artigo (rota secao.items.pronto: marca o artigo como pronto;
// se for "Limpar mesa", fecha o pedido e liberta a mesa)
const aMarcar = ref(new Set());
const marcarPronto = (item) => {
    if (aMarcar.value.has(item.id)) return;
    aMarcar.value = new Set([...aMarcar.value, item.id]);
    router.patch(route('secao.items.pronto', item.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            const restantes = new Set(aMarcar.value);
            restantes.delete(item.id);
            aMarcar.value = restantes;
        },
    });
};

const totalItems = computed(() => (props.itemsPorMesa ?? []).reduce((total, grupo) => total + grupo.items.length, 0));
const idsAtuais = () => new Set((props.itemsPorMesa ?? []).flatMap((grupo) => grupo.items.map((item) => item.id)));
const atualizar = () => {
    aAtualizar.value = true;
    router.reload({ only: ['itemsPorMesa', 'tem_urgentes', 'agora'], preserveScroll: true, onFinish: () => { aAtualizar.value = false; ultimaAtualizacao.value = new Date(); } });
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
onMounted(() => {
    intervalo = setInterval(atualizar, 5000);
    relogio = setInterval(() => { agoraLocal.value = Date.now(); }, 30000);
});
onBeforeUnmount(() => { clearInterval(intervalo); clearInterval(relogio); clearTimeout(limparDestaque); });
const retirar = (id) => router.patch(route('secao.pedidos.retirar', id), {}, { preserveScroll: true });
const hora = computed(() => ultimaAtualizacao.value.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
</script>

<template>
    <main class="flex min-h-screen flex-col bg-escuro font-sans tabular-nums text-white">
        <div v-if="tem_urgentes" role="alert" class="flex min-h-[84px] shrink-0 items-center justify-center gap-5 bg-laranja px-6 text-center text-3xl font-extrabold tracking-[.04em] xl:text-[44px]">
            <svg class="h-10 w-10 shrink-0 animate-pulse xl:h-12 xl:w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4M12 17h.01" /></svg>
            ATENÇÃO — HÁ MESAS A TERMINAR
        </div>

        <header class="flex shrink-0 flex-wrap items-center justify-between gap-4 border-b-[10px] px-6 py-5 xl:min-h-[132px] xl:px-12 xl:py-0" :class="corSecao.borda">
            <div class="flex flex-wrap items-center gap-5 xl:gap-7">
                <span class="h-8 w-8 shrink-0 rounded-full xl:h-10 xl:w-10" :class="corSecao.fundo" aria-hidden="true"></span>
                <h1 class="text-6xl font-extrabold tracking-[.02em] xl:text-[84px] xl:leading-none">{{ titulo }}</h1>
                <span class="flex h-14 items-center rounded-full bg-escuro-2 px-6 text-3xl font-extrabold xl:h-[72px] xl:px-7 xl:text-[40px]">{{ textoContagem }}</span>
            </div>
            <div class="flex flex-col items-end gap-0.5">
                <span class="text-base font-bold uppercase tracking-[.08em] text-escuro-inativo xl:text-[22px]">{{ aAtualizar ? 'A procurar novos pedidos' : 'Atualização automática · último refresh' }}</span>
                <span class="text-4xl font-extrabold xl:text-5xl">{{ hora }}</span>
            </div>
        </header>

        <section v-if="!itemsPorMesa?.length" class="flex flex-1 flex-col items-center justify-center gap-6 text-[#8FA39A]">
            <svg class="h-28 w-28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
            <p class="text-5xl font-extrabold xl:text-7xl">Sem pedidos pendentes</p>
        </section>

        <section v-else class="grid flex-1 content-start items-start gap-7 px-6 pb-10 pt-7 md:grid-cols-2 xl:grid-cols-3 xl:px-12">
            <article
                v-for="grupo in itemsPorMesa"
                :key="grupo.pedido_id ?? grupo.mesa"
                class="flex flex-col gap-4 rounded-[22px] bg-[#233029] p-6"
                :class="grupo.urgente ? 'border-[6px] border-laranja' : 'border-2 border-[#3A4842]'"
            >
                <div class="flex flex-col gap-2.5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="min-w-0 truncate text-5xl font-extrabold leading-none xl:text-6xl">{{ grupo.mesa }}</h2>
                        <span v-if="grupo.urgente" class="shrink-0 rounded-full bg-laranja px-3.5 py-1.5 text-2xl font-extrabold tracking-[.04em]">A TERMINAR</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="min-w-0 truncate text-[26px] font-semibold text-escuro-inativo">Operador: {{ grupo.operador ?? 'Sem operador' }}</span>
                        <span
                            v-if="minutosEspera(grupo) !== null"
                            class="flex h-[60px] shrink-0 items-center gap-2.5 rounded-[14px] px-[18px] text-[40px] font-extrabold text-white"
                            :class="corEspera(minutosEspera(grupo))"
                        >
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg>
                            {{ minutosEspera(grupo) }} min
                        </span>
                    </div>
                </div>

                <div
                    v-for="item in grupo.items"
                    :key="item.id"
                    class="flex flex-col gap-3 rounded-2xl border-4 px-[18px] py-4 text-tinta transition-all duration-500"
                    :class="[
                        item.estado === 'pronto' ? 'border-transparent bg-white/60 opacity-60' : (item.prioridade || item.observacoes ? 'border-laranja bg-laranja-claro' : (novosItems.has(item.id) ? 'border-laranja/60 bg-white' : 'border-transparent bg-white')),
                    ]"
                >
                    <div class="flex items-center justify-between gap-4">
                        <span class="min-w-0 text-[42px] font-extrabold leading-tight" :class="item.estado === 'pronto' ? 'line-through' : ''">
                            <span class="whitespace-nowrap text-secao-grelhados">{{ item.quantidade }}x&nbsp;</span>{{ item.produto?.nome }}
                        </span>
                        <button
                            v-if="item.estado !== 'pronto'"
                            type="button"
                            class="flex h-[88px] shrink-0 items-center justify-center gap-2 rounded-2xl bg-verde px-[18px] text-[32px] font-extrabold text-white hover:bg-verde-escuro disabled:opacity-50"
                            :disabled="aMarcar.has(item.id)"
                            @click="marcarPronto(item)"
                        >
                            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                            Pronto
                        </button>
                        <span v-else class="flex shrink-0 items-center gap-2 text-[28px] font-extrabold text-verde">
                            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                            Pronto
                        </span>
                    </div>
                    <span v-if="item.prioridade && item.estado !== 'pronto'" class="self-start rounded-full bg-laranja px-3.5 py-1 text-[26px] font-extrabold text-white">A TERMINAR</span>
                    <span v-if="item.observacoes" class="rounded-xl bg-perigo px-4 py-2.5 text-[34px] font-extrabold text-white">ATENÇÃO: {{ item.observacoes }}</span>
                    <span v-if="novosItems.has(item.id) && !item.prioridade" class="self-start rounded-full border-2 border-laranja bg-laranja-claro px-3.5 py-1 text-[26px] font-extrabold text-laranja-texto">Novo pedido</span>
                </div>

                <button
                    v-if="modoBar && grupo.pedido_id"
                    type="button"
                    class="flex h-[88px] w-full items-center justify-center gap-3 rounded-2xl bg-verde px-5 text-[34px] font-extrabold text-white hover:bg-verde-escuro"
                    @click="retirar(grupo.pedido_id)"
                >
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                    RETIRAR PEDIDO
                </button>
            </article>
        </section>
    </main>
</template>
