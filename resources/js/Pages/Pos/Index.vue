<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ChamarComissaoModal from '@/Components/ChamarComissaoModal.vue';
import ChamadaFuncionarioAlert from '@/Components/ChamadaFuncionarioAlert.vue';
import ComissaoChamadasAlert from '@/Components/ComissaoChamadasAlert.vue';
import { ImpressoraUsb } from '@/escpos';
import axios from 'axios';

const props = defineProps({
    posNome: String,
    pontoBar: String,
    caixaAberta: Boolean,
    // Modelo do talao junta tudo numa folha: o cliente pode pedir sobremesas/bebidas a parte
    juntarFolhas: Boolean,
    produtos: Array,
    senhasHoje: Array,
});

const agora = ref(new Date());
const carrinho = ref([]);
const recebido = ref('');
const trocoEntregue = ref('');
const form = useForm({ items: [], devolvidos: [], valor_recebido: 0, troco: 0, juntar: {}, metodo_pagamento: 'dinheiro' });

// Forma de pagamento: so o dinheiro tem troco e abre a gaveta
const METODOS = [
    { id: 'dinheiro', label: 'Dinheiro' },
    { id: 'mbway', label: 'MB WAY' },
    { id: 'contactless', label: 'Contactless' },
];
const metodo = ref('dinheiro');
const emDinheiro = computed(() => metodo.value === 'dinheiro');

// Senhas: por omissao uma por unidade. Se o cliente pedir, junta-se um grupo numa folha.
const GRUPOS = [
    { chave: 'cozinha', label: 'Comida', secoes: null, junto: false },
    { chave: 'sobremesas', label: 'Sobremesas', secoes: ['sobremesas'], junto: false },
    { chave: 'bebidas', label: 'Bebidas', secoes: ['bebidas', 'bar', 'cafe'], junto: false },
];
const grupoDe = (secao) => GRUPOS.find((g) => g.secoes?.includes(secao))?.chave ?? 'cozinha';
const juntarPadrao = () => Object.fromEntries(GRUPOS.map((g) => [g.chave, g.junto]));
const juntar = ref(juntarPadrao());
// So aparece o botao de um grupo quando a senha tem pelo menos 2 unidades dele
const gruposNaSenha = computed(() => {
    if (!props.juntarFolhas) return [];
    return GRUPOS.filter((g) => carrinho.value
        .filter((i) => grupoDe(i.secao) === g.chave)
        .reduce((soma, i) => soma + i.quantidade, 0) >= 2);
});
const alternarJuntar = (chave) => { juntar.value = { ...juntar.value, [chave]: !juntar.value[chave] }; };
let relogio = null;
let refresh = null;

// Agrupar produtos por categoria (igual ao restaurante)
const secoes = computed(() => {
    const map = new Map();
    (props.produtos ?? []).forEach((p) => {
        const nome = p.categoria?.nome ?? 'Outros';
        if (!map.has(nome)) map.set(nome, { nome, produtos: [] });
        map.get(nome).produtos.push(p);
    });
    return [...map.values()];
});

const secaoAtiva = ref(null);
const secaoAtivaKey = computed(() => secaoAtiva.value ?? secoes.value[0]?.nome ?? null);
const produtosVisiveis = computed(() => {
    if (!secaoAtivaKey.value) return props.produtos ?? [];
    return secoes.value.find((s) => s.nome === secaoAtivaKey.value)?.produtos ?? [];
});
const tituloAtivo = computed(() => secaoAtivaKey.value ?? 'Produtos');

// Cor do botão por secção (igual ao restaurante)
const secaoClasse = (produto) => ({
    bebidas: 'bg-blue-600',
    frango: 'bg-red-700',
    cozinha: 'bg-orange-600',
    comida: 'bg-orange-600',
    acompanhamentos: 'bg-emerald-700',
    sobremesas: 'bg-purple-600',
}[produto.categoria?.secao] || 'bg-gray-700');

// Estilo com imagem de fundo quando disponível
const btnStyle = (produto) => {
    if (!produto.imagem) return {};
    // A foto (quadrada, fundo branco) fica inteira do lado direito do botão;
    // à esquerda, fundo escuro para o nome e o preço se lerem bem.
    return {
        backgroundColor: '#ffffff',
        backgroundImage:
            'linear-gradient(90deg, #111827 0%, #111827 48%, rgba(17,24,39,0.55) 62%, rgba(17,24,39,0) 74%),' +
            'url(/storage/' + produto.imagem + ')',
        backgroundSize: '100% 100%, contain',
        backgroundPosition: 'left center, right center',
        backgroundRepeat: 'no-repeat, no-repeat',
    };
};

const total = computed(() => carrinho.value.reduce((soma, item) => soma + Number(item.preco) * item.quantidade, 0));
const cartQty = computed(() => Object.fromEntries(carrinho.value.map((i) => [i.produto_id, i.quantidade])));

// Caucao (metro): cobrada a parte na venda; metros devolvidos podem ser
// trocados por bebidas (descontam aqui) ou devolvidos em dinheiro.
const produtosCaucao = computed(() => (props.produtos ?? []).filter((p) => Number(p.caucao) > 0));
const devolvidos = ref([]);
const comCaucao = (item) => Math.max(0, item.quantidade - Math.min(item.jaTem || 0, item.quantidade));
const caucaoCobrada = computed(() => carrinho.value.reduce((soma, item) => soma + Number(item.caucao || 0) * comCaucao(item), 0));
const caucaoDescontada = computed(() => devolvidos.value.reduce((soma, d) => soma + Number(d.caucao) * d.quantidade, 0));
const aPagar = computed(() => Math.round((total.value + caucaoCobrada.value - caucaoDescontada.value) * 100) / 100);
const saldoExcedido = computed(() => caucaoDescontada.value > 0 && aPagar.value < 0);
const painelMetro = ref(false);
const qtdMetro = ref({});
const qtdDe = (produto) => qtdMetro.value[produto.id] ?? 1;
const mudarQtdMetro = (produto, delta) => { qtdMetro.value[produto.id] = Math.max(1, qtdDe(produto) + delta); };

const usarEmBebidas = (produto) => {
    const linha = devolvidos.value.find((d) => d.produto_id === produto.id);
    linha ? (linha.quantidade += qtdDe(produto)) : devolvidos.value.push({ produto_id: produto.id, nome: produto.nome, caucao: produto.caucao, quantidade: qtdDe(produto) });
    qtdMetro.value[produto.id] = 1;
    painelMetro.value = false;
    trocoEntregue.value = '';
};
const alterarDevolvido = (linha, delta) => {
    linha.quantidade += delta;
    devolvidos.value = devolvidos.value.filter((d) => d.quantidade > 0);
};
const devolvendo = ref(false);
const devolverDinheiro = (produto) => {
    const quantidade = qtdDe(produto);
    if (!confirm(`Devolver ${eur(Number(produto.caucao) * quantidade)} em dinheiro (${quantidade}x ${produto.nome})?`)) return;
    router.post(route('pos.caucao.devolver'), { produto_id: produto.id, quantidade }, {
        preserveScroll: true,
        onStart: () => (devolvendo.value = true),
        onFinish: () => (devolvendo.value = false),
        onSuccess: () => { painelMetro.value = false; qtdMetro.value[produto.id] = 1; },
    });
};

const troco = computed(() => Math.max(0, Number(recebido.value || 0) - aPagar.value));
const trocoRegistado = computed(() => trocoEntregue.value === '' ? troco.value : Number(trocoEntregue.value || 0));
const doacao = computed(() => Math.max(0, troco.value - trocoRegistado.value));
const euros = (valor) => Number(valor ?? 0).toFixed(2) + '€';
const hora = (data) => new Date(data).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
const logout = () => router.post(route('pos.logout'));

// ---- Senhas anteriores: ver, reimprimir (2a via) e anular ----
const painelSenhas = ref(false);
const listaSenhas = ref([]);
const procurarNumero = ref('');
const aCarregarSenhas = ref(false);
const senhaAberta = ref(null);
const aAnular = ref(false);
const motivoAnulacao = ref('');
const acaoSenha = useForm({ o: 'tudo', motivo: '' });
const MOTIVOS_RAPIDOS = ['Engano no pedido', 'Cliente desistiu', 'Pagamento não passou'];

const carregarSenhas = async () => {
    aCarregarSenhas.value = true;
    try {
        const { data } = await axios.get(route('pos.senhas'), { params: { numero: procurarNumero.value || undefined } });
        listaSenhas.value = data.senhas ?? [];
        if (senhaAberta.value) {
            senhaAberta.value = listaSenhas.value.find((s) => s.id === senhaAberta.value.id) ?? senhaAberta.value;
        }
    } finally {
        aCarregarSenhas.value = false;
    }
};
const abrirSenhas = async (id = null) => {
    painelSenhas.value = true;
    senhaAberta.value = null;
    aAnular.value = false;
    procurarNumero.value = '';
    await carregarSenhas();
    if (id) senhaAberta.value = listaSenhas.value.find((s) => s.id === id) ?? null;
};
const fecharSenhas = () => {
    painelSenhas.value = false;
    senhaAberta.value = null;
    aAnular.value = false;
    acaoSenha.clearErrors();
};
const escolherSenha = (senha) => {
    senhaAberta.value = senha;
    aAnular.value = false;
    motivoAnulacao.value = '';
    acaoSenha.clearErrors();
};
const reimprimir = (o) => {
    acaoSenha.o = o;
    acaoSenha.post(route('pos.pedido.reimprimir', senhaAberta.value.id), { preserveScroll: true, onSuccess: () => fecharSenhas() });
};
const aDevolver = computed(() => {
    const s = senhaAberta.value;
    if (!s) return 0;
    return Number(s.pago) + Number(s.doacao) + Number(s.caucao_descontada);
});
const confirmarAnulacao = () => {
    acaoSenha.motivo = motivoAnulacao.value;
    acaoSenha.post(route('pos.pedido.anular', senhaAberta.value.id), { preserveScroll: true, onSuccess: () => fecharSenhas() });
};
const chamandoComissao = ref(false);

// jaTem: o cliente traz o metro/jarro para voltar a encher - nao se cobra caucao
const adicionar = (produto, { jaTem = false } = {}) => {
    let item = carrinho.value.find((linha) => linha.produto_id === produto.id);
    if (item) {
        item.quantidade++;
    } else {
        item = { produto_id: produto.id, nome: produto.nome, preco: produto.preco, caucao: Number(produto.caucao || 0), secao: produto.categoria?.secao ?? null, jaTem: 0, quantidade: 1 };
        carrinho.value.push(item);
    }
    if (jaTem) item.jaTem = Math.min(item.quantidade, (item.jaTem || 0) + 1);
};

const alterar = (item, delta) => {
    item.quantidade += delta;
    carrinho.value = carrinho.value.filter((linha) => linha.quantidade > 0);
};

const cobrar = () => {
    form.items = carrinho.value.map(({ produto_id, quantidade, jaTem }) => ({ produto_id, quantidade, ja_tem: Math.min(jaTem || 0, quantidade) }));
    form.devolvidos = devolvidos.value.map(({ produto_id, quantidade }) => ({ produto_id, quantidade }));
    form.metodo_pagamento = metodo.value;
    form.valor_recebido = emDinheiro.value ? (recebido.value || Math.max(0, aPagar.value)) : Math.max(0, aPagar.value);
    form.troco = emDinheiro.value ? trocoRegistado.value : 0;
    form.juntar = Object.fromEntries(Object.entries(juntar.value).map(([k, v]) => [k, v ? 1 : 0]));
    form.post(route('pos.prepago.store'), {
        preserveScroll: true,
        onSuccess: () => {
            metodo.value = 'dinheiro';
            juntar.value = juntarPadrao();
            carrinho.value = [];
            devolvidos.value = [];
            recebido.value = '';
            trocoEntregue.value = '';
        },
    });
};

// Confirmacao curta depois de cobrar (o talao sai sozinho na impressora)
const page = usePage();
const aviso = ref('');
let avisoTimer = null;
watch(() => page.props.flash?.success, (msg) => {
    if (!msg) return;
    aviso.value = msg;
    clearTimeout(avisoTimer);
    avisoTimer = setTimeout(() => (aviso.value = ''), 4000);
}, { immediate: true });

// Impressao sem sair do POS (postos WebUSB/navegador).
// WebUSB: manda os bytes ESC/POS daqui — o primeiro talao abre a gaveta.
// Navegador: imprime a pagina do talao num iframe escondido; sem dialogo
// so com o Chrome em --kiosk-printing.
const usb = new ImpressoraUsb();
const impressaoPendente = ref(null);
const erroImpressao = ref('');
const aImprimir = ref(false);
let ultimoImpresso = null;
let iframeTalao = null;

const imprimirUsb = async (trabalho, { pedirSeNecessario = false } = {}) => {
    erroImpressao.value = '';
    aImprimir.value = true;

    try {
        if (!usb.ligada && !(await usb.reconectar())) {
            if (!pedirSeNecessario) {
                // O browser so deixa escolher a impressora depois de um clique
                impressaoPendente.value = trabalho;
                return;
            }
            await usb.escolher();
        }

        for (const payload of trabalho.escpos) {
            await usb.imprimir(payload);
        }
        impressaoPendente.value = null;
    } catch (e) {
        if (e?.name === 'NotFoundError') return;
        impressaoPendente.value = trabalho;
        erroImpressao.value = e?.message || String(e);
    } finally {
        aImprimir.value = false;
    }
};

const imprimirNavegador = (trabalho) => {
    iframeTalao?.remove();
    iframeTalao = document.createElement('iframe');
    iframeTalao.setAttribute('aria-hidden', 'true');
    iframeTalao.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
    iframeTalao.src = trabalho.url;
    document.body.appendChild(iframeTalao);
};

const imprimirTrabalho = (trabalho, opcoes) => (trabalho.modo === 'webusb'
    ? imprimirUsb(trabalho, opcoes)
    : imprimirNavegador(trabalho));

watch(() => page.props.flash?.imprimir, (trabalho) => {
    if (!trabalho || trabalho.pedido_id === ultimoImpresso) return;
    ultimoImpresso = trabalho.pedido_id;
    imprimirTrabalho(trabalho);
}, { immediate: true });

onMounted(() => {
    relogio = setInterval(() => (agora.value = new Date()), 1000);
    refresh = setInterval(() => router.reload({ only: ['caixaAberta', 'senhasHoje'], preserveScroll: true }), 20000);
});

onBeforeUnmount(() => {
    clearInterval(relogio);
    clearInterval(refresh);
    clearTimeout(avisoTimer);
    iframeTalao?.remove();
});

// ---------------------------------------------------------------------------
// Apresentação (redesign) — só formatação e atalhos que escrevem nos mesmos campos
// ---------------------------------------------------------------------------
const eur = (valor) => Number(valor ?? 0).toLocaleString('pt-PT', { useGrouping: 'always', minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const artigos = computed(() => carrinho.value.reduce((soma, item) => soma + item.quantidade, 0));
const secoesInfo = {
    cozinha: { label: 'Cozinha', dot: 'bg-secao-cozinha', text: 'text-secao-cozinha' },
    comida: { label: 'Comida', dot: 'bg-secao-cozinha', text: 'text-secao-cozinha' },
    frango: { label: 'Frango', dot: 'bg-secao-grelhados', text: 'text-secao-grelhados' },
    bebidas: { label: 'Bebidas', dot: 'bg-secao-bar', text: 'text-secao-bar' },
    acompanhamentos: { label: 'Acompanhamentos', dot: 'bg-secao-acompanhamentos', text: 'text-secao-acompanhamentos' },
    sobremesas: { label: 'Sobremesas', dot: 'bg-secao-sobremesas', text: 'text-secao-sobremesas' },
    servico: { label: 'Serviço', dot: 'bg-secao-servico', text: 'text-secao-servico' },
};
const secaoDe = (produto) => secoesInfo[produto?.categoria?.secao] ?? { label: produto?.categoria?.nome ?? 'Outros', dot: 'bg-suave', text: 'text-suave' };
const notasRapidas = computed(() => [5, 10, 20, 50, 100].filter((v) => v > aPagar.value).slice(0, 3));
const escolherRecebido = (valor) => {
    recebido.value = valor === '' ? '' : String(valor);
    trocoEntregue.value = '';
};
const doouTroco = computed(() => trocoEntregue.value !== '' && Number(trocoEntregue.value) === 0 && troco.value > 0);
const alternarDoacao = () => { trocoEntregue.value = doouTroco.value ? '' : 0; };
const limparSenha = () => {
    metodo.value = 'dinheiro';
    juntar.value = juntarPadrao();
    carrinho.value = [];
    devolvidos.value = [];
    recebido.value = '';
    trocoEntregue.value = '';
};

</script>

<template>
    <ChamadaFuncionarioAlert />
    <ComissaoChamadasAlert />
    <main class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums lg:h-[100dvh] lg:overflow-hidden">
        <header class="flex shrink-0 flex-wrap items-center justify-between gap-2 bg-escuro px-4 py-2.5 text-white sm:px-6 lg:h-16 lg:py-0 lg:curto:h-12">
            <div class="flex min-w-0 items-baseline gap-4">
                <h1 class="text-xl font-extrabold">POS {{ pontoBar }}</h1>
                <span class="truncate text-sm text-escuro-inativo">{{ posNome }} · {{ agora.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' }) }}</span>
            </div>
            <div class="flex gap-2.5">
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-bold text-white" @click="abrirSenhas()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 3v5h5" /><path d="M12 7v5l3 2" /></svg>
                    Senhas anteriores
                </button>
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-laranja px-4 text-[15px] font-bold text-white" @click="chamandoComissao = true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></svg>
                    Chamar comissão
                </button>
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-bold text-white" @click="logout">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></svg>
                    Sair
                </button>
            </div>
        </header>

        <div v-if="aviso" role="status" class="flex shrink-0 items-center gap-2 bg-verde px-6 py-3 text-[17px] font-bold text-white">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
            {{ aviso }}
        </div>
        <div v-if="impressaoPendente" role="alert" class="flex shrink-0 flex-wrap items-center justify-between gap-3 bg-laranja-claro px-6 py-3 font-bold text-laranja-texto">
            <span>{{ erroImpressao || 'Impressora por autorizar neste equipamento (só da primeira vez).' }}</span>
            <button type="button" class="h-11 rounded-[10px] bg-laranja px-4 text-white disabled:opacity-45" :disabled="aImprimir" @click="imprimirTrabalho(impressaoPendente, { pedirSeNecessario: true })">
                {{ aImprimir ? 'A imprimir...' : 'Imprimir senha' }}
            </button>
        </div>
        <div v-if="!caixaAberta" role="alert" class="shrink-0 bg-perigo-claro px-6 py-3 text-center text-[17px] font-bold text-perigo-texto">
            Caixa fechada para {{ pontoBar }}. Abre a caixa no backoffice antes de vender.
        </div>

        <div class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_400px]">
            <section class="flex min-h-0 min-w-0 flex-col gap-3 p-4 sm:p-6">
                <nav v-if="secoes.length > 1" aria-label="Categorias" class="flex shrink-0 gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="s in secoes"
                        :key="s.nome"
                        type="button"
                        class="h-14 shrink-0 whitespace-nowrap rounded-[10px] border px-5 text-[17px] font-bold"
                        :class="secaoAtivaKey === s.nome ? 'border-escuro bg-escuro text-white' : 'border-linha bg-white text-tinta'"
                        :aria-pressed="secaoAtivaKey === s.nome"
                        @click="secaoAtiva = s.nome"
                    >{{ s.nome }}</button>
                </nav>
                <h2 class="sr-only">{{ tituloAtivo }}</h2>
                <div v-if="produtosVisiveis.length === 0" class="flex flex-1 items-center justify-center font-semibold text-suave">
                    Sem produtos nesta secção.
                </div>
                <div v-else class="grid min-h-0 flex-1 auto-rows-min grid-cols-2 gap-3 overflow-y-auto pb-1 sm:grid-cols-3 xl:grid-cols-4">
                    <template v-for="produto in produtosVisiveis" :key="produto.id">
                    <!-- Produto com caucao (metro, jarro): novo paga caucao, encher nao -->
                    <div
                        v-if="Number(produto.caucao) > 0"
                        class="relative flex min-h-[124px] min-w-0 flex-col overflow-hidden rounded-[14px] bg-white p-3"
                        :class="[cartQty[produto.id] ? 'border-2 border-verde' : 'border border-linha', !caixaAberta ? 'opacity-45' : '']"
                    >
                        <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider" :class="secaoDe(produto).text">
                            <span class="h-2 w-2 rounded-full" :class="secaoDe(produto).dot"></span>{{ secaoDe(produto).label }}
                        </span>
                        <span class="mt-1 block break-words pr-9 text-lg font-bold leading-tight">{{ produto.nome }} <span class="text-base font-medium text-suave">{{ eur(produto.preco) }}</span></span>
                        <span v-if="cartQty[produto.id]" class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-verde text-base font-bold text-white">{{ cartQty[produto.id] }}</span>
                        <div class="mt-auto grid grid-cols-2 gap-2 pt-2">
                            <button type="button" class="flex h-12 flex-col items-center justify-center rounded-[10px] border border-laranja bg-laranja-claro px-1 leading-tight text-laranja-texto disabled:cursor-not-allowed" :disabled="!caixaAberta" @click="adicionar(produto)">
                                <span class="text-[15px] font-bold">Novo</span>
                                <span class="text-xs font-semibold">+{{ eur(produto.caucao) }}</span>
                            </button>
                            <button type="button" class="flex h-12 flex-col items-center justify-center rounded-[10px] border border-verde bg-verde-claro px-1 leading-tight text-verde-escuro disabled:cursor-not-allowed" :disabled="!caixaAberta" @click="adicionar(produto, { jaTem: true })">
                                <span class="text-[15px] font-bold">Encher</span>
                                <span class="text-xs font-semibold">sem caução</span>
                            </button>
                        </div>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="relative flex min-h-[124px] min-w-0 flex-col items-start overflow-hidden rounded-[14px] bg-white p-4 text-left disabled:cursor-not-allowed disabled:opacity-45"
                        :class="cartQty[produto.id] ? 'border-2 border-verde' : 'border border-linha'"
                        :disabled="!caixaAberta"
                        @click="adicionar(produto)"
                    >
                        <img v-if="produto.imagem" :src="`/storage/${produto.imagem}`" alt="" class="pointer-events-none absolute bottom-2 right-2 h-14 w-14 rounded-lg object-contain opacity-90">
                        <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider" :class="secaoDe(produto).text">
                            <span class="h-2 w-2 rounded-full" :class="secaoDe(produto).dot"></span>{{ secaoDe(produto).label }}
                        </span>
                        <span class="mt-2 block break-words pr-8 text-lg font-bold leading-tight" :class="produto.imagem ? 'pr-16' : ''">{{ produto.nome }}</span>
                        <span class="mt-auto pt-3 text-base font-medium text-suave">{{ eur(produto.preco) }}</span>
                        <span v-if="cartQty[produto.id]" class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-verde text-base font-bold text-white">{{ cartQty[produto.id] }}</span>
                    </button>
                    </template>
                </div>

                <section aria-label="Últimas senhas" class="shrink-0 rounded-[14px] border border-linha bg-white px-4 py-3 curto:px-3 curto:py-2">
                    <div class="mb-2 flex items-center justify-between gap-2 curto:mb-1.5">
                        <h2 class="text-sm font-extrabold uppercase tracking-wider text-suave">Últimas senhas <span class="font-semibold normal-case tracking-normal">— toca para reimprimir ou anular</span></h2>
                        <button type="button" class="h-9 shrink-0 rounded-[8px] border border-linha-forte px-3 text-sm font-bold" @click="abrirSenhas()">Ver todas</button>
                    </div>
                    <p v-if="!senhasHoje?.length" class="text-sm text-suave">Ainda não há senhas neste posto.</p>
                    <!-- Ecras baixos: uma fila so, com scroll para o lado -->
                    <div class="grid max-h-32 gap-2 overflow-y-auto sm:grid-cols-3 xl:grid-cols-4 curto:flex curto:max-h-none curto:overflow-x-auto curto:overflow-y-hidden curto:pb-1 [&>button]:curto:w-44 [&>button]:curto:shrink-0">
                        <button v-for="pedido in senhasHoje" :key="pedido.id" type="button" class="min-w-0 rounded-[10px] bg-fundo px-2.5 py-2 text-left hover:bg-linha-fraca" :class="pedido.estado === 'cancelado' ? 'opacity-60' : ''" @click="abrirSenhas(pedido.id)">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="text-lg font-extrabold" :class="pedido.estado === 'cancelado' ? 'line-through' : ''">#{{ pedido.numero_senha }}</span>
                                <span class="text-xs text-suave">{{ hora(pedido.created_at) }}</span>
                            </div>
                            <span class="block truncate text-xs text-suave">{{ pedido.estado === 'cancelado' ? 'ANULADA · ' : '' }}{{ pedido.items.map((item) => `${item.quantidade}x ${item.produto?.nome}`).join(', ') }}</span>
                        </button>
                    </div>
                </section>
            </section>

            <aside aria-label="Senha" class="flex min-h-0 flex-col border-t lg:overflow-y-auto border-linha bg-white lg:border-l lg:border-t-0">
                <div class="flex shrink-0 items-center justify-between border-b border-linha px-5 py-3.5 curto:py-2">
                    <h2 class="text-xl font-extrabold">Senha</h2>
                    <div class="flex gap-2">
                        <button v-if="produtosCaucao.length" type="button" class="h-11 rounded-[10px] border border-laranja bg-laranja-claro px-4 text-[15px] font-bold text-laranja-texto disabled:opacity-45" :disabled="!caixaAberta" :aria-expanded="painelMetro" @click="painelMetro = !painelMetro">Devolução de caução</button>
                        <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-perigo disabled:opacity-45" :disabled="!carrinho.length && !devolvidos.length" @click="limparSenha">Limpar</button>
                    </div>
                </div>
                <div v-if="painelMetro" class="shrink-0 space-y-3 border-b border-laranja bg-laranja-claro px-5 py-4">
                    <div v-for="produto in produtosCaucao" :key="produto.id" class="space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-[17px] font-extrabold text-laranja-texto">{{ produto.nome }} · caução {{ eur(produto.caucao) }}</span>
                            <div class="flex items-center gap-2">
                                <button type="button" aria-label="Menos um" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte bg-white" @click="mudarQtdMetro(produto, -1)">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                                </button>
                                <span class="w-7 text-center text-lg font-bold">{{ qtdDe(produto) }}</span>
                                <button type="button" aria-label="Mais um" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte bg-white" @click="mudarQtdMetro(produto, 1)">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="h-14 rounded-[10px] bg-verde text-base font-bold text-white" @click="usarEmBebidas(produto)">Usar em bebidas<br><span class="text-sm font-semibold">saldo {{ eur(produto.caucao * qtdDe(produto)) }}</span></button>
                            <button type="button" class="h-14 rounded-[10px] bg-laranja text-base font-bold text-white disabled:opacity-45" :disabled="devolvendo" @click="devolverDinheiro(produto)">Devolver dinheiro<br><span class="text-sm font-semibold">{{ eur(produto.caucao * qtdDe(produto)) }} da gaveta</span></button>
                        </div>
                    </div>
                </div>
                <div class="min-h-[120px] flex-1 overflow-y-auto px-5 curto:min-h-[84px]">
                    <p v-if="!carrinho.length && !devolvidos.length" class="py-10 text-center text-[15px] text-suave">Escolhe os produtos.</p>
                    <div v-for="d in devolvidos" :key="`dev-${d.produto_id}`" class="flex items-center gap-3 border-b border-linha-fraca py-3 text-verde-escuro curto:py-1.5">
                        <span class="min-w-0 flex-1 truncate text-[17px] font-bold">{{ d.nome }} devolvido</span>
                        <div class="flex items-center gap-2">
                            <button type="button" aria-label="Retirar um devolvido" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo" @click="alterarDevolvido(d, -1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                            </button>
                            <span class="w-7 text-center text-lg font-bold">{{ d.quantidade }}</span>
                            <button type="button" aria-label="Mais um devolvido" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo" @click="alterarDevolvido(d, 1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            </button>
                        </div>
                        <span class="w-20 text-right text-base font-bold">-{{ eur(d.caucao * d.quantidade) }}</span>
                    </div>
                    <div v-for="item in carrinho" :key="item.produto_id" class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-linha-fraca py-3 curto:py-1.5">
                        <span class="min-w-0 flex-1 truncate text-[17px] font-bold">{{ item.nome }}</span>
                        <div class="flex items-center gap-2">
                            <button type="button" aria-label="Retirar um" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo" @click="alterar(item, -1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                            </button>
                            <span class="w-7 text-center text-lg font-bold">{{ item.quantidade }}</span>
                            <button type="button" aria-label="Adicionar mais um" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo" @click="alterar(item, 1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            </button>
                        </div>
                        <span class="w-20 text-right text-base font-bold">{{ eur(item.preco * item.quantidade) }}</span>
                        <div v-if="item.caucao > 0" class="flex w-full items-center gap-2 rounded-[10px] bg-laranja-claro px-3 py-2" role="group" :aria-label="`Caução de ${item.nome}`">
                            <span class="min-w-0 flex-1 text-[15px] font-bold text-laranja-texto">
                                <template v-if="comCaucao(item)">Caução {{ comCaucao(item) }}x +{{ eur(item.caucao * comCaucao(item)) }}</template>
                                <template v-else>Encher — sem caução</template>
                            </span>
                            <span class="text-[15px] font-semibold text-suave">Já tem</span>
                            <button type="button" aria-label="Já tem menos um" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte bg-white disabled:opacity-40" :disabled="!(item.jaTem > 0)" @click="item.jaTem = Math.max(0, (item.jaTem || 0) - 1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                            </button>
                            <span class="w-7 text-center text-lg font-bold">{{ Math.min(item.jaTem || 0, item.quantidade) }}</span>
                            <button type="button" aria-label="Já tem mais um" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte bg-white disabled:opacity-40" :disabled="(item.jaTem || 0) >= item.quantidade" @click="item.jaTem = Math.min(item.quantidade, (item.jaTem || 0) + 1)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="shrink-0 space-y-3 border-t border-linha bg-fundo p-5 curto:space-y-2 curto:p-3">
                    <div v-if="caucaoCobrada || caucaoDescontada" class="space-y-0.5 text-[15px]">
                        <div class="flex justify-between text-suave"><span>Produtos</span><span class="font-bold">{{ eur(total) }}</span></div>
                        <div v-if="caucaoCobrada" class="flex justify-between text-laranja-texto"><span>Caução</span><span class="font-bold">+{{ eur(caucaoCobrada) }}</span></div>
                        <div v-if="caucaoDescontada" class="flex justify-between text-verde-escuro"><span>Caução devolvida (saldo)</span><span class="font-bold">-{{ eur(caucaoDescontada) }}</span></div>
                    </div>
                    <div class="flex items-end justify-between gap-2">
                        <span class="text-[15px] text-suave">A pagar · {{ artigos }} artigos</span>
                        <span class="text-4xl font-extrabold curto:text-3xl">{{ eur(Math.max(0, aPagar)) }}</span>
                    </div>
                    <p v-if="saldoExcedido" role="alert" class="rounded-[10px] bg-perigo-claro p-2 text-sm font-bold text-perigo-texto">O saldo das cauções devolvidas ({{ eur(caucaoDescontada) }}) é maior que a senha. Junta mais bebidas ou devolve o resto em dinheiro.</p>
                    <div role="group" aria-label="Forma de pagamento" class="grid grid-cols-3 gap-2">
                        <button
                            v-for="m in METODOS"
                            :key="m.id"
                            type="button"
                            class="h-12 rounded-[10px] text-base font-bold curto:h-10"
                            :class="metodo === m.id ? 'border-[3px] border-verde bg-verde-claro text-verde-escuro' : 'border border-linha-forte bg-white text-tinta'"
                            :aria-pressed="metodo === m.id"
                            @click="metodo = m.id"
                        >{{ m.label }}</button>
                    </div>
                    <p v-if="!emDinheiro" class="rounded-[10px] bg-fundo px-3 py-2 text-[15px] font-semibold text-suave">
                        Confirma o pagamento de <strong class="text-tinta">{{ eur(Math.max(0, aPagar)) }}</strong> por {{ METODOS.find((m) => m.id === metodo)?.label }} antes de cobrar. Não abre a gaveta.
                    </p>
                    <div v-if="emDinheiro">
                        <span class="text-sm font-semibold text-suave">Recebido</span>
                        <div role="group" aria-label="Valor recebido" class="mt-1 grid grid-cols-4 gap-2">
                            <button type="button" class="h-14 rounded-[10px] text-base font-bold curto:h-11" :class="recebido === '' ? 'border-[3px] border-verde bg-verde-claro text-verde-escuro' : 'border border-linha-forte bg-white'" :aria-pressed="recebido === ''" @click="escolherRecebido('')">Certo</button>
                            <button
                                v-for="v in notasRapidas"
                                :key="v"
                                type="button"
                                class="h-14 rounded-[10px] text-base font-bold curto:h-11"
                                :class="String(recebido) === String(v) ? 'border-[3px] border-verde bg-verde-claro text-verde-escuro' : 'border border-linha-forte bg-white'"
                                :aria-pressed="String(recebido) === String(v)"
                                @click="escolherRecebido(v)"
                            >{{ v }} €</button>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <label class="block min-w-0">
                                <span class="mb-1 block truncate text-sm font-semibold text-suave">Outro valor</span>
                                <input v-model="recebido" inputmode="decimal" class="h-12 w-full rounded-[10px] border-linha-forte bg-white text-lg font-bold curto:h-10 text-tinta focus:border-verde focus:ring-verde" placeholder="0,00">
                            </label>
                            <label class="block min-w-0">
                                <span class="mb-1 block truncate text-sm font-semibold text-suave">Troco entregue</span>
                                <input v-model="trocoEntregue" inputmode="decimal" class="h-12 w-full rounded-[10px] border-linha-forte bg-white text-lg font-bold curto:h-10 text-tinta focus:border-verde focus:ring-verde" :placeholder="eur(troco)">
                            </label>
                        </div>
                    </div>
                    <div v-if="emDinheiro" class="grid grid-cols-2 gap-2">
                        <div class="rounded-[10px] border border-laranja bg-laranja-claro px-3 py-2 curto:py-1">
                            <span class="block text-sm font-bold text-laranja-texto">Troco a dar</span>
                            <span class="block text-2xl font-extrabold curto:text-xl">{{ eur(trocoRegistado) }}</span>
                        </div>
                        <div class="rounded-[10px] border border-linha-forte bg-fundo px-3 py-2 curto:py-1">
                            <span class="block text-sm font-bold text-suave">Doação</span>
                            <span class="block text-2xl font-extrabold curto:text-xl">{{ eur(doacao) }}</span>
                        </div>
                    </div>
                    <button
                        v-if="emDinheiro && (troco > 0 || doouTroco)"
                        type="button"
                        class="h-14 w-full rounded-[10px] border border-laranja text-base font-bold disabled:opacity-45 curto:h-11"
                        :class="doouTroco ? 'bg-laranja text-white' : 'bg-laranja-claro text-laranja-texto'"
                        :aria-pressed="doouTroco"
                        :disabled="troco <= 0"
                        @click="alternarDoacao"
                    >{{ doouTroco ? 'Cliente doou o troco — anular' : 'Cliente doa o troco' }}</button>
                    <div v-if="gruposNaSenha.length" role="group" aria-label="Juntar senhas por secção" class="flex flex-col gap-1.5 curto:gap-1">
                        <span class="text-sm font-semibold text-suave curto:text-xs">Juntar senhas numa folha — comida inclui frango e acomp.</span>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="g in gruposNaSenha"
                                :key="g.chave"
                                type="button"
                                role="switch"
                                class="flex h-12 min-w-0 flex-col items-center justify-center overflow-hidden rounded-[10px] px-1 leading-tight curto:h-10"
                                :class="juntar[g.chave] ? 'border-[3px] border-verde bg-verde-claro text-verde-escuro' : 'border border-linha-forte bg-white text-tinta'"
                                :aria-checked="juntar[g.chave]"
                                @click="alternarJuntar(g.chave)"
                            >
                                <span class="max-w-full truncate text-[15px] font-bold">{{ g.label }}</span>
                                <span class="text-xs font-semibold" :class="juntar[g.chave] ? '' : 'text-suave'">{{ juntar[g.chave] ? '✓ juntas' : 'separadas' }}</span>
                            </button>
                        </div>
                    </div>
                    <AvisoErros :errors="form.errors" class="!p-2" />
                    <button type="button" class="h-[68px] w-full rounded-[14px] bg-laranja text-xl font-bold curto:h-14 text-white disabled:opacity-45" :disabled="!caixaAberta || !carrinho.length || saldoExcedido || form.processing" @click="cobrar">
                        {{ emDinheiro ? 'Cobrar e tirar senha' : `Cobrar ${METODOS.find((m) => m.id === metodo)?.label} e tirar senha` }}
                    </button>
                </div>
            </aside>
        </div>

        <!-- Senhas anteriores -->
        <div v-if="painelSenhas" class="fixed inset-0 z-50 flex items-stretch justify-center bg-black/50 p-0 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Senhas anteriores" @click.self="fecharSenhas">
            <div class="flex h-full w-full max-w-4xl flex-col overflow-hidden bg-white sm:h-[min(640px,92dvh)] sm:rounded-[16px]">
                <div class="flex shrink-0 items-center gap-3 border-b border-linha px-4 py-3">
                    <button v-if="senhaAberta" type="button" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte md:hidden" aria-label="Voltar à lista" @click="senhaAberta = null">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    </button>
                    <h2 class="min-w-0 flex-1 truncate text-xl font-extrabold">Senhas anteriores · {{ pontoBar }}</h2>
                    <button type="button" class="h-11 rounded-[10px] border border-linha-forte px-4 text-[15px] font-bold" @click="fecharSenhas">Fechar</button>
                </div>
                <div class="grid min-h-0 flex-1 md:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)]">
                    <!-- Lista -->
                    <div class="flex min-h-0 flex-col border-linha md:border-r" :class="senhaAberta ? 'hidden md:flex' : 'flex'">
                        <form class="flex shrink-0 gap-2 p-3" @submit.prevent="carregarSenhas">
                            <input v-model="procurarNumero" inputmode="numeric" class="h-12 min-w-0 flex-1 rounded-[10px] border-linha-forte text-lg font-bold" placeholder="Nº da senha" aria-label="Procurar pelo número da senha">
                            <button type="submit" class="h-12 rounded-[10px] bg-escuro px-4 font-bold text-white">Procurar</button>
                        </form>
                        <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                            <p v-if="aCarregarSenhas" class="py-8 text-center text-suave">A carregar…</p>
                            <p v-else-if="!listaSenhas.length" class="py-8 text-center text-suave">Sem senhas{{ procurarNumero ? ' com esse número' : '' }}.</p>
                            <button
                                v-for="s in listaSenhas"
                                v-else
                                :key="s.id"
                                type="button"
                                class="mb-2 flex w-full items-center gap-3 rounded-[12px] border px-3 py-2.5 text-left"
                                :class="senhaAberta?.id === s.id ? 'border-2 border-verde bg-verde-claro' : 'border-linha bg-white'"
                                @click="escolherSenha(s)"
                            >
                                <span class="w-16 shrink-0 text-xl font-extrabold" :class="s.anulada ? 'text-suave line-through' : ''">#{{ s.numero }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold">{{ s.itens.map((i) => `${i.quantidade}x ${i.nome}`).join(', ') }}</span>
                                    <span class="block text-xs text-suave">{{ s.hora }} · {{ s.metodo_nome }}<template v-if="s.reimpressoes"> · 2a via ×{{ s.reimpressoes }}</template></span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <span class="block font-bold">{{ eur(s.pago) }}</span>
                                    <span v-if="s.anulada" class="mt-0.5 inline-block rounded-full bg-perigo-claro px-2 text-xs font-extrabold text-perigo-texto">ANULADA</span>
                                </span>
                            </button>
                        </div>
                    </div>
                    <!-- Detalhe -->
                    <div class="min-h-0 overflow-y-auto p-4" :class="senhaAberta ? 'block' : 'hidden md:block'">
                        <p v-if="!senhaAberta" class="py-12 text-center text-suave">Toca numa senha para ver o que foi pedido, reimprimir ou anular.</p>
                        <template v-else>
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-4xl font-extrabold" :class="senhaAberta.anulada ? 'line-through text-suave' : ''">#{{ senhaAberta.numero }}</span>
                                <span class="text-sm text-suave">{{ senhaAberta.hora }} · {{ senhaAberta.operador }}</span>
                            </div>
                            <div v-if="senhaAberta.anulada" class="mt-3 rounded-[10px] bg-perigo-claro p-3 text-sm font-semibold text-perigo-texto">
                                Anulada às {{ senhaAberta.anulado_em }} por {{ senhaAberta.anulado_por }} — {{ senhaAberta.motivo_anulacao }}.
                                Devolvido: {{ eur(senhaAberta.valor_devolvido) }}.
                            </div>
                            <ul class="mt-3 divide-y divide-linha-fraca rounded-[12px] border border-linha">
                                <li v-for="(i, k) in senhaAberta.itens" :key="k" class="flex justify-between gap-3 px-3 py-2 text-[15px]">
                                    <span class="font-semibold">{{ i.quantidade }}x {{ i.nome }}</span><span>{{ eur(i.valor) }}</span>
                                </li>
                            </ul>
                            <div class="mt-3 space-y-0.5 text-[15px]">
                                <div v-if="senhaAberta.caucao_cobrada" class="flex justify-between text-laranja-texto"><span>Caução</span><span class="font-bold">+{{ eur(senhaAberta.caucao_cobrada) }}</span></div>
                                <div v-if="senhaAberta.caucao_descontada" class="flex justify-between text-verde-escuro"><span>Caução devolvida (saldo)</span><span class="font-bold">-{{ eur(senhaAberta.caucao_descontada) }}</span></div>
                                <div class="flex justify-between text-lg"><span>Pago · {{ senhaAberta.metodo_nome }}</span><strong>{{ eur(senhaAberta.pago) }}</strong></div>
                                <div v-if="senhaAberta.doacao" class="flex justify-between text-suave"><span>Doação (troco deixado)</span><span>{{ eur(senhaAberta.doacao) }}</span></div>
                                <div v-if="senhaAberta.reimpressoes" class="text-sm text-suave">Já reimpressa {{ senhaAberta.reimpressoes }}×.</div>
                            </div>

                            <AvisoErros :errors="acaoSenha.errors" class="mt-3 !p-2" />

                            <div v-if="!senhaAberta.anulada && !aAnular" class="mt-4 grid grid-cols-2 gap-2">
                                <button type="button" class="h-14 rounded-[10px] bg-escuro text-base font-bold text-white disabled:opacity-45" :disabled="acaoSenha.processing" @click="reimprimir('tudo')">Reimprimir tudo</button>
                                <button type="button" class="h-14 rounded-[10px] border border-linha-forte bg-white text-base font-bold disabled:opacity-45" :disabled="acaoSenha.processing" @click="reimprimir('conta')">Só a conta</button>
                                <p class="col-span-2 text-xs text-suave">A reimpressão sai com "2a VIA" e não abre a gaveta.</p>
                                <button type="button" class="col-span-2 h-12 rounded-[10px] border border-perigo bg-perigo-claro text-base font-bold text-perigo-texto" @click="aAnular = true">Anular senha…</button>
                            </div>

                            <div v-if="!senhaAberta.anulada && aAnular" class="mt-4 space-y-3 rounded-[12px] border-2 border-perigo p-3">
                                <div class="text-lg font-extrabold text-perigo-texto">Anular a senha #{{ senhaAberta.numero }}</div>
                                <div class="rounded-[10px] bg-fundo p-3 text-[15px]">
                                    Devolver ao cliente: <strong class="text-xl">{{ eur(aDevolver) }}</strong>
                                    <span class="block text-sm text-suave">
                                        <template v-if="senhaAberta.metodo === 'dinheiro'">Em dinheiro, da gaveta (abre ao confirmar).</template>
                                        <template v-else>Por {{ senhaAberta.metodo_nome }}<template v-if="senhaAberta.caucao_descontada"> — e {{ eur(senhaAberta.caucao_descontada) }} de caução em dinheiro, da gaveta</template>.</template>
                                    </span>
                                    <span class="block text-sm text-suave">Os talões ainda por imprimir já não saem. Recolhe as senhas que o cliente tiver.</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="m in MOTIVOS_RAPIDOS" :key="m" type="button" class="h-10 rounded-full border px-3 text-sm font-bold" :class="motivoAnulacao === m ? 'border-perigo bg-perigo-claro text-perigo-texto' : 'border-linha-forte'" @click="motivoAnulacao = m">{{ m }}</button>
                                </div>
                                <input v-model="motivoAnulacao" class="h-12 w-full rounded-[10px] border-linha-forte" placeholder="Motivo (obrigatório)" aria-label="Motivo da anulação">
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" class="h-14 rounded-[10px] border border-linha-forte bg-white text-base font-bold" @click="aAnular = false">Voltar</button>
                                    <button type="button" class="h-14 rounded-[10px] bg-perigo text-base font-bold text-white disabled:opacity-45" :disabled="motivoAnulacao.trim().length < 3 || acaoSenha.processing" @click="confirmarAnulacao">Confirmar anulação</button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <ChamarComissaoModal
            v-if="chamandoComissao"
            :operador-nome="posNome"
            @fechar="chamandoComissao = false"
        />
    </main>
</template>
