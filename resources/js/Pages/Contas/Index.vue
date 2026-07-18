<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    filters:           Object,
    movimentos:        Array,
    resumo:            Object,
    festaAno:          Number,
    lucroFesta:        Object,
    categoriasEntrada: Array,
    categoriasSaida:   Array,
});

// ── Filtros ─────────────────────────────────────────────────────────────────
const filtros = reactive({ ...props.filters });
const filtrar = () =>
    router.get(route('contas-bancarias.index'), filtros, {
        preserveState: true,
        preserveScroll: true,
    });

const iso = (d) => d.toISOString().slice(0, 10);
const aplicarPeriodo = (periodo) => {
    const hoje = new Date();
    let inicio = new Date(hoje);
    let fim    = new Date(hoje);

    if (periodo === 'mes') {
        inicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
        fim    = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    } else if (periodo === 'trimestre') {
        const t  = Math.floor(hoje.getMonth() / 3);
        inicio   = new Date(hoje.getFullYear(), t * 3, 1);
        fim      = new Date(hoje.getFullYear(), t * 3 + 3, 0);
    } else if (periodo === 'ano') {
        inicio = new Date(hoje.getFullYear(), 0, 1);
        fim    = new Date(hoje.getFullYear(), 11, 31);
    } else if (periodo === 'tudo') {
        filtros.data_inicio = '';
        filtros.data_fim    = '';
        filtrar();
        return;
    }

    filtros.data_inicio = iso(inicio);
    filtros.data_fim    = iso(fim);
    filtrar();
};

// ── Helpers ──────────────────────────────────────────────────────────────────
const euros = (v) =>
    Number(v || 0).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });

const categoriasParaTipo = (tipo) =>
    tipo === 'entrada' ? props.categoriasEntrada : props.categoriasSaida;

const labelCategoria = (cat) => {
    const todas = [...(props.categoriasEntrada ?? []), ...(props.categoriasSaida ?? [])];
    return todas.find((c) => c.valor === cat)?.label ?? (cat ?? '—');
};

// ── Formulário Novo Movimento ────────────────────────────────────────────────
const hoje = new Date().toISOString().slice(0, 10);

const form = useForm({
    tipo:       'entrada',
    descricao:  '',
    valor:      '',
    data:       hoje,
    categoria:  props.categoriasEntrada?.[0]?.valor ?? '',
    conta:      'banco',
    referencia: '',
    notas:      '',
});

const ajustarCategoria = (formulario) => {
    const cats = categoriasParaTipo(formulario.tipo);
    if (!cats?.some((c) => c.valor === formulario.categoria)) {
        formulario.categoria = cats?.[0]?.valor ?? '';
    }
};

const criarMovimento = () => {
    form.post(route('contas-bancarias.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('descricao', 'valor', 'referencia', 'notas');
            form.data = hoje;
        },
    });
};

// Pré-preencher com lucro da festa
const preencherLucroFesta = () => {
    form.tipo       = 'entrada';
    form.categoria  = 'lucro_festa';
    form.descricao  = `Lucro da Festa ${props.festaAno}`;
    form.valor      = Math.abs(props.lucroFesta?.lucro ?? 0).toFixed(2);
    form.conta      = 'banco';
    form.data       = hoje;
};

// ── Edição ───────────────────────────────────────────────────────────────────
const edicaoId   = ref(null);
const editForm   = useForm({
    tipo:       'entrada',
    descricao:  '',
    valor:      '',
    data:       '',
    categoria:  '',
    conta:      'banco',
    referencia: '',
    notas:      '',
});

const abrirEdicao = (m) => {
    edicaoId.value      = m.id;
    editForm.tipo       = m.tipo;
    editForm.descricao  = m.descricao;
    editForm.valor      = m.valor;
    editForm.data       = m.data ? String(m.data).slice(0, 10) : '';
    editForm.categoria  = m.categoria ?? '';
    editForm.conta      = m.conta;
    editForm.referencia = m.referencia ?? '';
    editForm.notas      = m.notas ?? '';
    editForm.clearErrors();
};

const guardarEdicao = (m) => {
    editForm.patch(route('contas-bancarias.update', m.id), {
        preserveScroll: true,
        onSuccess: () => { edicaoId.value = null; },
    });
};

const apagar = (m) => {
    if (!confirm(`Apagar movimento "${m.descricao}"?`)) return;
    router.delete(route('contas-bancarias.destroy', m.id), { preserveScroll: true });
};

// ── Modal Saldo Confirmado ────────────────────────────────────────────────────
const modalSaldo = ref(null); // null | 'banco' | 'prazo'
const saldoForm = useForm({
    conta:  'banco',
    valor:  '',
    data:   hoje,
    notas:  '',
});

const abrirModalSaldo = (conta) => {
    modalSaldo.value  = conta;
    saldoForm.conta   = conta;
    const conf = conta === 'banco'
        ? props.resumo?.saldo_banco_confirmado
        : props.resumo?.saldo_prazo_confirmado;
    saldoForm.valor   = conf?.valor ?? '';
    saldoForm.data    = conf?.data ?? hoje;
    saldoForm.notas   = conf?.notas ?? '';
    saldoForm.clearErrors();
};

const guardarSaldo = () => {
    saldoForm.post(route('contas-bancarias.saldo'), {
        preserveScroll: true,
        onSuccess: () => { modalSaldo.value = null; },
    });
};

// ── Computed ─────────────────────────────────────────────────────────────────
const saldoBancoConf = computed(() => props.resumo?.saldo_banco_confirmado);
const saldoPrazoConf = computed(() => props.resumo?.saldo_prazo_confirmado);
const totalEntradas  = computed(() => props.resumo?.total_entradas ?? 0);
const totalSaidas    = computed(() => props.resumo?.total_saidas   ?? 0);
const resultado      = computed(() => props.resumo?.resultado ?? 0);
const lucroFesta     = computed(() => props.lucroFesta?.lucro ?? 0);
</script>

<template>
    <AppLayout>
        <Head title="Contas Bancárias" />

        <!-- Modal saldo confirmado -->
        <div v-if="modalSaldo" class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/50 p-4" @click.self="modalSaldo = null">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="mb-4 font-black text-stone-800">
                    Actualizar saldo — {{ modalSaldo === 'banco' ? 'Conta Bancária' : 'Conta a Prazo' }}
                </h3>
                <form @submit.prevent="guardarSaldo" class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-stone-600">Saldo actual (€) *</label>
                        <input v-model="saldoForm.valor" type="number" step="0.01" min="0" required autofocus
                            placeholder="Ex: 3 500.00"
                            class="w-full rounded-lg border-stone-300 text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                        <p v-if="saldoForm.errors.valor" class="mt-0.5 text-xs text-red-600">{{ saldoForm.errors.valor }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-stone-600">Data do extrato *</label>
                        <input v-model="saldoForm.data" type="date" required
                            class="w-full rounded-lg border-stone-300 text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-stone-600">Notas</label>
                        <input v-model="saldoForm.notas" type="text" placeholder="Ex: Conferido em julho 2026"
                            class="w-full rounded-lg border-stone-300 text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" class="rounded-lg border px-4 py-2 text-sm font-medium text-stone-600 hover:bg-stone-50 transition" @click="modalSaldo = null">Cancelar</button>
                        <button type="submit" :disabled="saldoForm.processing"
                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700 disabled:opacity-50 transition">
                            Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="px-4 py-6 sm:px-6 lg:px-8">

            <!-- Cabeçalho -->
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-black text-stone-800 sm:text-2xl">Contas Bancárias</h1>
                <div class="flex flex-wrap gap-2 text-xs">
                    <button v-for="p in [['mes','Este mês'],['trimestre','Trimestre'],['ano','Este ano'],['tudo','Tudo']]"
                        :key="p[0]" type="button"
                        class="rounded-lg border border-amber-200 bg-white px-3 py-1.5 font-medium text-stone-600 hover:bg-amber-50 transition"
                        @click="aplicarPeriodo(p[0])">{{ p[1] }}</button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-amber-200 bg-white p-4 shadow-sm">
                <div>
                    <label class="mb-1 block text-xs font-bold text-stone-600">Data início</label>
                    <input v-model="filtros.data_inicio" type="date" @change="filtrar"
                        class="rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold text-stone-600">Data fim</label>
                    <input v-model="filtros.data_fim" type="date" @change="filtrar"
                        class="rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold text-stone-600">Conta</label>
                    <select v-model="filtros.conta" @change="filtrar"
                        class="rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="">Todas</option>
                        <option value="banco">Conta bancária</option>
                        <option value="prazo">Conta a prazo</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold text-stone-600">Tipo</label>
                    <select v-model="filtros.tipo" @change="filtrar"
                        class="rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="">Todos</option>
                        <option value="entrada">Entradas</option>
                        <option value="saida">Saídas</option>
                    </select>
                </div>
            </div>

            <!-- Cards de saldo real (confirmado do extrato) -->
            <div class="mb-4 grid gap-4 sm:grid-cols-2">
                <!-- Conta bancária -->
                <div class="rounded-xl border-2 bg-white p-5 shadow-sm"
                    :class="saldoBancoConf ? 'border-blue-300' : 'border-dashed border-stone-300'">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Conta Bancária</p>
                            <p v-if="saldoBancoConf" class="mt-1 text-2xl font-black"
                                :class="saldoBancoConf.valor >= 0 ? 'text-emerald-700' : 'text-red-600'">
                                {{ euros(saldoBancoConf.valor) }}
                            </p>
                            <p v-else class="mt-1 text-lg font-semibold text-stone-400">Não definido</p>
                            <p v-if="saldoBancoConf" class="mt-0.5 text-xs text-stone-400">
                                Extrato de {{ saldoBancoConf.data }}
                                <span v-if="saldoBancoConf.notas"> · {{ saldoBancoConf.notas }}</span>
                            </p>
                            <p v-else class="mt-0.5 text-xs text-stone-400">Introduz o saldo do teu extrato bancário</p>
                        </div>
                        <button type="button"
                            class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100 transition"
                            @click="abrirModalSaldo('banco')">
                            {{ saldoBancoConf ? 'Actualizar' : 'Definir' }}
                        </button>
                    </div>
                </div>

                <!-- Conta a prazo -->
                <div class="rounded-xl border-2 bg-white p-5 shadow-sm"
                    :class="saldoPrazoConf ? 'border-purple-300' : 'border-dashed border-stone-300'">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Conta a Prazo</p>
                            <p v-if="saldoPrazoConf" class="mt-1 text-2xl font-black"
                                :class="saldoPrazoConf.valor >= 0 ? 'text-emerald-700' : 'text-red-600'">
                                {{ euros(saldoPrazoConf.valor) }}
                            </p>
                            <p v-else class="mt-1 text-lg font-semibold text-stone-400">Não definido</p>
                            <p v-if="saldoPrazoConf" class="mt-0.5 text-xs text-stone-400">
                                Extrato de {{ saldoPrazoConf.data }}
                                <span v-if="saldoPrazoConf.notas"> · {{ saldoPrazoConf.notas }}</span>
                            </p>
                            <p v-else class="mt-0.5 text-xs text-stone-400">Introduz o saldo do teu extrato</p>
                        </div>
                        <button type="button"
                            class="rounded-lg border border-purple-200 bg-purple-50 px-3 py-1.5 text-xs font-bold text-purple-700 hover:bg-purple-100 transition"
                            @click="abrirModalSaldo('prazo')">
                            {{ saldoPrazoConf ? 'Actualizar' : 'Definir' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Card de saldo total + lucro festa -->
            <div class="mb-6 grid gap-4 sm:grid-cols-2">
                <!-- Saldo total confirmado -->
                <div v-if="saldoBancoConf || saldoPrazoConf"
                    class="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Saldo Total Confirmado</p>
                    <p class="mt-1 text-2xl font-black"
                        :class="(saldoBancoConf?.valor ?? 0) + (saldoPrazoConf?.valor ?? 0) >= 0 ? 'text-emerald-700' : 'text-red-600'">
                        {{ euros((saldoBancoConf?.valor ?? 0) + (saldoPrazoConf?.valor ?? 0)) }}
                    </p>
                    <p class="mt-0.5 text-xs text-amber-600">Banco + Prazo (extratos)</p>
                </div>

                <!-- Lucro das festas -->
                <div class="rounded-xl border p-4 shadow-sm"
                    :class="lucroFesta >= 0 ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide"
                                :class="lucroFesta >= 0 ? 'text-emerald-700' : 'text-red-700'">
                                {{ lucroFesta >= 0 ? 'Lucro' : 'Prejuízo' }} da Festa {{ festaAno }}
                            </p>
                            <p class="mt-1 text-2xl font-black"
                                :class="lucroFesta >= 0 ? 'text-emerald-800' : 'text-red-800'">
                                {{ euros(lucroFesta) }}
                            </p>
                            <p class="mt-0.5 text-xs"
                                :class="lucroFesta >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                Receitas: {{ euros(props.lucroFesta?.total_receitas) }} · Custos: {{ euros(props.lucroFesta?.total_custos) }}
                            </p>
                        </div>
                        <button v-if="lucroFesta > 0" type="button"
                            class="shrink-0 rounded-lg border border-emerald-300 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition"
                            @click="preencherLucroFesta">
                            Registar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cards do período filtrado -->
            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Entradas (período)</p>
                    <p class="mt-1 text-xl font-black text-emerald-800">{{ euros(totalEntradas) }}</p>
                </div>
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-red-700">Saídas (período)</p>
                    <p class="mt-1 text-xl font-black text-red-800">{{ euros(totalSaidas) }}</p>
                </div>
                <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-stone-500">Resultado (período)</p>
                    <p class="mt-1 text-xl font-black" :class="resultado >= 0 ? 'text-emerald-700' : 'text-red-600'">
                        {{ euros(resultado) }}
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1fr_360px]">

                <!-- Tabela de movimentos -->
                <div class="rounded-xl border border-amber-200 bg-white shadow-sm">
                    <div class="border-b border-amber-100 px-5 py-3">
                        <h2 class="font-bold text-stone-800">Movimentos ({{ movimentos.length }})</h2>
                    </div>

                    <div v-if="movimentos.length === 0" class="py-12 text-center text-sm text-stone-400">
                        Nenhum movimento no período selecionado.
                    </div>

                    <div v-else class="divide-y divide-amber-50">
                        <div v-for="m in movimentos" :key="m.id">
                            <!-- Linha normal -->
                            <div v-if="edicaoId !== m.id" class="flex flex-wrap items-start gap-3 px-4 py-3 hover:bg-amber-50/50 transition">
                                <div class="mt-0.5 shrink-0">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-black"
                                        :class="m.tipo === 'entrada' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">
                                        {{ m.tipo === 'entrada' ? '+' : '−' }}
                                    </span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                                        <span class="truncate font-semibold text-stone-800 text-sm">{{ m.descricao }}</span>
                                        <span class="shrink-0 font-black text-sm"
                                            :class="m.tipo === 'entrada' ? 'text-emerald-700' : 'text-red-600'">
                                            {{ m.tipo === 'entrada' ? '+' : '−' }}{{ euros(m.valor) }}
                                        </span>
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-stone-400">
                                        <span>{{ String(m.data).slice(0, 10) }}</span>
                                        <span class="rounded bg-stone-100 px-1.5 py-0.5 text-stone-500">
                                            {{ m.conta === 'banco' ? 'Banco' : 'A prazo' }}
                                        </span>
                                        <span v-if="m.categoria" class="rounded bg-amber-50 px-1.5 py-0.5 text-amber-700">
                                            {{ labelCategoria(m.categoria) }}
                                        </span>
                                        <span v-if="m.referencia">Ref: {{ m.referencia }}</span>
                                    </div>
                                    <div v-if="m.notas" class="mt-0.5 text-xs italic text-stone-400">{{ m.notas }}</div>
                                </div>
                                <div class="flex shrink-0 gap-1.5">
                                    <button type="button"
                                        class="rounded-lg border border-stone-200 px-2 py-1 text-xs font-medium text-stone-600 hover:bg-amber-50 transition"
                                        @click="abrirEdicao(m)">Editar</button>
                                    <button type="button"
                                        class="rounded-lg border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 transition"
                                        @click="apagar(m)">Apagar</button>
                                </div>
                            </div>

                            <!-- Linha de edição inline -->
                            <div v-else class="bg-amber-50/70 px-4 py-3">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div class="sm:col-span-2 grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="mb-0.5 block text-xs font-bold text-stone-600">Tipo</label>
                                            <select v-model="editForm.tipo" @change="ajustarCategoria(editForm)"
                                                class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                                <option value="entrada">Entrada</option>
                                                <option value="saida">Saída</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-0.5 block text-xs font-bold text-stone-600">Conta</label>
                                            <select v-model="editForm.conta"
                                                class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                                <option value="banco">Conta bancária</option>
                                                <option value="prazo">Conta a prazo</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="mb-0.5 block text-xs font-bold text-stone-600">Descrição</label>
                                        <input v-model="editForm.descricao" type="text"
                                            class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                                    </div>
                                    <div>
                                        <label class="mb-0.5 block text-xs font-bold text-stone-600">Valor (€)</label>
                                        <input v-model="editForm.valor" type="number" step="0.01" min="0.01"
                                            class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                                    </div>
                                    <div>
                                        <label class="mb-0.5 block text-xs font-bold text-stone-600">Data</label>
                                        <input v-model="editForm.data" type="date"
                                            class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                                    </div>
                                    <div>
                                        <label class="mb-0.5 block text-xs font-bold text-stone-600">Categoria</label>
                                        <select v-model="editForm.categoria"
                                            class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                            <option value="">— sem categoria —</option>
                                            <option v-for="c in categoriasParaTipo(editForm.tipo)" :key="c.valor" :value="c.valor">{{ c.label }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-0.5 block text-xs font-bold text-stone-600">Referência</label>
                                        <input v-model="editForm.referencia" type="text"
                                            class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="mb-0.5 block text-xs font-bold text-stone-600">Notas</label>
                                        <input v-model="editForm.notas" type="text"
                                            class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                                    </div>
                                </div>
                                <div class="mt-3 flex justify-end gap-2">
                                    <button type="button"
                                        class="rounded-lg border border-stone-200 px-3 py-1.5 text-xs font-medium text-stone-600 hover:bg-white transition"
                                        @click="edicaoId = null">Cancelar</button>
                                    <button type="button" :disabled="editForm.processing"
                                        class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700 disabled:opacity-50 transition"
                                        @click="guardarEdicao(m)">Guardar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulário novo movimento -->
                <div class="shrink-0">
                    <div class="sticky top-6 rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-4 font-bold text-stone-800">Registar movimento</h2>
                        <form @submit.prevent="criarMovimento" class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-bold text-stone-600">Tipo</label>
                                    <select v-model="form.tipo" @change="ajustarCategoria(form)"
                                        class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                        <option value="entrada">Entrada</option>
                                        <option value="saida">Saída</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-bold text-stone-600">Conta</label>
                                    <select v-model="form.conta"
                                        class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                        <option value="banco">Conta bancária</option>
                                        <option value="prazo">Conta a prazo</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-stone-600">Descrição *</label>
                                <input v-model="form.descricao" type="text" required
                                    placeholder="Ex: Renda do café - Julho"
                                    class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                                <p v-if="form.errors.descricao" class="mt-0.5 text-xs text-red-600">{{ form.errors.descricao }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-bold text-stone-600">Valor (€) *</label>
                                    <input v-model="form.valor" type="number" step="0.01" min="0.01" required
                                        placeholder="0.00"
                                        class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                                    <p v-if="form.errors.valor" class="mt-0.5 text-xs text-red-600">{{ form.errors.valor }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-bold text-stone-600">Data *</label>
                                    <input v-model="form.data" type="date" required
                                        class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-stone-600">Categoria</label>
                                <select v-model="form.categoria"
                                    class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                    <option value="">— sem categoria —</option>
                                    <option v-for="c in categoriasParaTipo(form.tipo)" :key="c.valor" :value="c.valor">{{ c.label }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-stone-600">Referência</label>
                                <input v-model="form.referencia" type="text"
                                    placeholder="Nº documento, fatura..."
                                    class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-stone-600">Notas</label>
                                <textarea v-model="form.notas" rows="2"
                                    placeholder="Observações adicionais..."
                                    class="w-full rounded-lg border-stone-300 text-sm text-stone-800 shadow-sm focus:border-amber-500 focus:ring-amber-500" />
                            </div>

                            <button type="submit" :disabled="form.processing"
                                class="w-full rounded-xl py-2.5 text-sm font-bold text-white transition disabled:opacity-50"
                                :class="form.tipo === 'entrada' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'">
                                <span v-if="form.processing">A guardar...</span>
                                <span v-else-if="form.tipo === 'entrada'">+ Registar entrada</span>
                                <span v-else>− Registar saída</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
