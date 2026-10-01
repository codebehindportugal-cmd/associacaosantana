<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    filters: Object,
    custos: Array,
    receitas: Array,
    movimentos: Array,
    resumo: Object,
    categoriasCusto: Array,
    categoriasReceita: Array,
    associacoes: Array,
    divisao: Object,
    linkPartilhado: String,
});

const filtros = reactive({ ...props.filters });
const edicaoId = ref(null);

// NOTA: o campo chama-se data_movimento no frontend porque "data"
// colide com o metodo interno form.data() do Inertia (bug silencioso).
// O transform() converte para "data" antes de enviar ao servidor.
const paraServidor = (dados) => {
    const { data_movimento, ...resto } = dados;
    return { ...resto, data: data_movimento };
};

const form = useForm({
    tipo: 'custo',
    categoria: props.categoriasCusto?.[0]?.valor ?? 'outros',
    descricao: '',
    data_movimento: new Date().toISOString().slice(0, 10),
    valor: '',
    observacoes: '',
});

const editForm = useForm({
    tipo: 'custo',
    categoria: 'outros',
    descricao: '',
    data_movimento: '',
    valor: '',
    observacoes: '',
});

const euros = (valor) => Number(valor || 0).toLocaleString('pt-PT', {
    style: 'currency',
    currency: 'EUR',
});

const filtrar = () => router.get(route('contas-festa.index'), filtros, {
    preserveState: true,
    preserveScroll: true,
});

const iso = (d) => d.toISOString().slice(0, 10);

const aplicarPeriodo = (periodo) => {
    const hoje = new Date();
    let inicio = new Date(hoje);
    let fim = new Date(hoje);

    if (periodo === 'semana') {
        const diaSemana = (hoje.getDay() + 6) % 7; // segunda = 0
        inicio.setDate(hoje.getDate() - diaSemana);
        fim = new Date(inicio);
        fim.setDate(inicio.getDate() + 6);
    } else if (periodo === 'mes') {
        inicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
        fim = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
    } else if (periodo === 'trimestre') {
        const trimestre = Math.floor(hoje.getMonth() / 3);
        inicio = new Date(hoje.getFullYear(), trimestre * 3, 1);
        fim = new Date(hoje.getFullYear(), trimestre * 3 + 3, 0);
    } else if (periodo === 'ano') {
        inicio = new Date(hoje.getFullYear(), 0, 1);
        fim = new Date(hoje.getFullYear(), 11, 31);
    }

    filtros.data_inicio = iso(inicio);
    filtros.data_fim = iso(fim);
    filtrar();
};

const periodos = [
    { valor: 'hoje', label: 'Hoje' },
    { valor: 'semana', label: 'Semana' },
    { valor: 'mes', label: 'Mês' },
    { valor: 'trimestre', label: 'Trimestre' },
    { valor: 'ano', label: 'Ano' },
];

const categoriasParaTipo = (tipo) => tipo === 'receita' ? props.categoriasReceita : props.categoriasCusto;

const ajustarCategoria = (formulario) => {
    const categorias = categoriasParaTipo(formulario.tipo);
    if (!categorias?.some((categoria) => categoria.valor === formulario.categoria)) {
        formulario.categoria = categorias?.[0]?.valor ?? 'outros';
    }
};

const criarMovimento = () => {
    form.transform(paraServidor).post(route('contas-festa.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('descricao', 'valor', 'observacoes');
            form.data_movimento = new Date().toISOString().slice(0, 10);
        },
    });
};

const editar = (movimento) => {
    edicaoId.value = movimento.id;
    editForm.clearErrors();
    editForm.tipo = movimento.tipo;
    editForm.categoria = movimento.categoria;
    editForm.descricao = movimento.descricao;
    editForm.data_movimento = movimento.data ? String(movimento.data).slice(0, 10) : '';
    editForm.valor = movimento.valor;
    editForm.observacoes = movimento.observacoes || '';
};

const guardarEdicao = (movimento) => {
    // POST com _method=put: evita bloqueios de PUT no servidor
    editForm.transform((dados) => ({ ...paraServidor(dados), _method: 'put' }))
        .post(route('contas-festa.update', movimento.id), {
            preserveScroll: true,
            onSuccess: () => {
                edicaoId.value = null;
            },
        });
};

const apagar = (movimento) => {
    if (confirm('Apagar "' + movimento.descricao + '"?')) {
        router.post(route('contas-festa.destroy', movimento.id), { _method: 'delete' }, { preserveScroll: true });
    }
};

const dataCurta = (data) => data ? new Date(String(data).slice(0, 10) + 'T00:00:00').toLocaleDateString('pt-PT') : '-';

// ---------------------------------------------------------------------------
// Associacoes que partilham o evento: a receita BRUTA e dividida pelas
// percentagens acordadas; cada associacao suporta os seus proprios custos.
// ---------------------------------------------------------------------------
const edicaoAssocId = ref(null);
const linkCopiado = ref(false);

const percentagemFmt = (valor) => Number(valor || 0).toLocaleString('pt-PT', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
}) + '%';

const assocVazia = {
    nome: '',
    sigla: '',
    percentagem: '',
    responsavel: '',
    telefone: '',
    email: '',
};

const assocForm = useForm({ ...assocVazia });
const assocEditForm = useForm({ ...assocVazia, ativo: true, ordem: 0 });

const criarAssociacao = () => {
    assocForm.post(route('contas-festa.associacoes.store'), {
        preserveScroll: true,
        onSuccess: () => assocForm.reset(),
    });
};

const editarAssociacao = (associacao) => {
    edicaoAssocId.value = associacao.id;
    assocEditForm.clearErrors();
    assocEditForm.nome = associacao.nome;
    assocEditForm.sigla = associacao.sigla || '';
    assocEditForm.percentagem = associacao.percentagem;
    assocEditForm.responsavel = associacao.responsavel || '';
    assocEditForm.telefone = associacao.telefone || '';
    assocEditForm.email = associacao.email || '';
    assocEditForm.ativo = associacao.ativo;
    assocEditForm.ordem = associacao.ordem;
};

const guardarAssociacao = (associacao) => {
    assocEditForm.transform((dados) => ({ ...dados, _method: 'put' }))
        .post(route('contas-festa.associacoes.update', associacao.id), {
            preserveScroll: true,
            onSuccess: () => {
                edicaoAssocId.value = null;
            },
        });
};

const apagarAssociacao = (associacao) => {
    if (confirm('Remover "' + associacao.nome + '" da divisao?')) {
        router.post(route('contas-festa.associacoes.destroy', associacao.id), { _method: 'delete' }, { preserveScroll: true });
    }
};

const igualarPercentagens = () => {
    if (confirm('Dividir 100% em partes iguais por todas as associacoes ativas?')) {
        router.post(route('contas-festa.associacoes.igualar'), {}, { preserveScroll: true });
    }
};

const gerarLink = () => {
    const aviso = props.linkPartilhado
        ? 'Gerar um link novo invalida o que ja foi distribuido. Continuar?'
        : null;

    if (aviso && !confirm(aviso)) return;

    router.post(route('contas-festa.link.gerar'), {}, { preserveScroll: true });
};

const desativarLink = () => {
    if (confirm('Desativar o link? As outras associacoes deixam de ver as contas.')) {
        router.post(route('contas-festa.link.apagar'), { _method: 'delete' }, { preserveScroll: true });
    }
};

const copiarLink = async () => {
    try {
        await navigator.clipboard.writeText(props.linkPartilhado);
        linkCopiado.value = true;
        setTimeout(() => { linkCopiado.value = false; }, 2500);
    } catch (e) {
        linkCopiado.value = false;
    }
};

// --- Só para a apresentação ---
const periodoAtivo = ref(null);
const escolherPeriodo = (periodo) => {
    periodoAtivo.value = periodo;
    aplicarPeriodo(periodo);
};
const labelCategoria = (tipo, valor) => (categoriasParaTipo(tipo) ?? []).find((c) => c.valor === valor)?.label ?? valor;
const quotaParte = (associacao) => (props.divisao?.linhas ?? []).find((l) => l.id === associacao.id)?.valor;
const comSinal = (v) => (Number(v) > 0 ? '+' : '') + euros(v);
const nomesCampo = { nome: 'Nome', sigla: 'Sigla', percentagem: 'Percentagem', responsavel: 'Responsável', telefone: 'Telefone', email: 'Email', tipo: 'Tipo', categoria: 'Categoria', descricao: 'Descrição', data: 'Data', valor: 'Valor', observacoes: 'Observações' };
const nomeCampo = (campo) => nomesCampo[campo] ?? campo;
const campo = 'h-12 w-full min-w-0 rounded-[10px] border border-linha-forte bg-white px-3 text-[15px] text-tinta focus:border-verde focus:ring-verde';
const campoPequeno = 'h-11 w-full min-w-0 rounded-[10px] border border-linha-forte bg-white px-3 text-[15px] text-tinta focus:border-verde focus:ring-verde';
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-[30px] font-extrabold leading-tight">Contas da festa</h1>
                    <p class="text-[15px] text-suave">Custos, aquisições, vendas e resultado final.</p>
                </div>
                <a href="#novo-mov" class="inline-flex h-12 items-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white hover:bg-verde-escuro">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                    Adicionar movimento
                </a>
            </div>

            <!-- Período -->
            <section aria-label="Período" class="flex flex-wrap items-end gap-3 rounded-[14px] border border-linha bg-white px-4 py-3.5">
                <div role="group" aria-label="Período rápido" class="flex flex-[1_1_380px] flex-wrap gap-2">
                    <button
                        v-for="periodo in periodos"
                        :key="periodo.valor"
                        type="button"
                        :aria-pressed="periodoAtivo === periodo.valor"
                        class="h-11 rounded-full px-4 text-sm font-bold"
                        :class="periodoAtivo === periodo.valor ? 'bg-tinta text-white' : 'border border-linha-forte bg-white text-tinta hover:bg-fundo'"
                        @click="escolherPeriodo(periodo.valor)"
                    >
                        {{ periodo.label }}
                    </button>
                </div>
                <form class="grid w-full grid-cols-2 items-end gap-2 sm:flex sm:w-auto sm:flex-wrap" @submit.prevent="periodoAtivo = null; filtrar()">
                    <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">De
                        <input v-model="filtros.data_inicio" type="date" :class="[campoPequeno, 'px-2.5']">
                    </label>
                    <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Até
                        <input v-model="filtros.data_fim" type="date" :class="[campoPequeno, 'px-2.5']">
                    </label>
                    <button class="col-span-2 h-11 rounded-[10px] bg-escuro-2 px-[18px] text-sm font-bold text-white hover:bg-escuro">Filtrar</button>
                </form>
            </section>

            <!-- Resumo -->
            <section aria-label="Resumo" class="grid gap-3 md:grid-cols-3">
                <div class="rounded-[14px] border border-linha bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">Total receitas</div>
                    <strong class="text-[30px] font-extrabold text-verde">{{ euros(resumo.total_receitas) }}</strong>
                </div>
                <div class="rounded-[14px] border border-linha bg-white p-[18px]">
                    <div class="text-sm font-bold text-suave-2">Total custos</div>
                    <strong class="text-[30px] font-extrabold text-perigo">{{ euros(resumo.total_custos) }}</strong>
                </div>
                <div class="rounded-[14px] p-[18px] text-white" :class="Number(resumo.resultado) >= 0 ? 'bg-verde' : 'bg-perigo'">
                    <div class="text-sm font-bold" :class="Number(resumo.resultado) >= 0 ? 'text-verde-claro2' : 'text-perigo-claro'">Contas feitas (resultado)</div>
                    <strong class="text-[30px] font-extrabold">{{ comSinal(resumo.resultado) }}</strong>
                </div>
            </section>

            <!-- Custos / Receitas -->
            <div class="grid items-start gap-5 lg:grid-cols-2">
                <section class="rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="mb-2 text-[19px] font-extrabold">Compras e aquisições</h2>
                    <div v-if="!custos?.length" class="border-t border-linha-fraca py-3 text-suave">Sem custos neste período.</div>
                    <div v-for="linha in custos" :key="linha.categoria + '-' + linha.origem" class="flex justify-between gap-3 border-t border-linha-fraca py-3 text-base">
                        <span class="font-semibold">{{ linha.label }}<span v-if="linha.origem === 'automatico'" class="ml-1.5 whitespace-nowrap rounded-full bg-linha-fraca px-2 py-0.5 text-xs font-bold text-suave-2">automático</span></span>
                        <strong class="whitespace-nowrap text-perigo">{{ euros(linha.valor) }}</strong>
                    </div>
                </section>
                <section class="rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                    <h2 class="mb-2 text-[19px] font-extrabold">Vendas e receitas</h2>
                    <div v-if="!receitas?.length" class="border-t border-linha-fraca py-3 text-suave">Sem receitas neste período.</div>
                    <div v-for="linha in receitas" :key="linha.categoria + '-' + linha.origem" class="flex justify-between gap-3 border-t border-linha-fraca py-3 text-base">
                        <span class="font-semibold">{{ linha.label }}</span>
                        <strong class="whitespace-nowrap text-verde">{{ euros(linha.valor) }}</strong>
                    </div>
                </section>
            </div>

            <!-- Associações participantes -->
            <section class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="flex flex-wrap items-start justify-between gap-3 px-5 py-[18px]">
                    <div class="flex max-w-[640px] flex-col gap-1">
                        <h2 class="text-[19px] font-extrabold">Associações participantes</h2>
                        <p class="text-[15px] text-suave">Divisão da <strong class="text-tinta">receita bruta</strong> ({{ euros(divisao.receita_bruta) }}). Cada associação suporta os seus próprios custos.</p>
                    </div>
                    <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-4 text-sm font-bold text-tinta hover:bg-fundo" @click="igualarPercentagens">Igualar percentagens</button>
                </div>

                <div v-if="!divisao.percentagens_ok" role="status" class="mx-5 mb-3.5 flex items-center gap-2.5 rounded-[10px] bg-laranja-claro px-3.5 py-3 text-[15px] font-semibold text-laranja-texto">
                    <svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l10 18H2z" /><path d="M12 10v5M12 18v.01" /></svg>
                    As percentagens somam {{ percentagemFmt(divisao.soma_percentagens) }} e não 100%. Os valores abaixo estão provisórios.
                </div>

                <!-- Tabela (ecrãs largos) -->
                <table class="hidden w-full border-collapse text-base md:table">
                    <thead>
                        <tr class="text-left text-[13px] text-suave-2">
                            <th scope="col" class="border-b border-linha-fraca px-5 py-3 font-semibold">Associação</th>
                            <th scope="col" class="border-b border-linha-fraca p-3 font-semibold">Responsável</th>
                            <th scope="col" class="border-b border-linha-fraca p-3 text-right font-semibold">%</th>
                            <th scope="col" class="border-b border-linha-fraca p-3 text-right font-semibold">Quota-parte</th>
                            <th scope="col" class="border-b border-linha-fraca px-5 py-3 text-right font-semibold">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="associacao in associacoes" :key="associacao.id" class="border-b border-linha-fraca" :class="edicaoAssocId === associacao.id ? 'bg-fundo' : ''">
                            <template v-if="edicaoAssocId === associacao.id">
                                <td class="px-5 py-2.5">
                                    <div class="flex flex-wrap gap-1.5">
                                        <input v-model="assocEditForm.nome" aria-label="Nome" class="h-10 w-[190px] rounded-lg border border-linha-forte px-2.5 text-[15px] focus:border-verde focus:ring-verde">
                                        <input v-model="assocEditForm.sigla" aria-label="Sigla" placeholder="Sigla" class="h-10 w-[70px] rounded-lg border border-linha-forte px-2.5 text-sm focus:border-verde focus:ring-verde">
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex flex-col gap-1.5">
                                        <input v-model="assocEditForm.responsavel" aria-label="Responsável" placeholder="Nome" class="h-10 w-[150px] rounded-lg border border-linha-forte px-2.5 text-[15px] focus:border-verde focus:ring-verde">
                                        <input v-model="assocEditForm.telefone" aria-label="Telefone" placeholder="Telefone" class="h-10 w-[150px] rounded-lg border border-linha-forte px-2.5 text-sm focus:border-verde focus:ring-verde">
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <input v-model="assocEditForm.percentagem" aria-label="Percentagem" type="number" min="0" max="100" step="0.01" inputmode="decimal" class="h-10 w-20 rounded-lg border border-linha-forte px-2.5 text-right text-[15px] focus:border-verde focus:ring-verde">
                                </td>
                                <td class="px-3 py-2.5 text-right text-suave-2">—</td>
                                <td class="whitespace-nowrap px-5 py-2.5 text-right">
                                    <button type="button" class="h-10 rounded-[10px] bg-verde px-3.5 text-sm font-bold text-white hover:bg-verde-escuro" @click="guardarAssociacao(associacao)">Guardar</button>
                                    <button type="button" class="ml-1.5 h-10 rounded-[10px] border border-linha-forte bg-white px-3 text-sm font-semibold text-suave" @click="edicaoAssocId = null">Cancelar</button>
                                </td>
                            </template>
                            <template v-else>
                                <td class="px-5 py-3">
                                    <strong>{{ associacao.nome }}</strong>
                                    <span v-if="associacao.sigla" class="ml-1 text-[13px] text-suave-2">({{ associacao.sigla }})</span>
                                    <div v-if="!associacao.ativo" class="text-xs font-bold text-suave-2">inativa</div>
                                </td>
                                <td class="p-3 text-suave">
                                    {{ associacao.responsavel || '—' }}
                                    <div v-if="associacao.telefone" class="text-[13px] text-suave-2">{{ associacao.telefone }}</div>
                                </td>
                                <td class="p-3 text-right font-bold">{{ percentagemFmt(associacao.percentagem) }}</td>
                                <td class="whitespace-nowrap p-3 text-right font-extrabold text-verde">{{ euros(quotaParte(associacao)) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-right">
                                    <button type="button" class="h-10 rounded-[10px] border border-linha-forte bg-white px-3 text-sm font-semibold text-tinta hover:bg-fundo" @click="editarAssociacao(associacao)">Editar</button>
                                    <button type="button" class="ml-1.5 h-10 rounded-[10px] border border-[#F0D3CD] bg-white px-3 text-sm font-semibold text-perigo hover:bg-perigo-claro" @click="apagarAssociacao(associacao)">Remover</button>
                                </td>
                            </template>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-linha">
                            <td colspan="2" class="px-5 py-3 font-extrabold">Atribuído</td>
                            <td class="p-3 text-right font-bold">{{ percentagemFmt(divisao.soma_percentagens) }}</td>
                            <td class="whitespace-nowrap p-3 text-right font-extrabold">{{ euros(divisao.atribuido) }}</td>
                            <td></td>
                        </tr>
                        <tr v-if="Math.abs(Number(divisao.residuo)) >= 0.01">
                            <td colspan="3" class="px-5 pb-3.5 pt-1 text-[13px] text-suave-2">Por atribuir / arredondamento</td>
                            <td class="whitespace-nowrap px-3 pb-3.5 pt-1 text-right text-[13px] font-bold text-suave-2">{{ euros(divisao.residuo) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <!-- Cartões (ecrãs estreitos) -->
                <ul class="divide-y divide-linha-fraca border-t border-linha-fraca md:hidden">
                    <li v-for="associacao in associacoes" :key="associacao.id" class="flex flex-col gap-2 px-4 py-3" :class="edicaoAssocId === associacao.id ? 'bg-fundo' : ''">
                        <template v-if="edicaoAssocId === associacao.id">
                            <div class="grid grid-cols-[minmax(0,1fr)_80px] gap-2">
                                <input v-model="assocEditForm.nome" aria-label="Nome" :class="campoPequeno">
                                <input v-model="assocEditForm.sigla" aria-label="Sigla" placeholder="Sigla" :class="campoPequeno">
                                <input v-model="assocEditForm.responsavel" aria-label="Responsável" placeholder="Responsável" :class="campoPequeno">
                                <input v-model="assocEditForm.percentagem" aria-label="Percentagem" type="number" min="0" max="100" step="0.01" inputmode="decimal" :class="[campoPequeno, 'text-right']">
                                <input v-model="assocEditForm.telefone" aria-label="Telefone" placeholder="Telefone" :class="[campoPequeno, 'col-span-2']">
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="h-11 flex-1 rounded-[10px] bg-verde text-sm font-bold text-white" @click="guardarAssociacao(associacao)">Guardar</button>
                                <button type="button" class="h-11 flex-1 rounded-[10px] border border-linha-forte bg-white text-sm font-semibold text-suave" @click="edicaoAssocId = null">Cancelar</button>
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <strong class="break-words">{{ associacao.nome }}</strong>
                                    <span v-if="associacao.sigla" class="ml-1 text-[13px] text-suave-2">({{ associacao.sigla }})</span>
                                    <div v-if="!associacao.ativo" class="text-xs font-bold text-suave-2">inativa</div>
                                    <div class="text-sm text-suave">{{ associacao.responsavel || '—' }}<template v-if="associacao.telefone"> · {{ associacao.telefone }}</template></div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="font-bold">{{ percentagemFmt(associacao.percentagem) }}</div>
                                    <div class="font-extrabold text-verde">{{ euros(quotaParte(associacao)) }}</div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="h-11 flex-1 rounded-[10px] border border-linha-forte bg-white text-sm font-semibold text-tinta" @click="editarAssociacao(associacao)">Editar</button>
                                <button type="button" class="h-11 flex-1 rounded-[10px] border border-[#F0D3CD] bg-white text-sm font-semibold text-perigo" @click="apagarAssociacao(associacao)">Remover</button>
                            </div>
                        </template>
                    </li>
                    <li class="flex justify-between gap-3 px-4 py-3 font-extrabold">
                        <span>Atribuído · {{ percentagemFmt(divisao.soma_percentagens) }}</span>
                        <span class="whitespace-nowrap">{{ euros(divisao.atribuido) }}</span>
                    </li>
                    <li v-if="Math.abs(Number(divisao.residuo)) >= 0.01" class="flex justify-between gap-3 px-4 py-2 text-[13px] text-suave-2">
                        <span>Por atribuir / arredondamento</span>
                        <strong class="whitespace-nowrap">{{ euros(divisao.residuo) }}</strong>
                    </li>
                </ul>

                <form class="flex flex-col gap-2.5 border-t border-linha-fraca px-5 py-4" @submit.prevent="criarAssociacao">
                    <span class="text-sm font-extrabold">Adicionar associação</span>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_2fr_1.5fr_auto]">
                        <input v-model="assocForm.nome" required aria-label="Nome da associação" placeholder="Nome da associação" :class="campoPequeno">
                        <input v-model="assocForm.sigla" aria-label="Sigla" placeholder="Sigla" :class="campoPequeno">
                        <input v-model="assocForm.percentagem" required type="number" min="0" max="100" step="0.01" inputmode="decimal" aria-label="Percentagem" placeholder="%" :class="campoPequeno">
                        <input v-model="assocForm.responsavel" aria-label="Responsável" placeholder="Responsável" :class="campoPequeno">
                        <input v-model="assocForm.telefone" aria-label="Telefone" placeholder="Telefone" :class="campoPequeno">
                        <button class="h-11 rounded-[10px] bg-escuro-2 px-[18px] text-sm font-bold text-white hover:bg-escuro disabled:opacity-60" :disabled="assocForm.processing">
                            {{ assocForm.processing ? 'A guardar...' : 'Adicionar' }}
                        </button>
                    </div>
                    <div v-if="Object.keys(assocForm.errors).length || Object.keys(assocEditForm.errors).length" role="alert" class="rounded-[10px] bg-perigo-claro px-3.5 py-2.5 text-sm text-perigo-texto">
                        <div v-for="(erro, nome) in { ...assocForm.errors, ...assocEditForm.errors }" :key="nome"><strong>{{ nomeCampo(nome) }}:</strong> {{ erro }}</div>
                    </div>
                </form>

                <div class="mx-5 mb-5 flex flex-col gap-2.5 rounded-xl bg-fundo p-4">
                    <div class="flex flex-col gap-0.5">
                        <strong class="text-[15px]">Link para as outras associações</strong>
                        <span class="text-sm text-suave-2">Página de consulta, sem login, só com receita e divisão. Não mostra custos internos.</span>
                    </div>
                    <div v-if="linkPartilhado" class="flex flex-wrap items-center gap-2">
                        <label class="flex min-w-0 flex-[1_1_280px]">
                            <span class="sr-only">Link partilhado</span>
                            <input :value="linkPartilhado" readonly class="h-11 min-w-0 flex-1 rounded-[10px] border border-linha-forte bg-white px-3 text-sm text-suave focus:border-verde focus:ring-verde">
                        </label>
                        <button type="button" class="h-11 rounded-[10px] bg-verde px-4 text-sm font-bold text-white hover:bg-verde-escuro" @click="copiarLink">{{ linkCopiado ? 'Copiado' : 'Copiar' }}</button>
                        <a :href="linkPartilhado" target="_blank" rel="noopener" class="inline-flex h-11 items-center rounded-[10px] border border-linha-forte bg-white px-4 text-sm font-bold text-tinta hover:bg-fundo">Abrir</a>
                        <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-4 text-sm font-bold text-laranja-texto hover:bg-laranja-claro" @click="gerarLink">Gerar novo</button>
                        <button type="button" class="h-11 rounded-[10px] border border-[#F0D3CD] bg-white px-4 text-sm font-bold text-perigo hover:bg-perigo-claro" @click="desativarLink">Desativar</button>
                    </div>
                    <button v-else type="button" class="h-11 self-start rounded-[10px] bg-verde px-4 text-sm font-bold text-white hover:bg-verde-escuro" @click="gerarLink">Gerar link partilhado</button>
                </div>
            </section>

            <!-- Adicionar movimento -->
            <section id="novo-mov" class="flex scroll-mt-6 flex-col gap-3 rounded-[14px] border border-linha bg-white px-5 py-[18px]">
                <h2 class="text-[19px] font-extrabold">Adicionar movimento</h2>
                <form class="flex flex-col gap-2.5" @submit.prevent="criarMovimento">
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-[150px_190px_minmax(0,1fr)_160px_140px]">
                        <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Tipo
                            <select v-model="form.tipo" :class="campo" @change="ajustarCategoria(form)">
                                <option value="custo">Custo</option>
                                <option value="receita">Receita</option>
                            </select>
                        </label>
                        <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Categoria
                            <select v-model="form.categoria" :class="campo">
                                <option v-for="categoria in categoriasParaTipo(form.tipo)" :key="categoria.valor" :value="categoria.valor">{{ categoria.label }}</option>
                            </select>
                        </label>
                        <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave sm:col-span-2 lg:col-span-1">Descrição
                            <input v-model="form.descricao" required placeholder="Ex.: Banda X, luz, seguro, patrocinador" :class="campo">
                        </label>
                        <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Data
                            <input v-model="form.data_movimento" type="date" :class="[campo, 'px-2']">
                        </label>
                        <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Valor (€)
                            <input v-model="form.valor" required type="number" min="0" step="0.01" inputmode="decimal" placeholder="0,00" :class="[campo, 'text-[17px] font-bold']">
                        </label>
                    </div>
                    <label class="flex flex-col gap-1 text-[13px] font-semibold text-suave">Observações
                        <textarea v-model="form.observacoes" rows="2" placeholder="Opcional" class="w-full resize-y rounded-[10px] border border-linha-forte px-3 py-2.5 text-[15px] text-tinta focus:border-verde focus:ring-verde"></textarea>
                    </label>
                    <div v-if="Object.keys(form.errors).length" role="alert" class="rounded-[10px] bg-perigo-claro px-3.5 py-2.5 text-sm text-perigo-texto">
                        <div v-for="(erro, nome) in form.errors" :key="nome"><strong>{{ nomeCampo(nome) }}:</strong> {{ erro }}</div>
                    </div>
                    <div class="flex justify-end">
                        <button class="h-12 rounded-[10px] bg-verde px-6 text-[15px] font-extrabold text-white hover:bg-verde-escuro disabled:opacity-60" :disabled="form.processing">{{ form.processing ? 'A guardar...' : 'Adicionar' }}</button>
                    </div>
                </form>
            </section>

            <!-- Lançamentos manuais -->
            <section class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="border-b border-linha-fraca px-5 py-4">
                    <h2 class="text-[19px] font-extrabold">Lançamentos manuais</h2>
                </div>
                <div v-if="Object.keys(editForm.errors).length" role="alert" class="mx-5 mt-3 rounded-[10px] bg-perigo-claro px-3.5 py-2.5 text-sm text-perigo-texto">
                    <div v-for="(erro, nome) in editForm.errors" :key="nome"><strong>{{ nomeCampo(nome) }}:</strong> {{ erro }}</div>
                </div>
                <div v-if="!movimentos.length" class="p-8 text-center font-bold text-suave">
                    Ainda não há movimentos manuais.
                </div>
                <ul v-else>
                    <li v-for="movimento in movimentos" :key="movimento.id" class="border-b border-linha-fraca last:border-0">
                        <!-- Edição -->
                        <div v-if="edicaoId === movimento.id" class="flex flex-col gap-2.5 bg-fundo px-4 py-4 sm:px-5">
                            <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-[150px_130px_180px_minmax(0,1fr)_130px]">
                                <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Data
                                    <input v-model="editForm.data_movimento" type="date" :class="[campoPequeno, 'px-2']">
                                </label>
                                <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Tipo
                                    <select v-model="editForm.tipo" :class="campoPequeno" @change="ajustarCategoria(editForm)">
                                        <option value="custo">Custo</option>
                                        <option value="receita">Receita</option>
                                    </select>
                                </label>
                                <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Categoria
                                    <select v-model="editForm.categoria" :class="campoPequeno">
                                        <option v-for="categoria in categoriasParaTipo(editForm.tipo)" :key="categoria.valor" :value="categoria.valor">{{ categoria.label }}</option>
                                    </select>
                                </label>
                                <label class="flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave">Descrição
                                    <input v-model="editForm.descricao" :class="campoPequeno">
                                </label>
                                <label class="col-span-2 flex min-w-0 flex-col gap-1 text-[13px] font-semibold text-suave lg:col-span-1">Valor (€)
                                    <input v-model="editForm.valor" type="number" min="0" step="0.01" inputmode="decimal" :class="[campoPequeno, 'text-right']">
                                </label>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-4 text-sm font-semibold text-suave" @click="edicaoId = null">Cancelar</button>
                                <button type="button" class="h-11 rounded-[10px] bg-verde px-5 text-sm font-bold text-white hover:bg-verde-escuro disabled:opacity-60" :disabled="editForm.processing" @click="guardarEdicao(movimento)">Guardar</button>
                            </div>
                        </div>
                        <!-- Linha -->
                        <div v-else class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5 px-4 py-3 sm:px-5 md:grid-cols-[100px_100px_160px_minmax(0,1fr)_120px_auto]">
                            <span class="order-3 whitespace-nowrap text-[13px] text-suave-2 md:order-none md:text-base md:text-tinta">{{ dataCurta(movimento.data) }}</span>
                            <span class="order-4 justify-self-end md:order-none md:justify-self-start">
                                <span class="inline-flex h-[26px] items-center rounded-full px-2.5 text-[13px] font-extrabold" :class="movimento.tipo === 'receita' ? 'bg-verde-claro text-verde-escuro' : 'bg-perigo-claro text-perigo-texto'">{{ movimento.tipo === 'receita' ? 'Receita' : 'Custo' }}</span>
                            </span>
                            <span class="order-5 col-span-2 text-sm text-suave md:order-none md:col-span-1 md:text-base">{{ labelCategoria(movimento.tipo, movimento.categoria) }}</span>
                            <div class="order-1 min-w-0 md:order-none">
                                <strong class="break-words">{{ movimento.descricao }}</strong>
                                <div v-if="movimento.observacoes" class="text-[13px] text-suave-2">{{ movimento.observacoes }}</div>
                            </div>
                            <strong class="order-2 whitespace-nowrap text-right md:order-none">{{ euros(movimento.valor) }}</strong>
                            <div class="order-6 col-span-2 flex justify-end gap-1.5 md:order-none md:col-span-1">
                                <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-3 text-sm font-semibold text-tinta hover:bg-fundo md:h-10" @click="editar(movimento)">Editar</button>
                                <button type="button" :aria-label="`Apagar ${movimento.descricao}`" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-[#F0D3CD] bg-white text-perigo hover:bg-perigo-claro md:h-10 md:w-10" @click="apagar(movimento)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
