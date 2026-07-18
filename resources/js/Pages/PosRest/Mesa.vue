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
const cancelarPedido = () => {
    if (!props.pedido || !confirm('Cancelar este pedido e libertar a mesa?')) return;
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
</script>

<template>
    <ChamadaFuncionarioAlert />
    <ComissaoChamadasAlert />
    <main class="min-h-screen w-full max-w-[100vw] overflow-x-hidden bg-gray-900 p-3 text-white sm:p-4">
        <header class="mb-4 flex items-center justify-between"><Link :href="route('pos.rest.mesas')" class="rounded-lg bg-gray-800 px-4 py-3 font-black">← MESAS</Link><h1 class="text-3xl font-black">MESA {{ mesa.numero }}</h1></header>
        <div v-if="!pedido" class="min-w-0 space-y-4">
            <!-- ABRIR MESA PRIMEIRO NO MOBILE -->
            <section class="min-w-0 rounded-2xl bg-gray-800 p-4 shadow-lg sm:p-5">
                <h2 class="break-words text-2xl font-black sm:text-3xl">{{ mesa.designacao || `MESA ${mesa.numero}` }}</h2>
                <p class="mt-2 rounded-lg bg-gray-900 p-3 text-sm font-bold text-gray-300">Antes de escolher produtos, abre o pedido com o número de pessoas.</p>
                
                <div v-if="!mesaDividida" class="mt-5 space-y-4">
                    <div v-if="mesa.reserva_ativa" class="rounded-lg bg-orange-950/80 border border-orange-500 px-3 py-2 text-sm font-bold text-orange-300">
                        🪑 Reserva: <span class="text-white">{{ mesa.reserva_ativa.nome }}</span> — {{ mesa.reserva_ativa.pessoas }} pessoas
                    </div>
                    <label class="block font-black">Número de pessoas
                        <input v-model="lugaresOcupados" type="number" min="1" max="80" class="mt-2 w-full rounded-lg border-gray-700 bg-gray-900 p-4 text-2xl font-black text-white" placeholder="Obrigatório">
                    </label>
                    <label v-if="precisaMesasGrupo" class="block font-black">Mesas do grupo
                        <input v-model="mesasGrupo" type="text" class="mt-2 w-full rounded-lg border-gray-700 bg-gray-900 p-4 text-2xl font-black text-white" placeholder="Ex.: 32 33 34">
                        <div v-if="mesasLivres.length" class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="m in mesasLivres"
                                :key="m.id"
                                type="button"
                                class="rounded-lg px-3 py-2 text-sm font-black transition"
                                :class="mesasGrupoSet.has(String(m.numero)) ? 'bg-amber-500 text-gray-950' : 'bg-gray-700 hover:bg-gray-600'"
                                @click="toggleMesaGrupo(m.numero)"
                            >{{ m.numero }} <span class="opacity-60">({{ Math.min(10, Number(m.capacidade)) }}p)</span></button>
                        </div>
                        <p v-if="mesasGrupoSet.size > 0 && lugaresNumero > 0" class="mt-2 text-sm font-bold" :class="capacidadeGrupoSelecionado >= lugaresNumero ? 'text-emerald-400' : 'text-red-400'">
                            Capacidade total: {{ capacidadeGrupoSelecionado }} lugares {{ capacidadeGrupoSelecionado >= lugaresNumero ? '✓' : '— precisa de mais mesas' }}
                        </p>
                    </label>
                    <label v-if="precisaLetra" class="block font-black">{{ precisaMesasGrupo ? 'Letra do grupo' : 'Letra da submesa' }}
                        <select v-model="letraSubmesaNova" class="mt-2 w-full rounded-lg border-gray-700 bg-gray-900 p-4 text-2xl font-black uppercase text-white">
                            <option value="">Escolher letra</option>
                            <option v-for="letra in submesaLetrasDisponiveis" :key="letra" :value="letra">{{ letra }}</option>
                        </select>
                    </label>
                    <button class="w-full rounded-lg bg-emerald-600 p-4 text-lg font-black disabled:opacity-40" :disabled="!podeAbrirPedido || novoForm.processing" @click="abrirPedido()">
                        {{ novoForm.processing ? 'A ABRIR...' : 'ABRIR PEDIDO' }}
                    </button>
                </div>
                
                <div v-if="mesa.submesas?.length" class="mt-6 space-y-2">
                    <p class="mb-3 font-bold text-gray-400">SUBMESAS:</p>
                    <Link v-for="submesa in mesa.submesas" :key="submesa.id" :href="route('pos.rest.mesa', submesa.id)" class="block rounded-lg p-3 font-black" :class="submesa.estado === 'ocupada' ? 'bg-red-700' : 'bg-gray-700'">
                        {{ submesa.designacao }} · {{ submesa.capacidade }} lugares · {{ submesa.estado }}
                    </Link>
                </div>
                
                <div v-if="novoForm.errors.mesa_id" class="mt-4 rounded bg-red-700 p-3 font-bold">{{ novoForm.errors.mesa_id }}</div>
                <div v-if="novoForm.errors.submesa_letra" class="mt-4 rounded bg-red-700 p-3 font-bold">{{ novoForm.errors.submesa_letra }}</div>
                <div v-if="novoForm.errors.lugares_ocupados" class="mt-4 rounded bg-red-700 p-3 font-bold">{{ novoForm.errors.lugares_ocupados }}</div>
                <div v-if="novoForm.errors.mesas_grupo" class="mt-4 rounded bg-red-700 p-3 font-bold">{{ novoForm.errors.mesas_grupo }}</div>
            </section>
            
            <!-- PRODUTOS SECUNDÁRIOS NO MOBILE -->
            <section class="min-w-0 overflow-hidden rounded-lg bg-gray-800 p-3 opacity-50 sm:p-4">
                <div class="mb-4 rounded bg-gray-900 p-3 text-center text-sm font-black text-gray-300">Abre o pedido para adicionar produtos.</div>
                <div class="mb-4 flex max-w-full gap-2 overflow-x-auto pb-2">
                    <button v-for="cat in categorias" :key="cat" class="min-h-12 shrink-0 whitespace-nowrap rounded-lg px-4 py-3 font-black" :class="cat === categoriaAtual ? 'bg-emerald-600' : 'bg-gray-700'" @click="categoriaAtual = cat">{{ cat }}</button>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                    <button v-for="produto in lista" :key="produto.id" class="min-h-24 min-w-0 rounded-lg p-4 text-left font-black sm:min-h-28" :class="secaoClasse(produto)" @click="addProduto(produto)">
                        <span class="block text-lg">{{ produto.nome }}</span><span class="mt-2 block text-2xl">{{ euros(produto.preco) }}</span>
                    </button>
                </div>
            </section>
        </div>
        <div v-else class="min-w-0 space-y-4">
            <nav class="overflow-x-auto rounded-2xl bg-gray-800 p-1">
                <div class="flex min-w-max gap-2">
                <button
                    v-for="separador in separadores"
                    :key="separador.key"
                    type="button"
                    class="min-h-12 min-w-28 shrink-0 rounded-xl px-4 py-2 text-sm font-black"
                    :class="separadorAtual === separador.key ? 'bg-white text-gray-950' : 'text-gray-200'"
                    @click="separadorAtual = separador.key"
                >
                    {{ separador.label }}
                    <span v-if="separador.count" class="ml-1 rounded-full bg-emerald-500 px-2 py-0.5 text-xs text-gray-950">{{ separador.count }}</span>
                </button>
                </div>
            </nav>
            <div v-if="aviso || page.props.flash?.success" class="rounded-xl border border-emerald-500/40 bg-emerald-500/15 p-3 text-sm font-black text-emerald-100">
                {{ aviso || page.props.flash.success }}
            </div>

            <section v-if="separadorAtual === 'produtos'" class="min-w-0 overflow-hidden rounded-lg bg-gray-800 p-3 sm:p-4">
                <div class="mb-4 flex max-w-full gap-2 overflow-x-auto pb-2">
                    <button v-for="cat in categorias" :key="cat" class="min-h-12 shrink-0 whitespace-nowrap rounded-lg px-4 py-3 font-black" :class="cat === categoriaAtual ? 'bg-emerald-600' : 'bg-gray-700'" @click="categoriaAtual = cat">{{ cat }}</button>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                    <button v-for="produto in lista" :key="produto.id" class="min-h-24 min-w-0 rounded-lg p-4 text-left font-black sm:min-h-28" :class="secaoClasse(produto)" @click="addProduto(produto)">
                        <span class="block break-words text-lg">{{ produto.nome }}</span><span class="mt-2 block text-2xl">{{ euros(produto.preco) }}</span>
                    </button>
                </div>
            </section>

            <section v-if="separadorAtual === 'envio'" class="min-w-0 rounded-lg border-2 border-emerald-500 bg-gray-800 p-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-2xl font-black">Para enviar</h2>
                    <strong class="text-2xl text-emerald-400">{{ euros(totalCarrinho) }}</strong>
                </div>
                <div v-if="!carrinho.length" class="rounded bg-gray-900 p-5 text-center font-bold text-gray-400">
                    Escolhe produtos no separador Produtos.
                </div>
                <div v-for="(item, index) in carrinho" :key="`${item.produto_id}-${index}`" class="mb-2 rounded bg-gray-900 p-3">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <strong>{{ item.nome }}</strong>
                        <button class="rounded bg-red-700 px-3 py-2 font-black" @click="alterarQuantidadeCarrinho(item, -1)">-</button>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-lg">{{ item.quantidade }} x {{ euros(item.preco) }}</span>
                        <button class="rounded bg-emerald-700 px-3 py-2 font-black" @click="alterarQuantidadeCarrinho(item, 1)">+</button>
                    </div>
                    <label class="mt-2 flex items-center gap-2 text-sm font-black text-amber-300">
                        <input v-model="item.prioridade" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-amber-500">
                        A terminar
                    </label>
                    <textarea
                        v-model="item.observacoes"
                        rows="2"
                        maxlength="255"
                        class="mt-2 w-full rounded-lg border-gray-700 bg-gray-800 p-3 text-sm font-bold text-white placeholder:text-gray-500"
                        placeholder="Observações: sem cebola, bem passado..."
                    ></textarea>
                </div>
                <div v-if="itemForm.errors.items" class="mt-2 rounded bg-red-700 p-3 text-sm font-bold">{{ itemForm.errors.items }}</div>
                <button
                    class="mt-2 w-full rounded-lg bg-emerald-600 p-4 text-lg font-black disabled:opacity-50"
                    :disabled="!carrinho.length || itemForm.processing"
                    @click="enviarPedido"
                >
                    {{ itemForm.processing ? 'A ENVIAR...' : 'ENVIAR PEDIDO' }}
                </button>
            </section>

            <section v-if="separadorAtual === 'conta'" class="min-w-0 rounded-lg bg-gray-800 p-4">
                <h2 class="break-words text-3xl font-black">{{ mesa.designacao || `MESA ${mesa.numero}` }}</h2>
                <div class="my-4 inline-flex rounded bg-blue-600 px-3 py-1 text-sm font-black uppercase">{{ pedido.estado }}</div>
                <div class="mb-4 rounded-lg bg-gray-900 p-3">
                    <div v-if="pedido.nome_reserva" class="mb-2 rounded-lg bg-orange-900/60 px-3 py-2 text-base font-black text-orange-300">
                        🪑 Reserva: {{ pedido.nome_reserva }}
                    </div>
                    <div class="text-sm font-bold text-gray-400">Pedido feito por</div>
                    <div class="text-xl font-black">{{ pedidoAutor }}</div>
                </div>
                <div class="mb-4 rounded-lg bg-gray-900 p-3">
                    <label class="block font-black">
                        Pessoas na mesa
                        <input v-model="lugaresAtuais" type="number" min="1" class="mt-2 w-full rounded-lg border-gray-700 bg-gray-800 p-3 text-white">
                    </label>
                    <button class="mt-2 w-full rounded bg-gray-700 p-3 font-black" @click="atualizarLugares">ATUALIZAR PESSOAS</button>
                    <div v-if="lugaresForm.errors.lugares_ocupados" class="mt-2 rounded bg-red-700 p-2 text-sm font-bold">{{ lugaresForm.errors.lugares_ocupados }}</div>
                </div>
                <div class="mb-4 rounded-lg bg-amber-900/40 border border-amber-600 p-3">
                    <label class="block font-black text-amber-300">
                        Observações da mesa
                        <textarea
                            v-model="obsForm.observacoes"
                            rows="2"
                            placeholder="Ex: Banda não foi paga, Desconto especial, NIF 123..."
                            class="mt-2 w-full rounded-lg border-gray-700 bg-gray-900 p-3 text-sm font-bold text-white placeholder:text-gray-500"
                        ></textarea>
                    </label>
                    <button class="mt-2 w-full rounded bg-amber-700 p-2 text-sm font-black" @click="guardarObservacoes">GUARDAR OBSERVAÇÃO</button>
                </div>
                <div class="space-y-3">
                    <div v-if="erroItem" class="rounded bg-red-700 p-3 text-sm font-black">{{ erroItem }}</div>
                    <div v-for="item in pedido.items" :key="item.id" class="rounded-lg border bg-gray-900 p-3" :class="item.prioridade ? 'animate-pulse border-amber-500' : 'border-gray-700'">
                        <div class="flex items-start justify-between gap-3">
                            <strong>{{ item.produto?.nome }}</strong>
                            <button v-if="itemDentroPrazoAnulacao(item)" class="rounded bg-red-700 px-3 py-2 font-black" @click="remover(item)">-1</button>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <div class="font-mono text-lg">{{ item.quantidade }} × {{ euros(item.preco_unitario) }} = {{ euros(item.quantidade * item.preco_unitario) }}</div>
                            <button class="rounded px-3 py-2 font-black" :class="item.prioridade ? 'bg-amber-600' : 'bg-gray-700'" @click="urgente(item)">Fim</button>
                        </div>
                        <div v-if="item.observacoes" class="mt-3 rounded bg-amber-500 px-3 py-2 text-sm font-black text-gray-950">
                            {{ item.observacoes }}
                        </div>
                    </div>
                </div>
                <div class="my-5 text-right text-3xl font-black text-emerald-400">{{ euros(total) }}</div>
                <button class="w-full rounded-lg bg-emerald-600 p-5 text-xl font-black" @click="pagamentoAberto = true">FECHAR CONTA</button>
                <button class="mt-3 w-full rounded-lg bg-red-700 p-3 font-black" @click="cancelarPedido">CANCELAR PEDIDO</button>
            </section>

            <section v-if="separadorAtual === 'qrs'" class="grid min-w-0 gap-4 md:grid-cols-2">
                <article v-if="podeSelfOrder" class="rounded-lg border-2 border-cyan-500 bg-gray-800 p-4">
                    <h2 class="text-xl font-black">SELF-ORDER DO CLIENTE</h2>
                    <p class="mt-1 text-sm font-bold text-gray-300">Mostra o QR ao cliente para adicionar itens pelo telemóvel.</p>
                    <button class="mt-3 w-full rounded-lg bg-cyan-600 p-4 text-lg font-black" @click="mostrarQr">MOSTRAR QR AO CLIENTE</button>
                    <button class="mt-2 w-full rounded-lg bg-gray-900 p-3 text-sm font-black" @click="copiarLinkCliente">COPIAR LINK</button>
                </article>
                <article v-if="podeSelfOrder" class="rounded-lg border-2 border-amber-500 bg-gray-800 p-4">
                    <h2 class="text-xl font-black">CHAMAR FUNCIONÁRIO</h2>
                    <p class="mt-1 text-sm font-bold text-gray-300">QR para o cliente chamar um funcionário à mesa, sem fazer pedido.</p>
                    <button class="mt-3 w-full rounded-lg bg-amber-500 p-4 text-lg font-black text-slate-950" @click="mostrarQrFuncionario">MOSTRAR QR CHAMAR</button>
                </article>
                <article class="rounded-lg border-2 border-emerald-500 bg-gray-800 p-4">
                    <h2 class="text-xl font-black">PREÇÁRIO DO SITE</h2>
                    <p class="mt-1 text-sm font-bold text-gray-300">Mostra o QR para o cliente consultar produtos e preços.</p>
                    <button class="mt-3 w-full rounded-lg bg-emerald-600 p-4 text-lg font-black" @click="mostrarQrPrecario">MOSTRAR QR PREÇÁRIO</button>
                </article>
            </section>
        </div>
            <section v-if="separadorAtual === 'extras'" class="min-w-0 rounded-lg bg-gray-800 p-4">
                <h2 class="mb-4 text-xl font-black text-gray-200">PEDIDOS EXTRA</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <button
                        v-for="item in itensPedidoExtra"
                        :key="item.label"
                        class="min-h-20 rounded-xl bg-gray-700 p-4 text-center font-black transition hover:bg-gray-600 disabled:opacity-50"
                        :disabled="extraForm.processing"
                        @click="pedidoExtra(item.label)"
                    >
                        <span class="block text-3xl">{{ item.emoji }}</span>
                        <span class="mt-2 block text-sm">{{ item.label }}</span>
                    </button>
                </div>
                <p class="mt-4 text-center text-xs font-bold text-gray-500">Imprime na impressora da conta</p>

                <div class="mt-4 border-t border-gray-700 pt-4">
                    <button
                        type="button"
                        class="w-full rounded-xl bg-amber-500 p-4 text-sm font-black text-black transition hover:bg-amber-400"
                        @click="chamandoComissao = true"
                    >
                        🎉 CHAMAR COMISSÃO DE FESTAS
                    </button>
                </div>
            </section>

        <ChamarComissaoModal
            v-if="chamandoComissao"
            :operador-nome="pedidoAutor"
            @fechar="chamandoComissao = false"
        />

        <div v-if="pagamentoAberto" class="fixed inset-0 z-50 overflow-auto bg-gray-950 p-5">
            <div class="mx-auto max-w-xl">
                <h2 class="mb-4 text-center text-4xl font-black text-emerald-400">{{ euros(total) }}</h2>
                <div class="mb-4 grid grid-cols-3 gap-3"><button v-for="m in ['dinheiro','mbway','multibanco']" :key="m" class="rounded-lg p-3 text-sm font-black uppercase leading-tight sm:p-4 sm:text-base" :class="metodo === m ? 'bg-emerald-600' : 'bg-gray-800'" @click="metodo = m">{{ m }}</button></div>
                <input v-model="recebido" class="mb-3 w-full rounded-lg border-gray-700 bg-gray-800 p-4 text-center text-3xl font-black text-white" placeholder="Recebido">
                <div class="grid grid-cols-3 gap-2">
                    <button v-for="n in ['1','2','3','4','5','6','7','8','9','.','0','del']" :key="n" class="rounded-lg bg-gray-800 p-5 text-2xl font-black" @click="tecla(n)">{{ n === 'del' ? '←' : n }}</button>
                </div>
                <div class="my-4 text-center text-2xl font-black text-emerald-400">Troco: {{ euros(troco) }}</div>
                <button class="w-full rounded-lg bg-emerald-600 p-5 text-xl font-black" @click="fechar">✅ CONFIRMAR PAGAMENTO</button>
                <button class="mt-3 w-full rounded-lg bg-gray-700 p-4 font-black" @click="pagamentoAberto = false">✕ CANCELAR</button>
            </div>
        </div>
        <div v-if="qrAberto" class="fixed inset-0 z-50 overflow-auto bg-gray-950 p-5">
            <div class="mx-auto max-w-md rounded-2xl bg-white p-5 text-center text-slate-950">
                <h2 class="text-2xl font-black">{{ qrTitulo }}</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">{{ qrSubtitulo }}</p>
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR code self-order" class="mx-auto my-5 h-72 w-72 rounded-xl border p-3">
                <input :value="qrLink" readonly class="w-full rounded-lg border-slate-300 text-xs">
                <button class="mt-3 w-full rounded-lg bg-slate-900 p-3 font-black text-white" @click="copiarLinkCliente">COPIAR LINK</button>
                <button class="mt-3 w-full rounded-lg bg-gray-200 p-3 font-black text-slate-950" @click="qrAberto = false">FECHAR</button>
            </div>
        </div>
    </main>
</template>
