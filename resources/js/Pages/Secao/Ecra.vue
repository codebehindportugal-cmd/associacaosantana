<script setup>
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({ titulo: String, secao: String, mostrarSecao: Boolean, itemsPorMesa: Array, tem_urgentes: Boolean, modoBar: Boolean, agora: String });
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
// "12 min" até 1 h; depois "1h05"; mais de 1 dia = "+1 dia"
const textoEspera = (minutos) => {
    if (minutos < 60) return `${minutos} min`;
    if (minutos >= 1440) return `+${Math.floor(minutos / 1440)} dia${minutos >= 2880 ? 's' : ''}`;
    return `${Math.floor(minutos / 60)}h${String(minutos % 60).padStart(2, '0')}`;
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
    'CARVALHAL FEST': { fundo: 'bg-secao-cozinha', borda: 'border-secao-cozinha' },
    ACOMPANHAMENTOS: { fundo: 'bg-secao-acompanhamentos', borda: 'border-secao-acompanhamentos' },
}[props.titulo] ?? { fundo: 'bg-secao-servico', borda: 'border-secao-servico' }));

// No ecrã das tasquinhas cada artigo mostra de que secção é
const etiquetaSecao = {
    comida: { nome: 'Comida', cor: 'bg-secao-cozinha' },
    frango: { nome: 'Frango', cor: 'bg-secao-grelhados' },
    acompanhamentos: { nome: 'Acompanhamento', cor: 'bg-secao-acompanhamentos' },
    sobremesas: { nome: 'Sobremesa', cor: 'bg-secao-sobremesas' },
};
const textoContagem = computed(() => (totalItems.value === 1 ? '1 pedido por preparar' : `${totalItems.value} pedidos por preparar`));

// Botão "Pronto" por artigo (rota secao.items.pronto: marca o artigo como pronto;
// se for "Limpar mesa", fecha o pedido e liberta a mesa)
const aMarcar = ref(new Set());
const erroPronto = ref('');
const marcarPronto = (item) => {
    if (aMarcar.value.has(item.id)) return;
    aMarcar.value = new Set([...aMarcar.value, item.id]);
    erroPronto.value = '';
    router.patch(route('secao.items.pronto', item.id), {}, {
        preserveScroll: true,
        onError: (erros) => { erroPronto.value = Object.values(erros).join(' ') || 'Não foi possível marcar como pronto.'; },
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
onBeforeUnmount(() => { clearInterval(intervalo); clearInterval(relogio); clearTimeout(limparDestaque); clearTimeout(cancelarConfirmacao); });
// Limpar ecrã: 1.º toque pede confirmação, 2.º toque (em 5 s) limpa.
// Não apaga nada: marca os pendentes como prontos / as senhas como retiradas.
const confirmarLimpar = ref(false);
const aLimpar = ref(false);
let cancelarConfirmacao = null;
const limparEcra = () => {
    if (!props.secao || aLimpar.value) return;
    if (!confirmarLimpar.value) {
        confirmarLimpar.value = true;
        clearTimeout(cancelarConfirmacao);
        cancelarConfirmacao = setTimeout(() => { confirmarLimpar.value = false; }, 5000);
        return;
    }
    clearTimeout(cancelarConfirmacao);
    confirmarLimpar.value = false;
    aLimpar.value = true;
    router.patch(route('secao.limpar', props.secao), {}, { preserveScroll: true, onFinish: () => { aLimpar.value = false; } });
};
const retirar = (id) => router.patch(route('secao.pedidos.retirar', id), {}, { preserveScroll: true });
const hora = computed(() => ultimaAtualizacao.value.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
</script>

<template>
    <main class="flex min-h-screen flex-col bg-escuro font-sans tabular-nums text-white">
        <div v-if="tem_urgentes" role="alert" class="flex min-h-[44px] shrink-0 items-center justify-center gap-3 bg-laranja px-4 text-center text-xl font-extrabold tracking-[.04em]">
            <svg class="h-7 w-7 shrink-0 animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4M12 17h.01" /></svg>
            HÁ MESAS A TERMINAR
        </div>

        <header class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b-[6px] px-4 py-2.5" :class="corSecao.borda">
            <div class="flex flex-wrap items-center gap-4">
                <span class="h-6 w-6 shrink-0 rounded-full" :class="corSecao.fundo" aria-hidden="true"></span>
                <h1 class="text-3xl font-extrabold tracking-[.02em]">{{ titulo }}</h1>
                <span class="flex h-9 items-center rounded-full bg-escuro-2 px-4 text-xl font-extrabold">{{ textoContagem }}</span>
            </div>
            <div class="flex items-center gap-4">
                <button
                    v-if="secao && itemsPorMesa?.length"
                    type="button"
                    class="flex h-11 items-center gap-2 rounded-xl border-2 px-4 text-lg font-extrabold disabled:opacity-50"
                    :class="confirmarLimpar ? 'border-perigo bg-perigo text-white' : 'border-escuro-inativo text-escuro-inativo hover:text-white'"
                    :disabled="aLimpar"
                    @click="limparEcra"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14" /></svg>
                    {{ confirmarLimpar ? 'Toca outra vez para limpar tudo' : 'Limpar ecrã' }}
                </button>
                <span class="text-2xl font-extrabold" :class="aAtualizar ? 'text-escuro-inativo' : ''" :title="aAtualizar ? 'A procurar novos pedidos' : 'Último refresh'">{{ hora }}</span>
            </div>
        </header>
        <div v-if="erroPronto" role="alert" class="mx-4 mt-3 rounded-[10px] bg-perigo-claro p-3 text-xl font-semibold text-perigo-texto">{{ erroPronto }}</div>

        <section v-if="!itemsPorMesa?.length" class="flex flex-1 flex-col items-center justify-center gap-4 text-[#8FA39A]">
            <svg class="h-20 w-20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
            <p class="text-3xl font-extrabold">Sem pedidos pendentes</p>
        </section>

        <!-- Colunas encaixadas: cada talão ocupa só a altura que precisa -->
        <section v-else class="flex-1 columns-1 gap-3 px-3 pb-6 pt-3 sm:columns-2 lg:columns-3 xl:columns-4 2xl:columns-5">
            <article
                v-for="grupo in itemsPorMesa"
                :key="grupo.pedido_id ?? grupo.mesa"
                class="mb-3 break-inside-avoid overflow-hidden rounded-xl bg-white text-tinta"
                :class="grupo.urgente ? 'ring-4 ring-laranja' : ''"
            >
                <div class="flex items-center gap-2 bg-escuro-2 px-3 py-1.5 text-white">
                    <h2 class="min-w-0 flex-1 truncate text-2xl font-extrabold leading-tight">{{ grupo.mesa }}</h2>
                    <span v-if="grupo.urgente" class="shrink-0 rounded-full bg-laranja px-2 py-0.5 text-sm font-extrabold">A TERMINAR</span>
                    <span
                        v-if="minutosEspera(grupo) !== null"
                        class="flex h-8 shrink-0 items-center gap-1 rounded-lg px-2 text-lg font-extrabold"
                        :class="corEspera(minutosEspera(grupo))"
                    >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg>
                        {{ textoEspera(minutosEspera(grupo)) }}
                    </span>
                </div>

                <ul class="divide-y divide-linha-fraca">
                    <li
                        v-for="item in grupo.items"
                        :key="item.id"
                        class="px-3 py-1.5 transition-colors duration-500"
                        :class="item.estado === 'pronto' ? 'opacity-50' : (item.prioridade || item.observacoes ? 'bg-laranja-claro' : (novosItems.has(item.id) ? 'bg-laranja-claro/60' : ''))"
                    >
                        <div class="flex items-center gap-2">
                            <span class="min-w-0 flex-1 text-xl font-extrabold leading-snug" :class="item.estado === 'pronto' ? 'line-through' : ''">
                                <span class="text-secao-grelhados">{{ item.quantidade }}x</span> {{ item.produto?.nome }}
                                <span v-if="mostrarSecao && etiquetaSecao[item.secao]" class="ml-1 inline-block rounded-full px-2 align-middle text-xs font-extrabold uppercase text-white" :class="etiquetaSecao[item.secao].cor">{{ etiquetaSecao[item.secao].nome }}</span>
                                <span v-if="item.prioridade && item.estado !== 'pronto'" class="ml-1 inline-block rounded-full bg-laranja px-2 align-middle text-xs font-extrabold text-white">A TERMINAR</span>
                                <span v-if="novosItems.has(item.id) && !item.prioridade" class="ml-1 inline-block rounded-full border border-laranja px-2 align-middle text-xs font-extrabold text-laranja-texto">NOVO</span>
                            </span>
                            <button
                                v-if="item.estado !== 'pronto'"
                                type="button"
                                class="flex h-11 shrink-0 items-center gap-1 rounded-lg bg-verde px-3 text-base font-extrabold text-white hover:bg-verde-escuro disabled:opacity-50"
                                :disabled="aMarcar.has(item.id)"
                                :aria-label="`Pronto: ${item.quantidade}x ${item.produto?.nome ?? ''}`"
                                @click="marcarPronto(item)"
                            >
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                                Pronto
                            </button>
                            <svg v-else class="shrink-0 text-verde" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-label="Pronto"><path d="M20 6 9 17l-5-5" /></svg>
                        </div>
                        <p v-if="item.observacoes" class="mt-1 rounded-md bg-perigo px-2 py-0.5 text-base font-extrabold text-white">⚠ {{ item.observacoes }}</p>
                    </li>
                </ul>

                <button
                    v-if="modoBar && grupo.pedido_id"
                    type="button"
                    class="flex h-11 w-full items-center justify-center gap-2 bg-verde text-lg font-extrabold text-white hover:bg-verde-escuro"
                    @click="retirar(grupo.pedido_id)"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                    RETIRAR PEDIDO
                </button>
            </article>
        </section>
    </main>
</template>
