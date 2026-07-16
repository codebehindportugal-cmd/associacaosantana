<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
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
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <Link :href="route('eventos.index')" class="text-sm font-bold text-amber-700">← Eventos</Link>
                <h1 class="text-2xl font-black">Inscrições — {{ evento.titulo }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    <span class="font-bold" :class="evento.inscricoes_ativas ? 'text-emerald-700' : 'text-red-700'">
                        {{ evento.inscricoes_ativas ? 'Inscrições ABERTAS' : 'Inscrições FECHADAS' }}
                    </span>
                    <span v-if="evento.inscricoes_limite"> · limite {{ evento.inscricoes_limite }} pessoas</span>
                    — ativa/desativa na <Link :href="route('eventos.edit', evento.id)" class="font-bold text-amber-700 underline">edição do evento</Link>
                </p>
            </div>
            <button type="button" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white" @click="exportarCsv">Exportar CSV</button>
        </div>

        <section class="mb-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <div class="text-sm font-bold text-slate-500">Inscrições</div>
                <div class="mt-1 text-3xl font-black">{{ totais.inscricoes }}</div>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <div class="text-sm font-bold text-slate-500">Total de pessoas</div>
                <div class="mt-1 text-3xl font-black text-emerald-700">{{ totais.pessoas }}</div>
                <div v-if="totais.valor" class="mt-1 text-sm font-bold text-slate-500">≈ {{ Number(totais.valor).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' }) }} estimados</div>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <div class="text-sm font-bold text-slate-500">QR para os cartazes (fixo, nunca muda)</div>
                <div class="mt-2 flex items-center gap-3">
                    <img v-if="qrDataUrl" :src="qrDataUrl" class="h-24 w-24 rounded border border-slate-200" alt="QR inscrições">
                    <div class="text-xs text-slate-500">
                        <div class="font-bold text-slate-700">{{ urlPublica }}</div>
                        <a v-if="qrDataUrl" :href="qrDataUrl" download="qr-inscricoes-santana.png" class="mt-1 inline-block rounded bg-amber-600 px-2 py-1 font-bold text-white">Descarregar PNG</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm">
            <div v-if="!inscricoes.length" class="rounded-md bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">
                Ainda não há inscrições.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2">Nome</th>
                            <th>Telefone</th>
                            <th class="text-center">Pessoas</th>
                            <th v-if="evento.tem_opcoes">Opção</th>
                            <th v-if="evento.pede_idades">Crianças</th>
                            <th class="text-right">Valor</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inscricao in inscricoes" :key="inscricao.id" class="border-t border-slate-100">
                            <td class="py-3">
                                <strong>{{ inscricao.nome }}</strong>
                                <span v-if="inscricao.pagamento_estado === 'pago'" class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-black text-emerald-800">PAGO</span>
                                <span v-else-if="inscricao.pagamento_estado === 'pendente'" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-black text-amber-800">PAG. PENDENTE</span>
                                <span v-else-if="inscricao.pagamento_estado === 'falhado'" class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-black text-red-700">PAG. FALHOU</span>
                                <div v-if="inscricao.email" class="text-xs text-slate-500">{{ inscricao.email }}</div>
                                <div v-if="inscricao.observacoes" class="text-xs text-slate-500">{{ inscricao.observacoes }}</div>
                            </td>
                            <td><a :href="`tel:${inscricao.telefone}`" class="font-bold text-amber-700">{{ inscricao.telefone }}</a></td>
                            <td class="text-center font-black">{{ inscricao.num_pessoas }}</td>
                            <td v-if="evento.tem_opcoes">{{ inscricao.opcao ?? '—' }}</td>
                            <td v-if="evento.pede_idades">
                                <template v-if="inscricao.num_criancas">{{ inscricao.num_criancas }}<span v-if="inscricao.idades_criancas" class="text-xs text-slate-500"> ({{ inscricao.idades_criancas }})</span></template>
                                <template v-else>—</template>
                            </td>
                            <td class="text-right font-bold">{{ inscricao.valor_estimado !== null ? Number(inscricao.valor_estimado).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' }) : '—' }}</td>
                            <td class="text-xs text-slate-500">{{ inscricao.criado_em }}</td>
                            <td class="text-right">
                                <button type="button" class="font-bold text-red-700" @click="apagar(inscricao)">Apagar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
