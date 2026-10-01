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

// ── Só para a apresentação ───────────────────────────────────────────────────
const periodoAtivo = ref(null);
const escolherPeriodo = (periodo) => {
    periodoAtivo.value = periodo;
    aplicarPeriodo(periodo);
};
const escolherTipo = (tipo) => {
    form.tipo = tipo;
    ajustarCategoria(form);
};
const comSinal = (v) => (Number(v) > 0 ? '+' : Number(v) < 0 ? '−' : '') + euros(Math.abs(Number(v) || 0));
const campo = 'h-12 w-full min-w-0 rounded-[10px] border border-linha-forte bg-white px-3 text-base text-tinta focus:border-verde focus:ring-verde';
const campoPequeno = 'h-11 w-full min-w-0 rounded-[10px] border border-linha-forte bg-white px-3 text-[15px] text-tinta focus:border-verde focus:ring-verde';
</script>

<template>
    <AppLayout>
        <Head title="Contas Bancárias" />

        <!-- Janela: atualizar saldo confirmado -->
        <div v-if="modalSaldo" class="fixed inset-0 z-50 flex items-center justify-center bg-tinta/50 p-4" @click.self="modalSaldo = null" @keydown.esc="modalSaldo = null">
            <div role="dialog" aria-modal="true" aria-labelledby="dlg-saldo" class="flex w-full max-w-[420px] flex-col gap-3 rounded-[14px] bg-white p-5 font-sans text-tinta shadow-2xl tabular-nums">
                <h3 id="dlg-saldo" class="text-[19px] font-extrabold">
                    Atualizar saldo — {{ modalSaldo === 'banco' ? 'Conta bancária' : 'Conta a prazo' }}
                </h3>
                <form class="flex flex-col gap-3" @submit.prevent="guardarSaldo">
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Saldo atual (€) *
                        <input v-model="saldoForm.valor" type="number" step="0.01" min="0" required autofocus inputmode="decimal" placeholder="Ex: 3 500,00" :class="[campo, 'text-lg font-bold']" />
                        <span v-if="saldoForm.errors.valor" class="text-[13px] font-semibold text-perigo">{{ saldoForm.errors.valor }}</span>
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Data do extrato *
                        <input v-model="saldoForm.data" type="date" required :class="[campo, 'text-[15px]']" />
                        <span v-if="saldoForm.errors.data" class="text-[13px] font-semibold text-perigo">{{ saldoForm.errors.data }}</span>
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Notas
                        <input v-model="saldoForm.notas" type="text" placeholder="Ex: Conferido em julho 2026" :class="[campo, 'text-[15px]']" />
                    </label>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-4 font-bold text-tinta hover:bg-fundo" @click="modalSaldo = null">Cancelar</button>
                        <button type="submit" :disabled="saldoForm.processing" class="h-11 rounded-[10px] bg-verde px-5 font-bold text-white hover:bg-verde-escuro disabled:opacity-50">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-col gap-1.5">
                <h1 class="text-[30px] font-extrabold leading-tight">Contas bancárias</h1>
                <p class="text-[15px] text-suave">Saldo real dos extratos, entradas e saídas da associação.</p>
            </div>

            <!-- Saldos confirmados -->
            <section aria-label="Saldos confirmados" class="grid gap-3 md:grid-cols-3">
                <div v-for="conta in [['banco', 'Conta bancária', saldoBancoConf, 'Introduz o saldo do teu extrato bancário'], ['prazo', 'Conta a prazo', saldoPrazoConf, 'Introduz o saldo do teu extrato']]" :key="conta[0]" class="flex flex-col gap-2.5 rounded-[14px] bg-white p-[18px]" :class="conta[2] ? 'border border-linha' : 'border-2 border-dashed border-linha-forte'">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-bold text-suave-2">{{ conta[1] }}</span>
                        <button type="button" class="h-9 rounded-[10px] border border-linha-forte bg-white px-3.5 text-sm font-bold text-verde hover:bg-verde-claro" @click="abrirModalSaldo(conta[0])">{{ conta[2] ? 'Atualizar' : 'Definir' }}</button>
                    </div>
                    <template v-if="conta[2]">
                        <strong class="text-[30px] font-extrabold" :class="conta[2].valor >= 0 ? '' : 'text-perigo'">{{ euros(conta[2].valor) }}</strong>
                        <span class="text-[13px] text-suave-2">Extrato de {{ conta[2].data }}<template v-if="conta[2].notas"> · {{ conta[2].notas }}</template></span>
                    </template>
                    <template v-else>
                        <strong class="text-lg font-semibold text-suave-2">Não definido</strong>
                        <span class="text-[13px] text-suave-2">{{ conta[3] }}</span>
                    </template>
                </div>
                <div v-if="saldoBancoConf || saldoPrazoConf" class="flex flex-col gap-2.5 rounded-[14px] bg-escuro p-[18px] text-white">
                    <span class="text-sm font-bold text-escuro-inativo">Saldo total confirmado</span>
                    <strong class="text-[30px] font-extrabold">{{ euros((saldoBancoConf?.valor ?? 0) + (saldoPrazoConf?.valor ?? 0)) }}</strong>
                    <span class="text-[13px] text-escuro-inativo">Banco + Prazo (extratos)</span>
                </div>
            </section>

            <!-- Lucro da festa -->
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-[14px] border px-[18px] py-4" :class="lucroFesta >= 0 ? 'border-verde-claro2 bg-verde-claro text-verde-escuro' : 'border-[#F0D3CD] bg-perigo-claro text-perigo-texto'">
                <div class="flex flex-col gap-0.5">
                    <span class="text-sm font-bold">{{ lucroFesta >= 0 ? 'Lucro' : 'Prejuízo' }} da Festa {{ festaAno }}</span>
                    <strong class="text-2xl font-extrabold">{{ euros(lucroFesta) }}</strong>
                    <span class="text-[13px]">Receitas: {{ euros(props.lucroFesta?.total_receitas) }} · Custos: {{ euros(props.lucroFesta?.total_custos) }}</span>
                </div>
                <button v-if="lucroFesta > 0" type="button" class="h-12 rounded-[10px] bg-verde px-[18px] text-[15px] font-bold text-white hover:bg-verde-escuro" @click="preencherLucroFesta">Registar como entrada</button>
            </section>

            <!-- Filtros + resumo do período -->
            <section aria-label="Filtros" class="flex flex-col gap-3.5 rounded-[14px] border border-linha bg-white p-4">
                <div role="group" aria-label="Período rápido" class="flex flex-wrap gap-2">
                    <button
                        v-for="p in [['mes','Este mês'],['trimestre','Trimestre'],['ano','Este ano'],['tudo','Tudo']]"
                        :key="p[0]"
                        type="button"
                        :aria-pressed="periodoAtivo === p[0]"
                        class="h-11 rounded-full px-4 text-sm font-bold"
                        :class="periodoAtivo === p[0] ? 'bg-tinta text-white' : 'border border-linha-forte bg-white text-tinta hover:bg-fundo'"
                        @click="escolherPeriodo(p[0])"
                    >{{ p[1] }}</button>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <label class="flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave">Data início
                        <input v-model="filtros.data_inicio" type="date" :class="campoPequeno" @change="periodoAtivo = null; filtrar()" />
                    </label>
                    <label class="flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave">Data fim
                        <input v-model="filtros.data_fim" type="date" :class="campoPequeno" @change="periodoAtivo = null; filtrar()" />
                    </label>
                    <label class="flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave">Conta
                        <select v-model="filtros.conta" :class="campoPequeno" @change="filtrar">
                            <option value="">Todas</option>
                            <option value="banco">Conta bancária</option>
                            <option value="prazo">Conta a prazo</option>
                        </select>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave">Tipo
                        <select v-model="filtros.tipo" :class="campoPequeno" @change="filtrar">
                            <option value="">Todos</option>
                            <option value="entrada">Entradas</option>
                            <option value="saida">Saídas</option>
                        </select>
                    </label>
                </div>
                <div class="grid grid-cols-1 gap-3 border-t border-linha-fraca pt-3.5 sm:grid-cols-3">
                    <div><div class="text-[13px] font-semibold text-suave-2">Entradas</div><strong class="text-[22px] text-verde">+{{ euros(totalEntradas) }}</strong></div>
                    <div><div class="text-[13px] font-semibold text-suave-2">Saídas</div><strong class="text-[22px] text-perigo">−{{ euros(totalSaidas) }}</strong></div>
                    <div><div class="text-[13px] font-semibold text-suave-2">Resultado</div><strong class="text-[22px]" :class="resultado >= 0 ? 'text-tinta' : 'text-perigo'">{{ comSinal(resultado) }}</strong></div>
                </div>
            </section>

            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                <!-- Movimentos -->
                <section class="min-w-0 overflow-hidden rounded-[14px] border border-linha bg-white">
                    <div class="border-b border-linha-fraca px-5 py-4">
                        <h2 class="text-[19px] font-extrabold">Movimentos <span class="font-semibold text-suave-2">({{ movimentos.length }})</span></h2>
                    </div>

                    <div v-if="movimentos.length === 0" class="py-12 text-center text-suave">
                        Nenhum movimento no período selecionado.
                    </div>

                    <ul v-else>
                        <li v-for="m in movimentos" :key="m.id" class="border-b border-linha-fraca last:border-0">
                            <!-- Linha normal -->
                            <div v-if="edicaoId !== m.id" class="flex flex-wrap items-start gap-3.5 px-4 py-3.5 sm:flex-nowrap sm:px-5">
                                <span aria-hidden="true" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full font-extrabold" :class="m.tipo === 'entrada' ? 'bg-verde-claro text-verde' : 'bg-perigo-claro text-perigo'">{{ m.tipo === 'entrada' ? '+' : '−' }}</span>
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <div class="flex flex-wrap justify-between gap-x-3 gap-y-0.5">
                                        <strong class="min-w-0 break-words text-base">{{ m.descricao }}</strong>
                                        <strong class="whitespace-nowrap text-base" :class="m.tipo === 'entrada' ? 'text-verde' : 'text-perigo'">{{ m.tipo === 'entrada' ? '+' : '−' }}{{ euros(m.valor) }}</strong>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5 text-[13px] text-suave-2">
                                        <span>{{ String(m.data).slice(0, 10) }}</span>
                                        <span class="rounded-full bg-linha-fraca px-2">{{ m.conta === 'banco' ? 'Banco' : 'A prazo' }}</span>
                                        <span v-if="m.categoria" class="rounded-full bg-verde-claro px-2 text-verde-escuro">{{ labelCategoria(m.categoria) }}</span>
                                        <span v-if="m.referencia">Ref: {{ m.referencia }}</span>
                                    </div>
                                    <div v-if="m.notas" class="text-[13px] italic text-suave-2">{{ m.notas }}</div>
                                </div>
                                <div class="ml-auto flex shrink-0 gap-1.5">
                                    <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-3 text-sm font-semibold text-tinta hover:bg-fundo sm:h-10" @click="abrirEdicao(m)">Editar</button>
                                    <button type="button" :aria-label="`Apagar ${m.descricao}`" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-[#F0D3CD] bg-white text-perigo hover:bg-perigo-claro sm:h-10 sm:w-10" @click="apagar(m)">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Edição inline -->
                            <div v-else class="flex flex-col gap-3 bg-fundo px-4 py-4 sm:px-5">
                                <div class="text-sm font-extrabold text-verde">A editar movimento</div>
                                <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                                    <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Tipo
                                        <select v-model="editForm.tipo" :class="campoPequeno" @change="ajustarCategoria(editForm)">
                                            <option value="entrada">Entrada</option>
                                            <option value="saida">Saída</option>
                                        </select>
                                    </label>
                                    <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Conta
                                        <select v-model="editForm.conta" :class="campoPequeno">
                                            <option value="banco">Conta bancária</option>
                                            <option value="prazo">Conta a prazo</option>
                                        </select>
                                    </label>
                                    <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Valor (€)
                                        <input v-model="editForm.valor" type="number" step="0.01" min="0.01" inputmode="decimal" :class="campoPequeno" />
                                    </label>
                                    <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Data
                                        <input v-model="editForm.data" type="date" :class="campoPequeno" />
                                    </label>
                                </div>
                                <div class="grid gap-2.5 sm:grid-cols-2">
                                    <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Descrição
                                        <input v-model="editForm.descricao" type="text" :class="campoPequeno" />
                                    </label>
                                    <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Categoria
                                        <select v-model="editForm.categoria" :class="campoPequeno">
                                            <option value="">— sem categoria —</option>
                                            <option v-for="c in categoriasParaTipo(editForm.tipo)" :key="c.valor" :value="c.valor">{{ c.label }}</option>
                                        </select>
                                    </label>
                                    <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Referência
                                        <input v-model="editForm.referencia" type="text" :class="campoPequeno" />
                                    </label>
                                    <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Notas
                                        <input v-model="editForm.notas" type="text" :class="campoPequeno" />
                                    </label>
                                </div>
                                <div v-if="Object.keys(editForm.errors).length" role="alert" class="rounded-[10px] bg-perigo-claro px-3 py-2 text-sm font-semibold text-perigo-texto">
                                    <div v-for="erro in editForm.errors" :key="erro">{{ erro }}</div>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-4 text-sm font-bold text-tinta" @click="edicaoId = null">Cancelar</button>
                                    <button type="button" :disabled="editForm.processing" class="h-11 rounded-[10px] bg-verde px-5 text-sm font-bold text-white hover:bg-verde-escuro disabled:opacity-50" @click="guardarEdicao(m)">Guardar</button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </section>

                <!-- Registar movimento -->
                <form class="flex flex-col gap-3.5 rounded-[14px] border border-linha bg-white p-5 xl:sticky xl:top-6" @submit.prevent="criarMovimento">
                    <h2 class="text-xl font-extrabold">Registar movimento</h2>
                    <div role="radiogroup" aria-label="Tipo" class="grid grid-cols-2 gap-1.5 rounded-xl bg-fundo p-1">
                        <button type="button" role="radio" :aria-checked="form.tipo === 'entrada'" class="h-11 rounded-[10px] text-[15px] font-extrabold" :class="form.tipo === 'entrada' ? 'bg-verde text-white' : 'text-suave'" @click="escolherTipo('entrada')">+ Entrada</button>
                        <button type="button" role="radio" :aria-checked="form.tipo === 'saida'" class="h-11 rounded-[10px] text-[15px] font-extrabold" :class="form.tipo === 'saida' ? 'bg-perigo text-white' : 'text-suave'" @click="escolherTipo('saida')">− Saída</button>
                    </div>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Conta
                        <select v-model="form.conta" :class="campo">
                            <option value="banco">Conta bancária</option>
                            <option value="prazo">Conta a prazo</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Descrição *
                        <input v-model="form.descricao" type="text" required placeholder="Ex: Renda do café - Julho" :class="campo" />
                        <span v-if="form.errors.descricao" class="text-[13px] font-semibold text-perigo">{{ form.errors.descricao }}</span>
                    </label>
                    <div class="grid grid-cols-2 gap-2.5">
                        <label class="flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave">Valor (€) *
                            <input v-model="form.valor" type="number" step="0.01" min="0.01" required inputmode="decimal" placeholder="0,00" :class="[campo, 'text-lg font-bold']" />
                            <span v-if="form.errors.valor" class="text-[13px] font-semibold text-perigo">{{ form.errors.valor }}</span>
                        </label>
                        <label class="flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave">Data *
                            <input v-model="form.data" type="date" required :class="[campo, 'px-2 text-[15px]']" />
                        </label>
                    </div>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Categoria
                        <select v-model="form.categoria" :class="campo">
                            <option value="">— sem categoria —</option>
                            <option v-for="c in categoriasParaTipo(form.tipo)" :key="c.valor" :value="c.valor">{{ c.label }}</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Referência
                        <input v-model="form.referencia" type="text" placeholder="Nº documento, fatura..." :class="campo" />
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">Notas
                        <textarea v-model="form.notas" rows="2" placeholder="Observações adicionais..." class="w-full resize-y rounded-[10px] border border-linha-forte bg-white px-3 py-2.5 text-base text-tinta focus:border-verde focus:ring-verde" />
                    </label>
                    <div v-if="Object.keys(form.errors).filter((k) => !['descricao', 'valor'].includes(k)).length" role="alert" class="rounded-[10px] bg-perigo-claro px-3 py-2 text-sm font-semibold text-perigo-texto">
                        <div v-for="(erro, chave) in form.errors" v-show="!['descricao', 'valor'].includes(chave)" :key="chave">{{ erro }}</div>
                    </div>
                    <button type="submit" :disabled="form.processing" class="h-[52px] rounded-[10px] text-base font-extrabold text-white disabled:opacity-50" :class="form.tipo === 'entrada' ? 'bg-verde hover:bg-verde-escuro' : 'bg-perigo hover:brightness-95'">
                        <span v-if="form.processing">A guardar...</span>
                        <span v-else-if="form.tipo === 'entrada'">+ Registar entrada</span>
                        <span v-else>− Registar saída</span>
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
