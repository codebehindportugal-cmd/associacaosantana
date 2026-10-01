<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import ChamarComissaoModal from '@/Components/ChamarComissaoModal.vue';
import ChamadaFuncionarioAlert from '@/Components/ChamadaFuncionarioAlert.vue';
import ComissaoChamadasAlert from '@/Components/ComissaoChamadasAlert.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import QRCode from 'qrcode';

const props = defineProps({ mesa: Object, pedido: Object, produtos: Object, mesasLivres: { type: Array, default: () => [] } });
const page = usePage();
const categoriaAtual = ref(Object.keys(props.produtos ?? {})[0] || '');
const separadorAtual = ref('produtos');
const pagamentoAberto = ref(false);
const metodo = ref('dinheiro');
const recebido = ref('');
const lugaresOcupados = ref(props.pedido ? '' : (props.mesa?.reserva_ativa?.pessoas ?? ''));
const letraSubmesaNova = ref('');
const carrinho = ref([]);
const aviso = ref('');
const qrAberto = ref(false);
const qrDataUrl = ref('');
const qrTitulo = ref('');
const qrSubtitulo = ref('');
const qrLink = ref('');
const lugaresAtuais = ref(props.pedido?.mesa?.capacidade ?? props.mesa?.capacidade ?? '');
const agora = ref(Date.now());
const novoForm = useForm({ lugares_ocupados: null, submesa_letra: null, mesas_grupo: null });
const mesasGrupo = ref('');
const mesasGrupoSet = computed(() => new Set(mesasGrupo.value.split(/\s+/).map(n => n.trim()).filter(Boolean)));
const toggleMesaGrupo = (numero) => {
    const nums = mesasGrupo.value.split(/\s+/).map(n => n.trim()).filter(Boolean);
    const str = String(numero);
    const idx = nums.indexOf(str);
    if (idx >= 0) nums.splice(idx, 1);
    else nums.push(str);
    mesasGrupo.value = nums.join(' ');
};
const capacidadeGrupoSelecionado = computed(() => {
    const extra = (props.mesasLivres ?? [])
        .filter(m => mesasGrupoSet.value.has(String(m.numero)))
        .reduce((sum, m) => sum + Math.min(10, Number(m.capacidade ?? 0)), 0);
    return capacidadeMesa.value + extra;
});
const submesaLetras = ['A', 'B', 'C', 'D'];
const itemForm = useForm({ items: [] });
const lugaresForm = useForm({ lugares_ocupados: lugaresAtuais.value });
const fecharForm = useForm({ metodo_pagamento: 'dinheiro', valor_recebido: 0, troco: 0 });
const obsForm = useForm({ observacoes: props.pedido?.observacoes ?? '' });
let avisoTimer;
let relogioAnulacaoTimer;
const categorias = computed(() => Object.keys(props.produtos ?? {}));
const lista = computed(() => props.produtos?.[categoriaAtual.value] ?? []);
const total = computed(() => (props.pedido?.items ?? []).reduce((s, i) => s + Number(i.preco_unitario) * i.quantidade, 0));
const totalCarrinho = computed(() => carrinho.value.reduce((s, item) => s + Number(item.preco) * Number(item.quantidade), 0));
const troco = computed(() => Math.max(0, Number(recebido.value || total.value) - total.value));
const mesaDividida = computed(() => !props.mesa?.mesa_principal_id && props.mesa?.submesas?.length > 0);
const podeEscolherLugares = computed(() => !props.pedido && !mesaDividida.value && Number(props.mesa?.capacidade ?? 0) > 1);
const pedidoAutor = computed(() => props.pedido?.operador_nome ?? props.pedido?.user?.name ?? props.pedido?.pos?.nome ?? 'Sem utilizador');
const erroItem = computed(() => page.props.errors?.item);
const podeSelfOrder = computed(() => props.pedido?.cliente_token && ['pendente', 'preparacao'].includes(props.pedido?.estado));
const clienteUrl = computed(() => podeSelfOrder.value ? route('cliente.mesa', props.pedido.cliente_token) : '');
const chamarUrl = computed(() => podeSelfOrder.value ? route('cliente.chamar.show', props.pedido.cliente_token) : '');
const precarioUrl = computed(() => route('precario'));
const capacidadeMesa = computed(() => Math.min(10, Number(props.mesa?.capacidade ?? 0)));
const lugaresNumero = computed(() => Number(lugaresOcupados.value || 0));
const extrairLetraSubmesa = (mesa) => {
    const base = String(mesa?.mesa_principal?.numero ?? props.mesa?.mesa_principal?.numero ?? props.mesa?.numero ?? '');
    const designacao = String(mesa?.designacao ?? mesa?.nome ?? '');

    return designacao.replace(new RegExp(`^Mesa\\s*${base}`, 'i'), '').trim().toUpperCase();
};
const letraSubmesaAtual = computed(() => props.mesa?.mesa_principal_id ? extrairLetraSubmesa(props.mesa) : '');
const letrasSubmesaUsadas = computed(() => (props.mesa?.submesas ?? [])
    .map((submesa) => extrairLetraSubmesa(submesa))
    .filter(Boolean));
const submesaLetrasDisponiveis = computed(() => submesaLetras.filter((letra) => {
    if (letra === letraSubmesaAtual.value) {
        return true;
    }

    return ! letrasSubmesaUsadas.value.includes(letra);
}));
const precisaSubmesa = computed(() => !props.pedido && lugaresNumero.value > 0 && lugaresNumero.value < capacidadeMesa.value);
const precisaMesasGrupo = computed(() => !props.pedido && lugaresNumero.value > capacidadeMesa.value);
// Na página de uma submesa, a letra é sempre obrigatória (é reserva parcial da mesa principal)
const precisaLetra = computed(() =>
    props.mesa?.mesa_principal_id
        ? (!props.pedido && lugaresNumero.value > 0)
        : (precisaSubmesa.value || precisaMesasGrupo.value)
);
const podeAbrirPedido = computed(() => !mesaDividida.value
    && lugaresNumero.value > 0
    && (!precisaLetra.value || letraSubmesaNova.value.trim())
    && (!precisaMesasGrupo.value || mesasGrupo.value.trim()));
const separadores = computed(() => [
    { key: 'conta', label: 'Conta', count: props.pedido?.items?.length ?? 0 },
    { key: 'produtos', label: 'Produtos', count: null },
    { key: 'envio', label: 'Envio', count: carrinho.value.reduce((soma, item) => soma + Number(item.quantidade), 0) },
    { key: 'qrs', label: 'QRs', count: podeSelfOrder.value ? 3 : 1 },
    { key: 'extras', label: 'Extras', count: null },
]);
const limiteAnulacaoMs = 2 * 60 * 1000;
const itemDentroPrazoAnulacao = (item) => {
    if (!item?.created_at) return false;

    const criadoEm = new Date(item.created_at).getTime();

    if (Number.isNaN(criadoEm)) return false;

    return agora.value - criadoEm <= limiteAnulacaoMs;
};
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const secaoClasse = (produto) => ({
    bebidas: 'bg-blue-600',
    frango: 'bg-red-700',
    cozinha: 'bg-orange-600',
    comida: 'bg-orange-600',
    acompanhamentos: 'bg-emerald-700',
    sobremesas: 'bg-purple-600',
}[produto.categoria?.secao] || 'bg-gray-700');

// Foto do produto (igual ao POS de pre-pagamento): inteira a direita,
// fundo escuro a esquerda para o nome e o preco se lerem bem.
const btnStyle = (produto) => {
    if (!produto.imagem) return null;
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
const nomeStyle = (produto) => (produto.imagem ? { maxWidth: '62%', textShadow: '0 1px 4px rgba(0,0,0,0.9)' } : null);
const abrirPedido = (mesa = props.mesa, lugares = lugaresOcupados.value) => {
    if (!podeAbrirPedido.value) return;
    novoForm.lugares_ocupados = lugares;
    novoForm.submesa_letra = letraSubmesaNova.value ? letraSubmesaNova.value.toUpperCase() : null;
    novoForm.mesas_grupo = mesasGrupo.value || null;
    novoForm.post(route('pos.rest.pedido.novo', mesa.id));
};
const mostrarAviso = (mensagem) => {
    aviso.value = mensagem;
    window.clearTimeout(avisoTimer);
    avisoTimer = window.setTimeout(() => {
        aviso.value = '';
    }, 3500);
};
const addProduto = (produto) => {
    if (!props.pedido) return;
    const existente = carrinho.value.find((item) => item.produto_id === produto.id && !item.observacoes);

    if (existente) {
        existente.quantidade += 1;
        mostrarAviso('Produto registado. No fim, abre Envio para validar e enviar o pedido.');
        return;
    }

    carrinho.value.push({
        produto_id: produto.id,
        nome: produto.nome,
        preco: produto.preco,
        quantidade: 1,
        prioridade: false,
        observacoes: '',
    });
    mostrarAviso('Produto registado. No fim, abre Envio para validar e enviar o pedido.');
};
const alterarQuantidadeCarrinho = (item, delta) => {
    item.quantidade += delta;
    if (item.quantidade <= 0) {
        carrinho.value = carrinho.value.filter((linha) => linha !== item);
    }
};
const enviarPedido = () => {
    if (!props.pedido || !carrinho.value.length) return;
    mostrarAviso('A validar e enviar o pedido...');
    itemForm.items = carrinho.value.map((item) => ({
        produto_id: item.produto_id,
        quantidade: item.quantidade,
        prioridade: item.prioridade,
        observacoes: item.observacoes || '',
    }));
    itemForm.post(route('pos.rest.pedido.items', props.pedido.id), {
        preserveScroll: true,
        onSuccess: () => {
            carrinho.value = [];
            itemForm.reset();
            mostrarAviso('Pedido validado e enviado.');
        },
        onError: () => mostrarAviso('Nao foi possivel enviar o pedido. Confirma os produtos e tenta novamente.'),
    });
};
// `confirmado` = já confirmado no diálogo do ecrã (sem confirm() nativo)
const cancelarPedido = (confirmado = false) => {
    if (!props.pedido || (confirmado !== true && !confirm('Cancelar este pedido e libertar a mesa?'))) return;
    mostrarAviso('A cancelar pedido...');
    router.patch(route('pos.rest.pedido.estado', props.pedido.id), { estado: 'cancelado' }, {
        preserveScroll: true,
        onSuccess: () => mostrarAviso('Pedido cancelado. A mesa foi libertada.'),
        onError: () => mostrarAviso('Nao foi possivel cancelar o pedido. Tenta novamente.'),
    });
};
const remover = (item) => {
    if (!itemDentroPrazoAnulacao(item)) {
        mostrarAviso('Este item ja so pode ser anulado no backoffice.');
        return;
    }

    router.delete(route('pos.rest.pedido.item.remover', [props.pedido.id, item.id]), {
        preserveScroll: true,
        onError: () => mostrarAviso('Este item ja so pode ser anulado no backoffice.'),
    });
};
const urgente = (item) => router.patch(route('pos.rest.pedido.item.urgente', [props.pedido.id, item.id]), {}, { preserveScroll: true });
const atualizarLugares = () => {
    if (!props.pedido) return;
    lugaresForm.lugares_ocupados = lugaresAtuais.value;
    lugaresForm.patch(route('pos.rest.pedido.lugares', props.pedido.id), { preserveScroll: true });
};
const guardarObservacoes = () => {
    if (!props.pedido) return;
    obsForm.patch(route('pos.rest.pedido.observacoes', props.pedido.id), { preserveScroll: true });
};
const fechar = () => {
    fecharForm.metodo_pagamento = metodo.value;
    fecharForm.valor_recebido = recebido.value || total.value;
    fecharForm.troco = troco.value;
    fecharForm.patch(route('pos.rest.pedido.fechar', props.pedido.id));
};
const tecla = (valor) => { if (valor === 'del') recebido.value = String(recebido.value).slice(0, -1); else recebido.value = String(recebido.value) + valor; };
const mostrarQr = async () => {
    if (!clienteUrl.value) return;
    qrTitulo.value = 'Self-Order do Cliente';
    qrSubtitulo.value = `Mesa ${props.mesa.numero}`;
    qrLink.value = clienteUrl.value;
    qrDataUrl.value = await QRCode.toDataURL(qrLink.value, { width: 420, margin: 2 });
    qrAberto.value = true;
};
const mostrarQrPrecario = async () => {
    qrTitulo.value = 'Preçário';
    qrSubtitulo.value = 'Produtos e preços disponíveis';
    qrLink.value = precarioUrl.value;
    qrDataUrl.value = await QRCode.toDataURL(qrLink.value, { width: 420, margin: 2 });
    qrAberto.value = true;
};
const mostrarQrFuncionario = async () => {
    if (!chamarUrl.value) return;
    qrTitulo.value = 'Chamar Funcionário';
    qrSubtitulo.value = `Mesa ${props.mesa.numero}`;
    qrLink.value = chamarUrl.value;
    qrDataUrl.value = await QRCode.toDataURL(qrLink.value, { width: 420, margin: 2 });
    qrAberto.value = true;
};
const copiarLinkCliente = async () => {
    const link = qrLink.value || clienteUrl.value;

    if (navigator?.clipboard && link) {
        await navigator.clipboard.writeText(link);
    }
};
const extraForm = useForm({ descricao: '' });
const chamandoComissao = ref(false);
const itensPedidoExtra = [
    { label: 'Guardanapos', emoji: '🧻' },
    { label: 'Loiça', emoji: '🍽️' },
    { label: 'Molho', emoji: '🫙' },
    { label: 'Tempero', emoji: '🧂' },
    { label: 'Limpar mesa', emoji: '🧹' },
];
const pedidoExtra = (descricao) => {
    extraForm.descricao = descricao;
    extraForm.post(route('pos.rest.pedido.extra', props.mesa.id), {
        preserveScroll: true,
        onSuccess: () => mostrarAviso('Pedido enviado: ' + descricao),
        onError: () => mostrarAviso('Nao foi possivel enviar o pedido.'),
    });
};
onMounted(() => {
    relogioAnulacaoTimer = window.setInterval(() => {
        agora.value = Date.now();
    }, 10000);
});
onBeforeUnmount(() => {
    window.clearInterval(relogioAnulacaoTimer);
    window.clearTimeout(avisoTimer);
});

// ---------------------------------------------------------------------------
// Estado e formatação só para a UI do redesign (não muda a lógica acima)
// ---------------------------------------------------------------------------
const modal = ref(null); // 'qrs' | 'extras' | 'cancelar'
const abaPedido = computed(() => (separadorAtual.value === 'conta' ? 'conta' : 'envio'));
const irPara = (aba) => { separadorAtual.value = aba; };
const eur = (v) => Number(v ?? 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const secoesInfo = {
    cozinha: { label: 'Cozinha', dot: 'bg-secao-cozinha', text: 'text-secao-cozinha', chip: 'border-secao-cozinha/40 text-secao-cozinha' },
    comida: { label: 'Comida', dot: 'bg-secao-grelhados', text: 'text-secao-grelhados', chip: 'border-secao-grelhados/40 text-secao-grelhados' },
    frango: { label: 'Frango', dot: 'bg-secao-grelhados', text: 'text-secao-grelhados', chip: 'border-secao-grelhados/40 text-secao-grelhados' },
    bebidas: { label: 'Bebidas', dot: 'bg-secao-bar', text: 'text-secao-bar', chip: 'border-secao-bar/40 text-secao-bar' },
    acompanhamentos: { label: 'Acompanhamentos', dot: 'bg-secao-acompanhamentos', text: 'text-secao-acompanhamentos', chip: 'border-secao-acompanhamentos/40 text-secao-acompanhamentos' },
    sobremesas: { label: 'Sobremesas', dot: 'bg-secao-sobremesas', text: 'text-secao-sobremesas', chip: 'border-secao-sobremesas/40 text-secao-sobremesas' },
    servico: { label: 'Serviço', dot: 'bg-secao-servico', text: 'text-secao-servico', chip: 'border-secao-servico/40 text-secao-servico' },
};
const secaoDe = (produto) => secoesInfo[produto?.categoria?.secao] ?? { label: produto?.categoria?.nome ?? 'Outros', dot: 'bg-suave', text: 'text-suave', chip: 'border-linha-forte text-suave' };
const corCategoria = (cat) => secaoDe((props.produtos?.[cat] ?? [])[0]).dot;
const produtosPorId = computed(() => Object.fromEntries(Object.values(props.produtos ?? {}).flat().map((p) => [p.id, p])));
const qtdNoCarrinho = (produtoId) => carrinho.value.filter((i) => i.produto_id === produtoId).reduce((s, i) => s + Number(i.quantidade), 0);
const artigosCarrinho = computed(() => carrinho.value.reduce((s, i) => s + Number(i.quantidade), 0));
const rotasCarrinho = computed(() => Object.values(carrinho.value.reduce((acc, item) => {
    const info = secaoDe(produtosPorId.value[item.produto_id]);
    acc[info.label] ??= { ...info, count: 0 };
    acc[info.label].count += Number(item.quantidade);
    return acc;
}, {})));
const adicionarProduto = (produto) => {
    addProduto(produto);
    if (props.pedido) separadorAtual.value = 'envio';
};
const alternarObservacao = (item) => { item.obsAberta = !item.obsAberta; };
const limparCarrinho = () => { carrinho.value = []; };
const estadoPedidoLabel = computed(() => ({ pendente: 'Pendente', preparacao: 'Em preparação', pronto: 'Pronto' }[props.pedido?.estado] ?? props.pedido?.estado ?? ''));
const haQuanto = (item) => {
    const t = new Date(item?.created_at ?? '').getTime();
    if (Number.isNaN(t)) return '';
    const min = Math.floor((agora.value - t) / 60000);
    return min <= 0 ? 'agora mesmo' : `há ${min} min`;
};
const artigosConta = computed(() => (props.pedido?.items ?? []).reduce((s, i) => s + Number(i.quantidade), 0));
const resumoConta = computed(() => Object.values((props.pedido?.items ?? []).reduce((acc, item) => {
    const chave = `${item.produto_id}|${item.preco_unitario}`;
    acc[chave] ??= { chave, nome: item.produto?.nome ?? 'Produto', quantidade: 0, total: 0 };
    acc[chave].quantidade += Number(item.quantidade);
    acc[chave].total += Number(item.quantidade) * Number(item.preco_unitario);
    return acc;
}, {})));
const mudarLugaresAbrir = (delta) => { lugaresOcupados.value = Math.min(80, Math.max(1, Number(lugaresOcupados.value || 0) + delta)); };
const mudarLugaresMesa = (delta) => { lugaresAtuais.value = Math.max(1, Number(lugaresAtuais.value || 0) + delta); };
const textoCapacidade = computed(() => {
    if (precisaMesasGrupo.value) return `Mais de ${capacidadeMesa.value} pessoas: junta mesas livres ao grupo.`;
    if (precisaSubmesa.value) return `Menos de ${capacidadeMesa.value} pessoas: a mesa é dividida e fica uma submesa.`;
    return `A mesa tem ${capacidadeMesa.value} lugares.`;
});

// Estado "A pagar": pedido aberto em que já se pediu a conta e ainda não foi pago
const contaPedida = computed(() => !!props.pedido?.conta_pedida_em);
const horaContaPedida = computed(() => contaPedida.value
    ? new Date(props.pedido.conta_pedida_em).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' })
    : '');
const pedirConta = (pedida = true) => {
    if (!props.pedido) return;
    router.patch(route('pos.rest.pedido.pedir-conta', props.pedido.id), { pedida }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => mostrarAviso('Nao foi possivel atualizar o pedido de conta.'),
    });
};
const abrirPagamento = () => {
    pagamentoAberto.value = true;
    // Carregar em "Fechar conta" também deixa a mesa "A pagar" até o pagamento ser confirmado
    if (!contaPedida.value) pedirConta(true);
};
const estadoMesa = computed(() => {
    if (!props.pedido) return { label: 'Livre', classe: 'bg-escuro-2' };
    if (contaPedida.value) return { label: 'A pagar', classe: 'bg-laranja' };
    return { label: 'Ocupada', classe: 'bg-verde' };
});
const metodosPagamento = [
    { valor: 'dinheiro', label: 'Dinheiro' },
    { valor: 'mbway', label: 'MB WAY' },
    { valor: 'multibanco', label: 'Multibanco' },
];
const escolherMetodo = (valor) => {
    metodo.value = valor;
    if (valor !== 'dinheiro') recebido.value = '';
};
const pessoasNaMesa = computed(() => {
    const m = props.pedido?.mesa ?? props.mesa;
    if (m?.lugares_inicio && m?.lugares_fim) return Number(m.lugares_fim) - Number(m.lugares_inicio) + 1;
    return m?.capacidade ?? null;
});
const valoresRapidos = computed(() => [5, 10, 20, 50, 100, 200].filter((v) => v > total.value).slice(0, 3));
const recebidoFalta = computed(() => recebido.value !== '' && Number(recebido.value) < total.value);
const visorRecebido = computed(() => (recebido.value === '' ? eur(total.value) : String(recebido.value).replace('.', ',') + ' €'));
const teclasPagamento = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', 'del'];
const confirmarCancelar = () => {
    modal.value = null;
    cancelarPedido(true);
};
</script>

<template>
    <ChamadaFuncionarioAlert />
    <ComissaoChamadasAlert />
    <main class="flex min-h-screen w-full max-w-[100vw] flex-col overflow-x-hidden bg-fundo font-sans text-tinta tabular-nums lg:h-screen lg:overflow-hidden">
        <!-- Barra de topo -->
        <header class="flex shrink-0 flex-wrap items-center justify-between gap-2 bg-escuro px-4 py-2.5 text-white sm:px-6 lg:h-16 lg:flex-nowrap lg:py-0">
            <div class="flex min-w-0 items-center gap-3">
                <Link :href="route('pos.rest.mesas')" class="flex h-11 shrink-0 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white hover:text-white">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    Mesas
                </Link>
                <h1 class="flex h-11 min-w-0 items-center truncate rounded-[10px] px-4 text-[15px] font-bold" :class="estadoMesa.classe">
                    <span class="truncate">{{ mesa.designacao || `Mesa ${mesa.numero}` }}<template v-if="mesa.localizacao"> · {{ mesa.localizacao }}</template> · {{ estadoMesa.label }}<template v-if="pedido && pessoasNaMesa"> · {{ pessoasNaMesa }} pessoas</template></span>
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white disabled:cursor-not-allowed disabled:opacity-45" :disabled="!pedido" @click="modal = 'qrs'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><path d="M14 14h3v3M21 14v7h-7" /></svg>
                    QRs
                </button>
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white disabled:cursor-not-allowed disabled:opacity-45" :disabled="!pedido" @click="modal = 'extras'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6" /><rect x="6" y="14" width="12" height="7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /></svg>
                    Pedidos extra
                </button>
                <button type="button" class="flex h-11 items-center rounded-[10px] bg-laranja px-4 text-[15px] font-bold text-white" @click="chamandoComissao = true">Chamar comissão</button>
            </div>
        </header>

        <div class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_420px]">
            <!-- Produtos -->
            <section class="flex min-h-0 min-w-0 flex-col gap-3 p-4 sm:p-6 lg:overflow-hidden" :class="pedido ? '' : 'order-2 lg:order-none'">
                <div v-if="aviso || page.props.flash?.success" role="status" class="shrink-0 rounded-[10px] border border-verde/30 bg-verde-claro px-4 py-3 text-[15px] font-semibold text-verde-escuro">
                    {{ aviso || page.props.flash.success }}
                </div>
                <div v-if="!pedido" class="shrink-0 rounded-[10px] border border-linha bg-white px-4 py-3 text-[15px] font-semibold text-suave">
                    Abre o pedido para adicionar produtos.
                </div>
                <nav aria-label="Categorias" class="flex shrink-0 gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="cat in categorias"
                        :key="cat"
                        type="button"
                        class="flex h-14 shrink-0 items-center gap-2 whitespace-nowrap rounded-[10px] border px-4 text-base font-bold"
                        :class="cat === categoriaAtual ? 'border-escuro bg-escuro text-white' : 'border-linha bg-white text-tinta'"
                        :aria-pressed="cat === categoriaAtual"
                        @click="categoriaAtual = cat"
                    >
                        <span class="h-2.5 w-2.5 rounded-full" :class="corCategoria(cat)"></span>{{ cat }}
                    </button>
                </nav>
                <div class="grid min-h-0 auto-rows-min grid-cols-2 gap-3 overflow-y-auto pb-2 sm:grid-cols-3 xl:grid-cols-4" :class="pedido ? '' : 'opacity-45'">
                    <button
                        v-for="produto in lista"
                        :key="produto.id"
                        type="button"
                        class="relative flex min-h-[124px] min-w-0 flex-col items-start overflow-hidden rounded-[14px] bg-white p-4 text-left disabled:cursor-not-allowed"
                        :class="qtdNoCarrinho(produto.id) ? 'border-2 border-verde' : 'border border-linha'"
                        :disabled="!pedido"
                        @click="adicionarProduto(produto)"
                    >
                        <img v-if="produto.imagem" :src="`/storage/${produto.imagem}`" alt="" class="pointer-events-none absolute bottom-2 right-2 h-14 w-14 rounded-lg object-contain opacity-90">
                        <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider" :class="secaoDe(produto).text">
                            <span class="h-2 w-2 rounded-full" :class="secaoDe(produto).dot"></span>{{ secaoDe(produto).label }}
                        </span>
                        <span class="mt-2 block break-words pr-8 text-lg font-bold leading-tight" :class="produto.imagem ? 'pr-16' : ''">{{ produto.nome }}</span>
                        <span class="mt-auto pt-3 text-base font-medium text-suave">{{ eur(produto.preco) }}</span>
                        <span v-if="qtdNoCarrinho(produto.id)" class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-verde text-base font-bold text-white">{{ qtdNoCarrinho(produto.id) }}</span>
                    </button>
                </div>
            </section>

            <!-- Painel lateral do pedido -->
            <aside aria-label="Pedido" class="flex min-h-0 min-w-0 flex-col border-linha bg-white lg:border-l" :class="pedido ? 'border-t lg:border-t-0' : 'order-1 border-b lg:order-none lg:border-b-0'">
                <!-- Abrir pedido -->
                <div v-if="!pedido" class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-5">
                    <div>
                        <h2 class="text-2xl font-extrabold">Abrir pedido · {{ mesa.designacao || `Mesa ${mesa.numero}` }}</h2>
                        <p class="mt-1 text-[15px] text-suave">Antes de escolher produtos, abre o pedido com o número de pessoas.</p>
                    </div>
                    <template v-if="!mesaDividida">
                        <div v-if="mesa.reserva_ativa" class="rounded-[10px] border border-azul/30 bg-[#EAF0FB] px-4 py-3 text-[15px] font-semibold text-[#1E4290]">
                            Reserva: <span class="font-bold text-tinta">{{ mesa.reserva_ativa.nome }}</span> — {{ mesa.reserva_ativa.pessoas }} pessoas
                        </div>
                        <div>
                            <span class="text-sm font-semibold text-suave">Número de pessoas</span>
                            <div class="mt-2 flex items-center gap-3">
                                <button type="button" aria-label="Menos uma pessoa" class="h-14 w-14 shrink-0 rounded-[10px] border border-linha-forte bg-fundo text-3xl font-bold" @click="mudarLugaresAbrir(-1)">−</button>
                                <input v-model="lugaresOcupados" type="number" min="1" max="80" inputmode="numeric" aria-label="Número de pessoas" class="h-14 w-full min-w-0 rounded-[10px] border-linha-forte bg-white text-center text-3xl font-extrabold focus:border-verde focus:ring-verde" placeholder="0">
                                <button type="button" aria-label="Mais uma pessoa" class="h-14 w-14 shrink-0 rounded-[10px] border border-linha-forte bg-fundo text-3xl font-bold" @click="mudarLugaresAbrir(1)">+</button>
                            </div>
                            <span class="mt-2 block text-sm text-suave">{{ textoCapacidade }}</span>
                        </div>
                        <div v-if="precisaMesasGrupo">
                            <label class="text-sm font-semibold text-suave" for="mesas-grupo">Mesas do grupo</label>
                            <input id="mesas-grupo" v-model="mesasGrupo" type="text" class="mt-2 h-12 w-full rounded-[10px] border-linha-forte text-lg font-bold focus:border-verde focus:ring-verde" placeholder="Ex.: 32 33 34">
                            <div v-if="mesasLivres.length" class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="m in mesasLivres"
                                    :key="m.id"
                                    type="button"
                                    class="h-12 rounded-[10px] px-4 text-base font-bold"
                                    :class="mesasGrupoSet.has(String(m.numero)) ? 'border-[3px] border-laranja bg-laranja-claro text-laranja-texto' : 'border border-linha-forte bg-white'"
                                    :aria-pressed="mesasGrupoSet.has(String(m.numero))"
                                    @click="toggleMesaGrupo(m.numero)"
                                >{{ m.numero }} <span class="font-medium text-suave">({{ Math.min(10, Number(m.capacidade)) }}p)</span></button>
                            </div>
                            <p class="mt-2 text-sm font-semibold" :class="mesasGrupoSet.size > 0 && lugaresNumero > 0 && capacidadeGrupoSelecionado < lugaresNumero ? 'text-perigo' : 'text-verde-escuro'">
                                <template v-if="mesasGrupoSet.size > 0 && lugaresNumero > 0">Capacidade total: {{ capacidadeGrupoSelecionado }} lugares {{ capacidadeGrupoSelecionado >= lugaresNumero ? '— chega' : '— precisa de mais mesas' }}</template>
                                <template v-else>Toca nas mesas livres a juntar.</template>
                            </p>
                        </div>
                        <div v-if="precisaLetra">
                            <span class="text-sm font-semibold text-suave">{{ precisaMesasGrupo ? 'Letra do grupo' : 'Letra da submesa' }}</span>
                            <div role="group" :aria-label="precisaMesasGrupo ? 'Letra do grupo' : 'Letra da submesa'" class="mt-2 grid grid-cols-4 gap-2">
                                <button
                                    v-for="letra in submesaLetras"
                                    :key="letra"
                                    type="button"
                                    class="h-14 rounded-[10px] text-xl font-extrabold disabled:cursor-not-allowed disabled:opacity-45"
                                    :class="letraSubmesaNova === letra ? 'border-[3px] border-verde bg-verde-claro text-verde-escuro' : 'border border-linha-forte bg-white'"
                                    :aria-pressed="letraSubmesaNova === letra"
                                    :disabled="!submesaLetrasDisponiveis.includes(letra)"
                                    @click="letraSubmesaNova = letra"
                                >{{ letra }}</button>
                            </div>
                        </div>
                    </template>

                    <div v-if="mesa.submesas?.length">
                        <span class="text-sm font-semibold text-suave">Submesas</span>
                        <div class="mt-2 grid gap-2">
                            <Link
                                v-for="submesa in mesa.submesas"
                                :key="submesa.id"
                                :href="route('pos.rest.mesa', submesa.id)"
                                class="flex min-h-14 items-center justify-between gap-3 rounded-[10px] px-4 py-2 text-base font-bold"
                                :class="submesa.estado === 'ocupada' ? 'bg-verde text-white hover:text-white' : 'border border-linha-forte bg-white text-tinta hover:text-tinta'"
                            >
                                <span>{{ submesa.designacao }} · {{ submesa.capacidade }} lugares</span>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider" :class="submesa.estado === 'ocupada' ? 'bg-white/20' : 'bg-fundo text-suave'">{{ submesa.estado === 'ocupada' ? 'Ocupada' : (submesa.estado === 'livre' ? 'Livre' : submesa.estado) }}</span>
                            </Link>
                        </div>
                    </div>

                    <div v-for="erro in [novoForm.errors.mesa_id, novoForm.errors.submesa_letra, novoForm.errors.lugares_ocupados, novoForm.errors.mesas_grupo].filter(Boolean)" :key="erro" role="alert" class="rounded-[10px] bg-perigo-claro px-4 py-3 text-[15px] font-semibold text-perigo-texto">{{ erro }}</div>

                    <div class="flex-1"></div>
                    <button v-if="!mesaDividida" type="button" class="h-16 w-full shrink-0 rounded-[10px] bg-verde text-xl font-bold text-white hover:bg-verde-escuro disabled:cursor-not-allowed disabled:opacity-45" :disabled="!podeAbrirPedido || novoForm.processing" @click="abrirPedido()">
                        {{ novoForm.processing ? 'A abrir…' : 'Abrir pedido' }}
                    </button>
                </div>

                <!-- Pedido aberto -->
                <template v-else>
                    <div role="tablist" aria-label="Pedido" class="grid shrink-0 grid-cols-2 gap-2 border-b border-linha p-3">
                        <button type="button" role="tab" :aria-selected="abaPedido === 'envio'" class="flex h-[52px] items-center justify-center gap-2 rounded-[10px] border text-base font-bold" :class="abaPedido === 'envio' ? 'border-escuro bg-escuro text-white' : 'border-linha-forte bg-white text-tinta'" @click="irPara('envio')">
                            Para enviar <span v-if="artigosCarrinho" class="flex h-6 min-w-6 items-center justify-center rounded-full bg-verde px-1.5 text-sm text-white">{{ artigosCarrinho }}</span>
                        </button>
                        <button type="button" role="tab" :aria-selected="abaPedido === 'conta'" class="flex h-[52px] items-center justify-center gap-2 rounded-[10px] border text-base font-bold" :class="abaPedido === 'conta' ? 'border-escuro bg-escuro text-white' : 'border-linha-forte bg-white text-tinta'" @click="irPara('conta')">
                            Conta <span class="font-semibold" :class="abaPedido === 'conta' ? 'text-white/80' : 'text-suave'">{{ eur(total) }}</span>
                        </button>
                    </div>

                    <!-- Para enviar -->
                    <template v-if="abaPedido === 'envio'">
                        <div class="min-h-0 flex-1 overflow-y-auto px-5">
                            <p v-if="!carrinho.length" class="py-10 text-center text-[15px] text-suave">Toque num produto para o adicionar.</p>
                            <div v-for="(item, index) in carrinho" :key="`${item.produto_id}-${index}`" class="border-b border-linha-fraca py-3">
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        <span class="block truncate text-[17px] font-bold">{{ item.nome }}</span>
                                        <span class="text-sm font-semibold" :class="secaoDe(produtosPorId[item.produto_id]).text">→ {{ secaoDe(produtosPorId[item.produto_id]).label }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" aria-label="Retirar um" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo" @click="alterarQuantidadeCarrinho(item, -1)">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                                        </button>
                                        <span class="w-7 text-center text-lg font-bold">{{ item.quantidade }}</span>
                                        <button type="button" aria-label="Adicionar mais um" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-linha-forte bg-fundo" @click="alterarQuantidadeCarrinho(item, 1)">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                        </button>
                                    </div>
                                    <span class="w-20 text-right text-base font-bold">{{ eur(item.preco * item.quantidade) }}</span>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" class="h-11 rounded-[10px] px-3 text-sm font-bold" :class="item.prioridade ? 'border-2 border-laranja bg-laranja-claro text-laranja-texto' : 'border border-linha-forte bg-white'" :aria-pressed="!!item.prioridade" @click="item.prioridade = !item.prioridade">A terminar</button>
                                    <button type="button" class="h-11 max-w-full truncate rounded-[10px] border border-linha-forte bg-white px-3 text-sm font-bold" :aria-pressed="!!item.obsAberta" @click="alternarObservacao(item)">
                                        {{ item.obsAberta ? 'Esconder observação' : (item.observacoes ? 'Observação: ' + item.observacoes : 'Observação') }}
                                    </button>
                                </div>
                                <label v-if="item.obsAberta" class="mt-2 block text-sm font-semibold text-suave">Observações
                                    <input v-model="item.observacoes" type="text" maxlength="255" class="mt-1 h-12 w-full rounded-[10px] border-linha-forte bg-fundo text-base text-tinta focus:border-verde focus:ring-verde" placeholder="Sem cebola, bem passado...">
                                </label>
                            </div>
                        </div>
                        <div class="shrink-0 border-t border-linha bg-fundo p-5">
                            <div v-if="rotasCarrinho.length" class="mb-2 flex flex-wrap items-center gap-1.5 text-sm">
                                <span class="font-semibold text-suave">Vai para:</span>
                                <span v-for="r in rotasCarrinho" :key="r.label" class="rounded-full border bg-white px-2.5 py-0.5 text-sm font-semibold" :class="r.chip">{{ r.label }} ({{ r.count }})</span>
                            </div>
                            <div class="flex items-end justify-between gap-2">
                                <span class="text-[15px] text-suave">{{ artigosCarrinho }} artigos por enviar</span>
                                <span class="text-3xl font-extrabold">{{ eur(totalCarrinho) }}</span>
                            </div>
                            <div v-if="itemForm.errors.items" role="alert" class="mt-2 rounded-[10px] bg-perigo-claro p-3 text-sm font-semibold text-perigo-texto">{{ itemForm.errors.items }}</div>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <button type="button" class="h-16 rounded-[10px] border border-linha-forte bg-white text-lg font-bold text-perigo disabled:cursor-not-allowed disabled:opacity-45" :disabled="!carrinho.length" @click="limparCarrinho">Limpar</button>
                                <button type="button" class="h-16 rounded-[10px] bg-verde text-lg font-bold text-white hover:bg-verde-escuro disabled:cursor-not-allowed disabled:opacity-45" :disabled="!carrinho.length || itemForm.processing" @click="enviarPedido">
                                    {{ itemForm.processing ? 'A enviar…' : 'Enviar pedido' }}
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- Conta -->
                    <template v-else>
                        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="rounded-full px-3 py-1 text-sm font-bold" :class="contaPedida ? 'bg-laranja-claro text-laranja-texto' : 'bg-verde-claro text-verde-escuro'">{{ contaPedida ? `A pagar · conta pedida às ${horaContaPedida}` : estadoPedidoLabel }}</span>
                                <span class="text-sm text-suave">Pedido feito por <strong class="text-tinta">{{ pedidoAutor }}</strong></span>
                            </div>
                            <div v-if="pedido.nome_reserva" class="rounded-[10px] border border-azul/30 bg-[#EAF0FB] px-3 py-2 text-sm font-semibold text-[#1E4290]">Reserva: {{ pedido.nome_reserva }}</div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="mr-auto text-sm font-semibold text-suave">Pessoas na mesa</span>
                                <button type="button" aria-label="Menos uma pessoa" class="h-11 w-11 rounded-[10px] border border-linha-forte bg-fundo text-xl font-bold" @click="mudarLugaresMesa(-1)">−</button>
                                <input v-model="lugaresAtuais" type="number" min="1" aria-label="Pessoas na mesa" class="h-11 w-16 rounded-[10px] border-linha-forte text-center text-lg font-bold focus:border-verde focus:ring-verde">
                                <button type="button" aria-label="Mais uma pessoa" class="h-11 w-11 rounded-[10px] border border-linha-forte bg-fundo text-xl font-bold" @click="mudarLugaresMesa(1)">+</button>
                                <button type="button" class="h-11 rounded-[10px] border border-linha-forte bg-white px-3 text-sm font-bold disabled:opacity-45" :disabled="lugaresForm.processing" @click="atualizarLugares">Atualizar</button>
                            </div>
                            <div v-if="lugaresForm.errors.lugares_ocupados" role="alert" class="rounded-[10px] bg-perigo-claro p-2 text-sm font-semibold text-perigo-texto">{{ lugaresForm.errors.lugares_ocupados }}</div>
                            <label class="block text-sm font-semibold text-suave">Observações da mesa
                                <span class="mt-1 flex gap-2">
                                    <input v-model="obsForm.observacoes" type="text" maxlength="500" class="h-11 min-w-0 flex-1 rounded-[10px] border-linha-forte bg-laranja-claro text-[15px] text-tinta focus:border-laranja focus:ring-laranja" placeholder="Ex: Banda não foi paga, Desconto especial, NIF...">
                                    <button type="button" class="h-11 shrink-0 rounded-[10px] border border-linha-forte bg-white px-3 text-sm font-bold text-tinta disabled:opacity-45" :disabled="obsForm.processing" @click="guardarObservacoes">Guardar</button>
                                </span>
                            </label>
                            <div v-if="erroItem" role="alert" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-semibold text-perigo-texto">{{ erroItem }}</div>
                            <div class="divide-y divide-linha-fraca border-y border-linha-fraca">
                                <div v-for="item in pedido.items" :key="item.id" class="py-2.5" :class="item.prioridade ? 'border-l-4 border-l-laranja pl-2' : ''">
                                    <div class="flex items-center gap-2">
                                        <div class="min-w-0 flex-1">
                                            <span class="block truncate text-base font-bold">{{ item.quantidade }} × {{ item.produto?.nome }}</span>
                                            <span class="text-xs text-suave">{{ eur(item.preco_unitario) }} · {{ haQuanto(item) }}</span>
                                        </div>
                                        <button v-if="itemDentroPrazoAnulacao(item)" type="button" aria-label="Retirar um (só nos primeiros 2 minutos)" class="h-11 w-12 rounded-[10px] bg-perigo-claro text-base font-bold text-perigo-texto" @click="remover(item)">−1</button>
                                        <button type="button" class="h-11 rounded-[10px] px-2.5 text-xs font-bold" :class="item.prioridade ? 'border-2 border-laranja bg-laranja-claro text-laranja-texto' : 'border border-linha-forte bg-white'" :aria-pressed="!!item.prioridade" @click="urgente(item)">A terminar</button>
                                        <span class="w-[72px] text-right text-base font-bold">{{ eur(item.quantidade * item.preco_unitario) }}</span>
                                    </div>
                                    <span v-if="item.observacoes" class="mt-1.5 inline-block rounded-md bg-laranja-claro px-2 py-1 text-sm font-semibold text-laranja-texto">{{ item.observacoes }}</span>
                                </div>
                            </div>
                            <p class="text-xs text-suave">Um artigo só pode ser retirado aqui nos primeiros 2 minutos. Depois disso, só no backoffice.</p>
                        </div>
                        <div class="shrink-0 border-t border-linha bg-fundo p-5">
                            <div class="flex items-end justify-between gap-2">
                                <span class="text-[15px] text-suave">Total da mesa</span>
                                <span class="text-3xl font-extrabold">{{ eur(total) }}</span>
                            </div>
                            <span v-if="carrinho.length" class="mt-2 block rounded-[10px] bg-laranja-claro px-3 py-2 text-sm font-semibold text-laranja-texto">Há {{ artigosCarrinho }} artigos por enviar. Envia-os antes de fechar a conta.</span>
                            <button
                                type="button"
                                class="mt-3 h-14 w-full rounded-[10px] border-2 text-base font-bold"
                                :class="contaPedida ? 'border-linha-forte bg-white text-tinta' : 'border-laranja bg-laranja-claro text-laranja-texto'"
                                :aria-pressed="contaPedida"
                                @click="pedirConta(!contaPedida)"
                            >{{ contaPedida ? 'Anular pedido de conta (volta a Ocupada)' : 'Pedir conta (mesa fica A pagar)' }}</button>
                            <div class="mt-3 grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3">
                                <button type="button" class="h-16 rounded-[10px] border border-linha-forte bg-white text-base font-bold text-perigo" @click="modal = 'cancelar'">Cancelar pedido</button>
                                <button type="button" class="h-16 rounded-[10px] bg-laranja text-lg font-bold text-white" @click="abrirPagamento">Fechar conta</button>
                            </div>
                        </div>
                    </template>
                </template>
            </aside>
        </div>

        <ChamarComissaoModal
            v-if="chamandoComissao"
            :operador-nome="pedidoAutor"
            @fechar="chamandoComissao = false"
        />

        <!-- Modal: QRs da mesa -->
        <div v-if="modal === 'qrs'" class="fixed inset-0 z-50 flex items-center justify-center bg-escuro/60 p-4" @click.self="modal = null">
            <div role="dialog" aria-label="QRs da mesa" class="max-h-[92dvh] w-full max-w-4xl overflow-y-auto rounded-[14px] bg-white p-5 sm:p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="text-2xl font-extrabold">QRs · Mesa {{ mesa.numero }}</h2>
                    <button type="button" class="h-11 rounded-[10px] border border-linha-forte px-4 font-bold" @click="modal = null">Fechar</button>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <article v-if="podeSelfOrder" class="flex flex-col rounded-[14px] border border-linha p-4">
                        <h3 class="text-lg font-bold">Self-order do cliente</h3>
                        <p class="mt-1 flex-1 text-sm text-suave">Mostra o QR ao cliente para adicionar itens pelo telemóvel.</p>
                        <button type="button" class="mt-3 h-14 rounded-[10px] bg-verde font-bold text-white hover:bg-verde-escuro" @click="modal = null; mostrarQr()">Mostrar QR ao cliente</button>
                        <button type="button" class="mt-2 h-11 rounded-[10px] border border-linha-forte font-bold" @click="qrLink = clienteUrl; copiarLinkCliente()">Copiar link</button>
                    </article>
                    <article v-if="podeSelfOrder" class="flex flex-col rounded-[14px] border border-linha p-4">
                        <h3 class="text-lg font-bold">Chamar funcionário</h3>
                        <p class="mt-1 flex-1 text-sm text-suave">QR para o cliente chamar um funcionário à mesa, sem fazer pedido.</p>
                        <button type="button" class="mt-3 h-14 rounded-[10px] bg-verde font-bold text-white hover:bg-verde-escuro" @click="modal = null; mostrarQrFuncionario()">Mostrar QR chamar</button>
                    </article>
                    <article class="flex flex-col rounded-[14px] border border-linha p-4">
                        <h3 class="text-lg font-bold">Preçário do site</h3>
                        <p class="mt-1 flex-1 text-sm text-suave">Mostra o QR para o cliente consultar produtos e preços.</p>
                        <button type="button" class="mt-3 h-14 rounded-[10px] bg-verde font-bold text-white hover:bg-verde-escuro" @click="modal = null; mostrarQrPrecario()">Mostrar QR preçário</button>
                    </article>
                </div>
            </div>
        </div>

        <!-- Modal: pedidos extra -->
        <div v-if="modal === 'extras'" class="fixed inset-0 z-50 flex items-center justify-center bg-escuro/60 p-4" @click.self="modal = null">
            <div role="dialog" aria-label="Pedidos extra" class="w-full max-w-2xl rounded-[14px] bg-white p-5 sm:p-6">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-2xl font-extrabold">Pedidos extra · Mesa {{ mesa.numero }}</h2>
                        <span class="text-sm text-suave">Imprime na impressora da conta</span>
                    </div>
                    <button type="button" class="h-11 shrink-0 rounded-[10px] border border-linha-forte px-4 font-bold" @click="modal = null">Fechar</button>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <button
                        v-for="item in itensPedidoExtra"
                        :key="item.label"
                        type="button"
                        class="h-[72px] rounded-[10px] border border-linha-forte bg-white text-lg font-bold disabled:opacity-45"
                        :disabled="extraForm.processing"
                        @click="pedidoExtra(item.label); modal = null"
                    >{{ item.label }}</button>
                </div>
            </div>
        </div>

        <!-- Modal: cancelar pedido -->
        <div v-if="modal === 'cancelar'" class="fixed inset-0 z-50 flex items-center justify-center bg-escuro/60 p-4" @click.self="modal = null">
            <div role="alertdialog" aria-label="Cancelar pedido" class="w-full max-w-md rounded-[14px] bg-white p-6">
                <h2 class="text-xl font-extrabold">Cancelar este pedido e libertar a mesa?</h2>
                <p class="mt-2 text-[15px] text-suave">A conta de {{ eur(total) }} é anulada e a Mesa {{ mesa.numero }} fica livre.</p>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button type="button" class="h-14 rounded-[10px] border border-linha-forte font-bold" @click="modal = null">Não, voltar</button>
                    <button type="button" class="h-14 rounded-[10px] bg-perigo font-bold text-white" @click="confirmarCancelar">Sim, cancelar</button>
                </div>
            </div>
        </div>

        <!-- Fecho de conta (POS-Pagamento) -->
        <div v-if="pagamentoAberto" class="fixed inset-0 z-50 flex flex-col overflow-y-auto bg-fundo lg:overflow-hidden">
            <header class="flex h-16 shrink-0 items-center justify-between gap-3 bg-escuro px-4 text-white sm:px-6">
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold" @click="pagamentoAberto = false">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    <span class="hidden sm:inline">Cancelar e voltar à mesa</span><span class="sm:hidden">Voltar</span>
                </button>
                <div class="truncate text-base font-bold">Fechar conta · {{ mesa.designacao || `Mesa ${mesa.numero}` }}</div>
                <div class="hidden text-sm text-escuro-inativo md:block">{{ pedidoAutor }}</div>
            </header>
            <div class="grid flex-1 gap-5 p-4 sm:p-6 lg:min-h-0 lg:grid-cols-[360px_minmax(0,1fr)_380px]">
                <section aria-label="Resumo" class="flex flex-col rounded-[14px] border border-linha bg-white p-5 lg:min-h-0">
                    <h2 class="text-lg font-bold">Conta da mesa</h2>
                    <div class="mt-2 min-h-0 flex-1 divide-y divide-linha-fraca overflow-y-auto">
                        <div v-for="linha in resumoConta" :key="linha.chave" class="flex justify-between gap-3 py-2 text-[15px]">
                            <span>{{ linha.quantidade }} × {{ linha.nome }}</span><span class="shrink-0">{{ eur(linha.total) }}</span>
                        </div>
                    </div>
                    <div v-if="pedido.observacoes" class="mt-3 rounded-[10px] border border-laranja/30 bg-laranja-claro px-3 py-2">
                        <span class="block text-sm font-bold text-laranja-texto">Observações da mesa</span>
                        <span class="text-sm">{{ pedido.observacoes }}</span>
                    </div>
                    <div class="mt-3 flex justify-between gap-2 text-sm text-suave"><span><template v-if="pessoasNaMesa">{{ pessoasNaMesa }} pessoas · </template>pedido de {{ pedidoAutor }}</span><span>{{ artigosConta }} artigos</span></div>
                </section>
                <section aria-label="Pagamento" class="flex flex-col gap-4">
                    <div class="flex items-center justify-between gap-3 rounded-[14px] bg-escuro px-6 py-5 text-white">
                        <span class="text-lg text-escuro-inativo">Total a pagar</span>
                        <span class="text-4xl font-extrabold sm:text-5xl">{{ eur(total) }}</span>
                    </div>
                    <div role="group" aria-label="Método de pagamento" class="grid grid-cols-3 gap-3">
                        <button
                            v-for="m in metodosPagamento"
                            :key="m.valor"
                            type="button"
                            class="h-[72px] rounded-[14px] text-lg font-bold"
                            :class="metodo === m.valor ? 'border-[3px] border-verde bg-verde-claro text-verde-escuro' : 'border border-linha-forte bg-white'"
                            :aria-pressed="metodo === m.valor"
                            @click="escolherMetodo(m.valor)"
                        >{{ m.label }}</button>
                    </div>
                    <div class="rounded-[14px] border border-linha bg-white p-5">
                        <div class="flex justify-between text-sm font-semibold text-suave"><span>Valor recebido</span><span v-if="recebido === ''">Vazio = valor certo</span></div>
                        <div class="mt-3 grid grid-cols-4 gap-2">
                            <button type="button" class="h-16 rounded-[10px] text-lg font-bold" :class="recebido === '' ? 'border-[3px] border-verde bg-verde-claro text-verde-escuro' : 'border border-linha-forte bg-white'" :aria-pressed="recebido === ''" @click="recebido = ''">Certo</button>
                            <button
                                v-for="v in valoresRapidos"
                                :key="v"
                                type="button"
                                class="h-16 rounded-[10px] text-lg font-bold"
                                :class="String(recebido) === String(v) ? 'border-[3px] border-verde bg-verde-claro text-verde-escuro' : 'border border-linha-forte bg-white'"
                                :aria-pressed="String(recebido) === String(v)"
                                @click="recebido = String(v)"
                            >{{ v }} €</button>
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col justify-center rounded-[14px] border-2 border-laranja bg-laranja-claro p-6">
                        <span class="text-lg font-bold text-laranja-texto">Troco a dar</span>
                        <span aria-live="polite" class="text-5xl font-extrabold sm:text-6xl">{{ eur(troco) }}</span>
                        <span v-if="recebidoFalta" class="mt-2 text-[15px] font-semibold text-perigo">O valor recebido é menor que o total.</span>
                    </div>
                </section>
                <section aria-label="Teclado" class="flex flex-col gap-3">
                    <div class="flex items-center justify-between rounded-[14px] border border-linha bg-white px-5 py-4">
                        <span class="text-sm font-semibold text-suave">Recebido</span>
                        <span class="text-3xl font-extrabold" :class="recebido === '' ? 'text-suave-2' : ''">{{ visorRecebido }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="n in teclasPagamento"
                            :key="n"
                            type="button"
                            class="h-[72px] rounded-[10px] border border-linha-forte text-2xl font-bold"
                            :class="n === 'del' ? 'bg-fundo' : 'bg-white'"
                            :aria-label="n === 'del' ? 'Apagar' : (n === '.' ? 'Vírgula' : n)"
                            @click="tecla(n)"
                        >{{ n === 'del' ? '←' : (n === '.' ? ',' : n) }}</button>
                    </div>
                    <button type="button" class="h-14 rounded-[10px] border border-linha-forte bg-white font-bold" @click="recebido = ''">Limpar valor</button>
                    <div class="flex-1"></div>
                    <div v-if="fecharForm.errors.valor_recebido || fecharForm.errors.metodo_pagamento" role="alert" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-semibold text-perigo-texto">{{ fecharForm.errors.valor_recebido || fecharForm.errors.metodo_pagamento }}</div>
                    <p class="text-sm text-suave">O talão sai na impressora da conta ao confirmar.</p>
                    <button type="button" class="h-[76px] rounded-[14px] bg-verde text-xl font-bold text-white hover:bg-verde-escuro disabled:opacity-45" :disabled="fecharForm.processing" @click="fechar">
                        {{ fecharForm.processing ? 'A confirmar…' : 'Confirmar pagamento' }}
                    </button>
                </section>
            </div>
        </div>

        <!-- QR em ecrã inteiro -->
        <div v-if="qrAberto" class="fixed inset-0 z-[60] flex items-center justify-center overflow-auto bg-escuro/80 p-5">
            <div class="w-full max-w-md rounded-[14px] bg-white p-6 text-center">
                <h2 class="text-2xl font-extrabold">{{ qrTitulo }}</h2>
                <p class="mt-1 text-[15px] text-suave">{{ qrSubtitulo }}</p>
                <img v-if="qrDataUrl" :src="qrDataUrl" :alt="`QR code — ${qrTitulo}`" class="mx-auto my-5 h-72 w-72 rounded-[14px] border border-linha p-3">
                <input :value="qrLink" readonly aria-label="Link" class="h-11 w-full rounded-[10px] border-linha-forte text-xs">
                <button type="button" class="mt-3 h-14 w-full rounded-[10px] bg-escuro font-bold text-white" @click="copiarLinkCliente">Copiar link</button>
                <button type="button" class="mt-2 h-14 w-full rounded-[10px] border border-linha-forte font-bold" @click="qrAberto = false">Fechar</button>
            </div>
        </div>
    </main>
</template>
