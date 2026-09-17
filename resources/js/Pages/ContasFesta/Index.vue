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
    { valor: 'mes', label: 'Mes' },
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
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black">Contas da Festa</h1>
                <p class="mt-1 text-sm text-slate-500">Custos, aquisicoes, vendas e resultado final.</p>
            </div>
            <div class="rounded-lg bg-white p-3 shadow-sm">
                <div class="mb-2 flex flex-wrap gap-2">
                    <button
                        v-for="periodo in periodos"
                        :key="periodo.valor"
                        type="button"
                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100"
                        @click="aplicarPeriodo(periodo.valor)"
                    >
                        {{ periodo.label }}
                    </button>
                </div>
                <form class="grid gap-2 sm:grid-cols-[150px_150px_auto]" @submit.prevent="filtrar">
                    <input v-model="filtros.data_inicio" type="date" class="rounded-md border-slate-300 text-sm">
                    <input v-model="filtros.data_fim" type="date" class="rounded-md border-slate-300 text-sm">
                    <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white">Filtrar</button>
                </form>
            </div>
        </div>

        <section class="mb-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <div class="text-sm font-bold text-slate-500">Total receitas</div>
                <div class="mt-2 text-3xl font-black text-emerald-700">{{ euros(resumo.total_receitas) }}</div>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <div class="text-sm font-bold text-slate-500">Total custos</div>
                <div class="mt-2 text-3xl font-black text-red-700">{{ euros(resumo.total_custos) }}</div>
            </div>
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <div class="text-sm font-bold text-slate-500">Contas feitas</div>
                <div class="mt-2 text-3xl font-black" :class="Number(resumo.resultado) >= 0 ? 'text-emerald-700' : 'text-red-700'">{{ euros(resumo.resultado) }}</div>
            </div>
        </section>

        <section class="mb-6 rounded-lg bg-white p-5 shadow-sm">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black">Associacoes participantes</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Divisao da <strong>receita bruta</strong> ({{ euros(divisao.receita_bruta) }}).
                        Cada associacao suporta os seus proprios custos.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                    @click="igualarPercentagens"
                >
                    Igualar percentagens
                </button>
            </div>

            <div v-if="!divisao.percentagens_ok" class="mb-4 rounded-md bg-amber-50 p-3 text-sm font-bold text-amber-800">
                As percentagens somam {{ percentagemFmt(divisao.soma_percentagens) }} e nao 100%.
                Os valores abaixo estao provisorios.
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2">Associacao</th>
                            <th>Responsavel</th>
                            <th class="text-right">%</th>
                            <th class="text-right">Quota-parte</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="associacao in associacoes" :key="associacao.id" class="border-t border-slate-100">
                            <template v-if="edicaoAssocId === associacao.id">
                                <td class="py-2">
                                    <input v-model="assocEditForm.nome" class="w-48 rounded-md border-slate-300 text-sm">
                                    <input v-model="assocEditForm.sigla" class="mt-1 w-24 rounded-md border-slate-300 text-xs" placeholder="Sigla">
                                </td>
                                <td>
                                    <input v-model="assocEditForm.responsavel" class="w-40 rounded-md border-slate-300 text-sm" placeholder="Nome">
                                    <input v-model="assocEditForm.telefone" class="mt-1 w-32 rounded-md border-slate-300 text-xs" placeholder="Telefone">
                                </td>
                                <td class="text-right">
                                    <input v-model="assocEditForm.percentagem" type="number" min="0" max="100" step="0.01" class="w-20 rounded-md border-slate-300 text-right text-sm">
                                </td>
                                <td class="text-right text-slate-400">-</td>
                                <td class="text-right">
                                    <button type="button" class="font-bold text-emerald-700" @click="guardarAssociacao(associacao)">Guardar</button>
                                    <button type="button" class="ml-3 font-bold text-slate-500" @click="edicaoAssocId = null">Cancelar</button>
                                </td>
                            </template>
                            <template v-else>
                                <td class="py-3">
                                    <strong>{{ associacao.nome }}</strong>
                                    <span v-if="associacao.sigla" class="ml-1 text-xs text-slate-500">({{ associacao.sigla }})</span>
                                    <div v-if="!associacao.ativo" class="text-xs font-bold text-slate-400">inativa</div>
                                </td>
                                <td class="text-slate-600">
                                    {{ associacao.responsavel || '-' }}
                                    <div v-if="associacao.telefone" class="text-xs text-slate-500">{{ associacao.telefone }}</div>
                                </td>
                                <td class="text-right font-bold">{{ percentagemFmt(associacao.percentagem) }}</td>
                                <td class="text-right font-black text-emerald-700">
                                    {{ euros((divisao.linhas.find((l) => l.id === associacao.id) || {}).valor) }}
                                </td>
                                <td class="text-right">
                                    <button type="button" class="font-bold text-amber-700" @click="editarAssociacao(associacao)">Editar</button>
                                    <button type="button" class="ml-3 font-bold text-red-700" @click="apagarAssociacao(associacao)">Remover</button>
                                </td>
                            </template>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-slate-200">
                            <td colspan="2" class="py-3 font-black">Atribuido</td>
                            <td class="text-right font-bold">{{ percentagemFmt(divisao.soma_percentagens) }}</td>
                            <td class="text-right font-black">{{ euros(divisao.atribuido) }}</td>
                            <td></td>
                        </tr>
                        <tr v-if="Math.abs(Number(divisao.residuo)) >= 0.01">
                            <td colspan="3" class="py-2 text-xs text-slate-500">Por atribuir / arredondamento</td>
                            <td class="text-right text-xs font-bold text-slate-500">{{ euros(divisao.residuo) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <form class="mt-4 grid gap-3 border-t border-slate-100 pt-4 lg:grid-cols-[1fr_110px_100px_1fr_140px_auto]" @submit.prevent="criarAssociacao">
                <input v-model="assocForm.nome" required class="rounded-md border-slate-300 text-sm" placeholder="Nome da associacao">
                <input v-model="assocForm.sigla" class="rounded-md border-slate-300 text-sm" placeholder="Sigla">
                <input v-model="assocForm.percentagem" required type="number" min="0" max="100" step="0.01" class="rounded-md border-slate-300 text-sm" placeholder="%">
                <input v-model="assocForm.responsavel" class="rounded-md border-slate-300 text-sm" placeholder="Responsavel">
                <input v-model="assocForm.telefone" class="rounded-md border-slate-300 text-sm" placeholder="Telefone">
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white" :disabled="assocForm.processing">
                    {{ assocForm.processing ? 'A guardar...' : 'Adicionar' }}
                </button>
            </form>
            <div v-if="Object.keys(assocForm.errors).length || Object.keys(assocEditForm.errors).length" class="mt-2 rounded-md bg-red-50 p-3 text-sm text-red-700">
                <div v-for="(erro, campo) in { ...assocForm.errors, ...assocEditForm.errors }" :key="campo"><strong>{{ campo }}:</strong> {{ erro }}</div>
            </div>

            <div class="mt-5 rounded-lg bg-slate-50 p-4">
                <div class="text-sm font-black text-slate-700">Link para as outras associacoes</div>
                <p class="mt-1 text-xs text-slate-500">
                    Pagina de consulta, sem login, so com receita e divisao. Nao mostra custos internos.
                </p>
                <div v-if="linkPartilhado" class="mt-3 flex flex-wrap items-center gap-2">
                    <input :value="linkPartilhado" readonly class="min-w-0 flex-1 rounded-md border-slate-300 bg-white text-xs">
                    <button type="button" class="rounded-md bg-slate-900 px-3 py-2 text-xs font-bold text-white" @click="copiarLink">
                        {{ linkCopiado ? 'Copiado' : 'Copiar' }}
                    </button>
                    <a :href="linkPartilhado" target="_blank" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700">Abrir</a>
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-amber-700" @click="gerarLink">Gerar novo</button>
                    <button type="button" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-bold text-red-700" @click="desativarLink">Desativar</button>
                </div>
                <button v-else type="button" class="mt-3 rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white" @click="gerarLink">
                    Gerar link partilhado
                </button>
            </div>
        </section>

        <div class="mb-6 grid gap-6 xl:grid-cols-2">
            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-lg font-black">Compras e aquisicoes</h2>
                <div v-for="linha in custos" :key="linha.categoria + '-' + linha.origem" class="flex justify-between border-t border-slate-100 py-3">
                    <span class="font-bold">{{ linha.label }}</span>
                    <strong class="text-red-700">{{ euros(linha.valor) }}</strong>
                </div>
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-lg font-black">Vendas e receitas</h2>
                <div v-for="linha in receitas" :key="linha.categoria + '-' + linha.origem" class="flex justify-between border-t border-slate-100 py-3">
                    <span class="font-bold">{{ linha.label }}</span>
                    <strong class="text-emerald-700">{{ euros(linha.valor) }}</strong>
                </div>
            </section>
        </div>

        <section class="mb-6 rounded-lg bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-black">Adicionar movimento</h2>
            <form class="grid gap-3 lg:grid-cols-[110px_170px_1fr_150px_130px_auto]" @submit.prevent="criarMovimento">
                <select v-model="form.tipo" class="rounded-md border-slate-300 text-sm" @change="ajustarCategoria(form)">
                    <option value="custo">Custo</option>
                    <option value="receita">Receita</option>
                </select>
                <select v-model="form.categoria" class="rounded-md border-slate-300 text-sm">
                    <option v-for="categoria in categoriasParaTipo(form.tipo)" :key="categoria.valor" :value="categoria.valor">
                        {{ categoria.label }}
                    </option>
                </select>
                <input v-model="form.descricao" required class="rounded-md border-slate-300 text-sm" placeholder="Ex.: Banda X, luz, seguro, patrocinador">
                <input v-model="form.data_movimento" type="date" class="rounded-md border-slate-300 text-sm">
                <input v-model="form.valor" required type="number" min="0" step="0.01" class="rounded-md border-slate-300 text-sm" placeholder="Valor">
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white" :disabled="form.processing">{{ form.processing ? 'A guardar...' : 'Adicionar' }}</button>
            </form>
            <textarea v-model="form.observacoes" class="mt-3 w-full rounded-md border-slate-300 text-sm" rows="2" placeholder="Observacoes"></textarea>
            <div v-if="Object.keys(form.errors).length" class="mt-2 rounded-md bg-red-50 p-3 text-sm text-red-700">
                <div v-for="(erro, campo) in form.errors" :key="campo"><strong>{{ campo }}:</strong> {{ erro }}</div>
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-lg font-black">Lancamentos manuais</h2>
            <div v-if="Object.keys(editForm.errors).length" class="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700">
                <div v-for="(erro, campo) in editForm.errors" :key="campo"><strong>{{ campo }}:</strong> {{ erro }}</div>
            </div>
            <div v-if="!movimentos.length" class="rounded-md bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">
                Ainda nao ha movimentos manuais.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2">Data</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th>Descricao</th>
                            <th class="text-right">Valor</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="movimento in movimentos" :key="movimento.id" class="border-t border-slate-100">
                            <template v-if="edicaoId === movimento.id">
                                <td class="py-2"><input v-model="editForm.data_movimento" type="date" class="w-36 rounded-md border-slate-300 text-sm"></td>
                                <td>
                                    <select v-model="editForm.tipo" class="w-28 rounded-md border-slate-300 text-sm" @change="ajustarCategoria(editForm)">
                                        <option value="custo">Custo</option>
                                        <option value="receita">Receita</option>
                                    </select>
                                </td>
                                <td>
                                    <select v-model="editForm.categoria" class="w-40 rounded-md border-slate-300 text-sm">
                                        <option v-for="categoria in categoriasParaTipo(editForm.tipo)" :key="categoria.valor" :value="categoria.valor">{{ categoria.label }}</option>
                                    </select>
                                </td>
                                <td><input v-model="editForm.descricao" class="w-full rounded-md border-slate-300 text-sm"></td>
                                <td><input v-model="editForm.valor" type="number" min="0" step="0.01" class="w-28 rounded-md border-slate-300 text-right text-sm"></td>
                                <td class="text-right">
                                    <button type="button" class="font-bold text-emerald-700" @click="guardarEdicao(movimento)">Guardar</button>
                                    <button type="button" class="ml-3 font-bold text-slate-500" @click="edicaoId = null">Cancelar</button>
                                </td>
                            </template>
                            <template v-else>
                                <td class="py-3">{{ dataCurta(movimento.data) }}</td>
                                <td class="font-bold" :class="movimento.tipo === 'receita' ? 'text-emerald-700' : 'text-red-700'">{{ movimento.tipo }}</td>
                                <td>{{ movimento.categoria }}</td>
                                <td>
                                    <strong>{{ movimento.descricao }}</strong>
                                    <div v-if="movimento.observacoes" class="text-xs text-slate-500">{{ movimento.observacoes }}</div>
                                </td>
                                <td class="text-right font-black">{{ euros(movimento.valor) }}</td>
                                <td class="text-right">
                                    <button type="button" class="font-bold text-amber-700" @click="editar(movimento)">Editar</button>
                                    <button type="button" class="ml-3 font-bold text-red-700" @click="apagar(movimento)">Apagar</button>
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
