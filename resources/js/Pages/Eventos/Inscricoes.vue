<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({
    evento: Object,
    inscricoes: Array,
    totais: Object,
    urlPublica: String,
});

const qrDataUrl = ref('');

onMounted(async () => {
    try {
        qrDataUrl.value = await QRCode.toDataURL(props.urlPublica, { width: 400, margin: 2 });
    } catch { /* sem qr */ }
});

const apagar = (inscricao) => {
    if (confirm(`Apagar a inscrição de "${inscricao.nome}"?`)) {
        router.delete(route('eventos.inscricoes.destroy', inscricao.id), { preserveScroll: true });
    }
};

// Check-in de pulseiras
const filtro = ref('todas'); // todas | por_confirmar | confirmadas
const pesquisa = ref('');

const inscricoesFiltradas = computed(() => (props.inscricoes ?? []).filter((i) => {
    if (filtro.value === 'por_confirmar' && i.confirmada) return false;
    if (filtro.value === 'confirmadas' && !i.confirmada) return false;
    const termo = pesquisa.value.trim().toLowerCase();
    if (termo && !`${i.nome} ${i.telefone}`.toLowerCase().includes(termo)) return false;
    return true;
}));

const alternarConfirmacao = (inscricao) => {
    router.post(route('eventos.inscricoes.confirmar', inscricao.id), {}, { preserveScroll: true });
};

const euros = (v) => Number(v).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });
const filtros = computed(() => [
    { valor: 'todas', label: `Todas (${props.totais.inscricoes})` },
    { valor: 'por_confirmar', label: `Por confirmar (${props.totais.inscricoes - props.totais.confirmadas})` },
    { valor: 'confirmadas', label: `Confirmadas (${props.totais.confirmadas})` },
]);
const pagamentoPill = {
    pago: { texto: 'Pago', cls: 'bg-verde-claro text-verde-escuro' },
    pendente: { texto: 'Pag. pendente', cls: 'bg-laranja-claro text-laranja-texto' },
    falhado: { texto: 'Pag. falhou', cls: 'bg-perigo-claro text-perigo-texto' },
};
const percentagemLimite = computed(() => props.evento.inscricoes_limite
    ? Math.min(100, Math.round((props.totais.pessoas / props.evento.inscricoes_limite) * 100))
    : null);

const exportarCsv = () => {
    const linhas = [
        ['Nome', 'Telefone', 'Email', 'Pessoas', 'Opção', 'Crianças', 'Idades', 'Valor', 'Pagamento', 'Observações', 'Data'],
        ...props.inscricoes.map((i) => [i.nome, i.telefone, i.email ?? '', i.num_pessoas, i.opcao ?? '', i.num_criancas ?? '', i.idades_criancas ?? '', i.valor_estimado ?? '', i.pagamento_estado ?? 'no dia', i.observacoes ?? '', i.criado_em]),
    ];
    const csv = linhas.map((l) => l.map((c) => `"${String(c).replaceAll('"', '""')}"`).join(';')).join('\n');
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `inscricoes-${props.evento.titulo.toLowerCase().replaceAll(' ', '-')}.csv`;
    a.click();
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex min-w-0 flex-col gap-1">
                    <Link :href="route('eventos.index')" class="inline-flex items-center gap-1 text-sm font-bold text-verde hover:text-verde-escuro">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                        Eventos
                    </Link>
                    <h1 class="text-[26px] font-extrabold leading-tight sm:text-[30px]">Inscrições — {{ evento.titulo }}</h1>
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-suave">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[13px] font-extrabold" :class="evento.inscricoes_ativas ? 'bg-verde-claro text-verde-escuro' : 'bg-perigo-claro text-perigo-texto'">
                            <span class="h-2 w-2 rounded-full" :class="evento.inscricoes_ativas ? 'bg-verde-ok' : 'bg-perigo'"></span>
                            {{ evento.inscricoes_ativas ? 'Inscrições abertas' : 'Inscrições fechadas' }}
                        </span>
                        <span v-if="evento.inscricoes_limite">limite {{ evento.inscricoes_limite }} pessoas</span>
                        <span>— ativa/desativa na <Link :href="route('eventos.edit', evento.id)" class="font-bold text-verde underline">edição do evento</Link></span>
                    </p>
                </div>
                <button type="button" class="btn-sec h-12" @click="exportarCsv">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5M4 20h16" /></svg>
                    Exportar CSV
                </button>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="cartao">
                    <div class="text-sm font-semibold text-suave">Inscrições</div>
                    <div class="mt-1 text-[32px] font-extrabold leading-none">{{ totais.inscricoes }}</div>
                </div>
                <div class="cartao">
                    <div class="text-sm font-semibold text-suave">Total de pessoas</div>
                    <div class="mt-1 text-[32px] font-extrabold leading-none text-verde">{{ totais.pessoas }}<span v-if="evento.inscricoes_limite" class="text-base font-semibold text-suave"> / {{ evento.inscricoes_limite }}</span></div>
                    <div v-if="percentagemLimite !== null" class="mt-2 h-2 overflow-hidden rounded-full bg-linha-fraca"><div class="h-full rounded-full bg-verde" :style="{ width: `${percentagemLimite}%` }"></div></div>
                    <div v-if="totais.valor" class="mt-2 text-sm font-semibold text-suave">≈ {{ euros(totais.valor) }} estimados</div>
                </div>
                <div class="cartao">
                    <div class="text-sm font-semibold text-suave">Pulseiras entregues</div>
                    <div class="mt-1 text-[32px] font-extrabold leading-none">{{ totais.pessoas_confirmadas }}<span class="text-base font-semibold text-suave"> / {{ totais.pessoas }} pessoas</span></div>
                    <div class="mt-2 text-sm font-semibold text-suave">{{ totais.confirmadas }} inscrições confirmadas</div>
                </div>
                <div class="cartao flex items-center gap-3">
                    <img v-if="qrDataUrl" :src="qrDataUrl" class="h-24 w-24 shrink-0 rounded-[10px] border border-linha" alt="QR inscrições">
                    <div class="min-w-0 text-xs text-suave">
                        <div class="text-[13px] font-bold text-suave">QR para os cartazes (fixo, nunca muda)</div>
                        <div class="mt-1 break-all font-bold text-tinta">{{ urlPublica }}</div>
                        <a v-if="qrDataUrl" :href="qrDataUrl" download="qr-inscricoes-santana.png" class="mt-2 inline-flex h-11 items-center rounded-[10px] bg-tinta px-3 text-sm font-bold text-white hover:bg-escuro-2">Descarregar PNG</a>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="flex flex-wrap items-center gap-2 border-b border-linha-fraca p-4">
                    <button
                        v-for="opcao in filtros"
                        :key="opcao.valor"
                        type="button"
                        class="inline-flex h-11 items-center rounded-full px-4 text-sm font-bold"
                        :class="filtro === opcao.valor ? 'bg-tinta text-white' : 'border border-linha-forte bg-white hover:bg-fundo'"
                        :aria-pressed="filtro === opcao.valor"
                        @click="filtro = opcao.valor"
                    >
                        {{ opcao.label }}
                    </button>
                    <label class="relative w-full sm:ml-auto sm:w-72">
                        <span class="sr-only">Pesquisar por nome ou telefone</span>
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-suave-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
                        <input v-model="pesquisa" class="h-11 w-full rounded-[10px] border border-linha-forte pl-10 text-[15px] focus:border-verde focus:ring-verde" placeholder="Nome ou telefone">
                    </label>
                </div>

                <div v-if="!inscricoesFiltradas.length" class="m-4 rounded-[10px] bg-fundo p-6 text-center text-sm font-bold text-suave-2">
                    {{ inscricoes.length ? 'Nada encontrado com este filtro.' : 'Ainda não há inscrições.' }}
                </div>
                <template v-else>
                    <!-- Ecrãs largos: tabela -->
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="w-full text-left text-sm">
                            <thead class="text-[13px] font-semibold text-suave">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Nome</th>
                                    <th class="px-2 font-semibold">Telefone</th>
                                    <th class="px-2 text-center font-semibold">Pessoas</th>
                                    <th v-if="evento.tem_opcoes" class="px-2 font-semibold">Opção</th>
                                    <th v-if="evento.pede_idades" class="px-2 font-semibold">Crianças</th>
                                    <th class="px-2 text-right font-semibold">Valor</th>
                                    <th class="px-2 font-semibold">Data</th>
                                    <th class="px-4 text-right font-semibold">Pulseira</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="inscricao in inscricoesFiltradas" :key="inscricao.id" class="border-t border-linha-fraca" :class="inscricao.confirmada ? 'bg-verde-claro/40' : ''">
                                    <td class="px-4 py-3 align-top">
                                        <strong class="font-bold">{{ inscricao.nome }}</strong>
                                        <span v-if="pagamentoPill[inscricao.pagamento_estado]" class="ml-1.5 rounded-full px-2 py-0.5 text-[11px] font-extrabold uppercase" :class="pagamentoPill[inscricao.pagamento_estado].cls">{{ pagamentoPill[inscricao.pagamento_estado].texto }}</span>
                                        <div v-if="inscricao.email" class="text-xs text-suave-2">{{ inscricao.email }}</div>
                                        <div v-if="inscricao.observacoes" class="text-xs text-suave-2">{{ inscricao.observacoes }}</div>
                                    </td>
                                    <td class="px-2 py-3 align-top"><a :href="`tel:${inscricao.telefone}`" class="font-bold text-verde">{{ inscricao.telefone }}</a></td>
                                    <td class="px-2 py-3 text-center align-top text-base font-extrabold">{{ inscricao.num_pessoas }}</td>
                                    <td v-if="evento.tem_opcoes" class="px-2 py-3 align-top">{{ inscricao.opcao ?? '—' }}</td>
                                    <td v-if="evento.pede_idades" class="px-2 py-3 align-top">
                                        <template v-if="inscricao.num_criancas">{{ inscricao.num_criancas }}<span v-if="inscricao.idades_criancas" class="text-suave-2"> ({{ inscricao.idades_criancas }})</span></template>
                                        <template v-else>—</template>
                                    </td>
                                    <td class="px-2 py-3 text-right align-top font-bold">{{ inscricao.valor_estimado !== null ? euros(inscricao.valor_estimado) : '—' }}</td>
                                    <td class="px-2 py-3 align-top text-xs text-suave-2">{{ inscricao.criado_em }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right">
                                        <button type="button" class="btn-confirmar" :class="inscricao.confirmada ? 'bg-verde text-white' : 'border-2 border-verde text-verde hover:bg-verde-claro'" :title="inscricao.confirmada ? 'Confirmada ' + inscricao.confirmada_em : 'Marcar como confirmada (pulseira entregue)'" @click="alternarConfirmacao(inscricao)">
                                            {{ inscricao.confirmada ? 'Confirmada' : 'Confirmar' }}
                                        </button>
                                        <button type="button" class="btn-apagar ml-2" :aria-label="`Apagar a inscrição de ${inscricao.nome}`" title="Apagar" @click="apagar(inscricao)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Ecrãs estreitos: cartões -->
                    <ul class="divide-y divide-linha-fraca lg:hidden">
                        <li v-for="inscricao in inscricoesFiltradas" :key="inscricao.id" class="flex flex-col gap-2 p-4" :class="inscricao.confirmada ? 'bg-verde-claro/40' : ''">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <strong class="font-bold">{{ inscricao.nome }}</strong>
                                    <span v-if="pagamentoPill[inscricao.pagamento_estado]" class="ml-1.5 rounded-full px-2 py-0.5 text-[11px] font-extrabold uppercase" :class="pagamentoPill[inscricao.pagamento_estado].cls">{{ pagamentoPill[inscricao.pagamento_estado].texto }}</span>
                                    <div v-if="inscricao.email" class="truncate text-xs text-suave-2">{{ inscricao.email }}</div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="text-lg font-extrabold leading-none">{{ inscricao.num_pessoas }}</div>
                                    <div class="text-[11px] text-suave-2">pessoas</div>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                <a :href="`tel:${inscricao.telefone}`" class="font-bold text-verde">{{ inscricao.telefone }}</a>
                                <span v-if="evento.tem_opcoes && inscricao.opcao">{{ inscricao.opcao }}</span>
                                <span v-if="evento.pede_idades && inscricao.num_criancas">{{ inscricao.num_criancas }} crianças<span v-if="inscricao.idades_criancas" class="text-suave-2"> ({{ inscricao.idades_criancas }})</span></span>
                                <span v-if="inscricao.valor_estimado !== null" class="font-bold">{{ euros(inscricao.valor_estimado) }}</span>
                                <span class="text-xs leading-5 text-suave-2">{{ inscricao.criado_em }}</span>
                            </div>
                            <div v-if="inscricao.observacoes" class="text-xs text-suave-2">{{ inscricao.observacoes }}</div>
                            <div class="flex gap-2">
                                <button type="button" class="btn-confirmar flex-1" :class="inscricao.confirmada ? 'bg-verde text-white' : 'border-2 border-verde text-verde hover:bg-verde-claro'" :title="inscricao.confirmada ? 'Confirmada ' + inscricao.confirmada_em : 'Marcar como confirmada (pulseira entregue)'" @click="alternarConfirmacao(inscricao)">
                                    {{ inscricao.confirmada ? 'Confirmada' : 'Confirmar' }}
                                </button>
                                <button type="button" class="btn-apagar" :aria-label="`Apagar a inscrição de ${inscricao.nome}`" title="Apagar" @click="apagar(inscricao)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                                </button>
                            </div>
                        </li>
                    </ul>
                </template>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.cartao { @apply rounded-[14px] border border-linha bg-white p-5; }
.btn-confirmar { @apply inline-flex h-11 min-w-[130px] items-center justify-center rounded-[10px] px-4 text-[15px] font-extrabold transition; }
.btn-apagar { @apply inline-grid h-11 w-11 shrink-0 place-items-center rounded-[10px] border border-linha-forte bg-white align-middle text-perigo hover:bg-perigo-claro; }
</style>
