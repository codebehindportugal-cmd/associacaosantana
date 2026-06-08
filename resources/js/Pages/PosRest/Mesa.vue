<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ mesa: Object, pedido: Object, produtos: Object });
const categoriaAtual = ref(Object.keys(props.produtos ?? {})[0] || '');
const pagamentoAberto = ref(false);
const metodo = ref('dinheiro');
const recebido = ref('');
const lugaresOcupados = ref('');
const letraSubmesaNova = ref('');
const novoForm = useForm({ lugares_ocupados: null, submesa_letra: null });
const itemForm = useForm({ produto_id: '', quantidade: 1, prioridade: false });
const fecharForm = useForm({ metodo_pagamento: 'dinheiro', valor_recebido: 0, troco: 0 });
const categorias = computed(() => Object.keys(props.produtos ?? {}));
const lista = computed(() => props.produtos?.[categoriaAtual.value] ?? []);
const total = computed(() => (props.pedido?.items ?? []).reduce((s, i) => s + Number(i.preco_unitario) * i.quantidade, 0));
const troco = computed(() => Math.max(0, Number(recebido.value || total.value) - total.value));
const mesaDividida = computed(() => !props.mesa?.mesa_principal_id && props.mesa?.submesas?.length > 0);
const podeEscolherLugares = computed(() => !props.pedido && !mesaDividida.value && Number(props.mesa?.capacidade ?? 0) > 1);
const pedidoAutor = computed(() => props.pedido?.operador_nome ?? props.pedido?.user?.name ?? props.pedido?.pos?.nome ?? 'Sem utilizador');
const euros = (v) => Number(v ?? 0).toFixed(2) + '€';
const secaoClasse = (produto) => ({ bebidas: 'bg-blue-600', cozinha: 'bg-orange-600', comida: 'bg-orange-600', acompanhamentos: 'bg-orange-600', sobremesas: 'bg-purple-600' }[produto.categoria?.secao] || 'bg-gray-700');
const abrirPedido = (mesa = props.mesa, lugares = lugaresOcupados.value) => {
    novoForm.lugares_ocupados = lugares || null;
    novoForm.submesa_letra = lugares ? (letraSubmesaNova.value || null) : null;
    novoForm.post(route('pos.rest.pedido.novo', mesa.id));
};
const addProduto = (produto) => {
    if (!props.pedido) return abrirPedido();
    itemForm.produto_id = produto.id;
    itemForm.quantidade = 1;
    itemForm.post(route('pos.rest.pedido.item', props.pedido.id), { preserveScroll: true });
};
const remover = (item) => router.delete(route('pos.rest.pedido.item.remover', [props.pedido.id, item.id]), { preserveScroll: true });
const urgente = (item) => router.patch(route('pos.rest.pedido.item.urgente', [props.pedido.id, item.id]), {}, { preserveScroll: true });
const fechar = () => {
    fecharForm.metodo_pagamento = metodo.value;
    fecharForm.valor_recebido = recebido.value || total.value;
    fecharForm.troco = troco.value;
    fecharForm.patch(route('pos.rest.pedido.fechar', props.pedido.id));
};
const tecla = (valor) => { if (valor === 'del') recebido.value = String(recebido.value).slice(0, -1); else recebido.value = String(recebido.value) + valor; };
</script>

<template>
    <main class="min-h-screen bg-gray-900 p-4 text-white">
        <header class="mb-4 flex items-center justify-between"><Link :href="route('pos.rest.mesas')" class="rounded-lg bg-gray-800 px-4 py-3 font-black">← MESAS</Link><h1 class="text-3xl font-black">MESA {{ mesa.numero }}</h1></header>
        <div class="grid gap-4 lg:grid-cols-[3fr_2fr]">
            <section class="relative rounded-lg bg-gray-800 p-4">
                <div class="mb-4 flex gap-2 overflow-x-auto pb-2">
                    <button v-for="cat in categorias" :key="cat" class="rounded-lg px-4 py-3 font-black" :class="cat === categoriaAtual ? 'bg-emerald-600' : 'bg-gray-700'" @click="categoriaAtual = cat">{{ cat }}</button>
                </div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <button v-for="produto in lista" :key="produto.id" class="min-h-28 rounded-lg p-4 text-left font-black" :class="secaoClasse(produto)" @click="addProduto(produto)">
                        <span class="block text-lg">{{ produto.nome }}</span><span class="mt-2 block text-2xl">{{ euros(produto.preco) }}</span>
                    </button>
                </div>
                <div v-if="!pedido" class="absolute inset-0 flex items-center justify-center rounded-lg bg-gray-950/80 p-6">
                    <div class="text-center">
                        <div class="mb-4 text-3xl font-black">{{ mesaDividida ? 'Escolhe uma submesa' : 'Abrir pedido nesta mesa?' }}</div>
                        <button v-if="!mesaDividida" class="rounded-lg bg-emerald-600 px-8 py-5 text-xl font-black" @click="abrirPedido">ABRIR PEDIDO</button>
                    </div>
                </div>
            </section>
            <aside class="rounded-lg bg-gray-800 p-4">
                <h2 class="text-4xl font-black">{{ mesa.designacao || `MESA ${mesa.numero}` }}</h2>
                <div v-if="!pedido" class="mt-6 rounded-lg bg-gray-900 p-4">
                    <p class="mb-4 text-center font-bold text-gray-300">Mesa livre - abre pedido na mesa completa ou numa submesa.</p>
                    <label v-if="podeEscolherLugares" class="mb-4 block font-black">Lugares ocupados
                        <input v-model="lugaresOcupados" type="number" min="1" max="80" class="mt-1 w-full rounded-lg border-gray-700 bg-gray-800 p-3 text-white" placeholder="Vazio = mesa completa">
                    </label>
                    <label v-if="podeEscolherLugares && lugaresOcupados" class="mb-4 block font-black">Letra da submesa
                        <input v-model="letraSubmesaNova" type="text" maxlength="1" class="mt-1 w-full rounded-lg border-gray-700 bg-gray-800 p-3 uppercase text-white" placeholder="Ex.: A">
                    </label>
                    <button v-if="!mesaDividida" class="w-full rounded-lg bg-emerald-600 p-4 text-lg font-black" @click="abrirPedido()">ABRIR PEDIDO</button>
                    <div v-if="mesa.submesas?.length" class="mt-4 space-y-2">
                        <Link v-for="submesa in mesa.submesas" :key="submesa.id" :href="route('pos.rest.mesa', submesa.id)" class="block rounded-lg p-3 font-black" :class="submesa.estado === 'ocupada' ? 'bg-red-700' : 'bg-gray-700'">
                            {{ submesa.designacao }} · {{ submesa.capacidade }} lugares · {{ submesa.estado }}
                        </Link>
                    </div>
                    <div v-if="novoForm.errors.mesa_id" class="mt-3 rounded bg-red-700 p-3 font-bold">{{ novoForm.errors.mesa_id }}</div>
                    <div v-if="novoForm.errors.submesa_letra" class="mt-3 rounded bg-red-700 p-3 font-bold">{{ novoForm.errors.submesa_letra }}</div>
                </div>
                <div v-else>
                    <div class="my-4 inline-flex rounded bg-blue-600 px-3 py-1 text-sm font-black uppercase">{{ pedido.estado }}</div>
                    <div class="mb-4 rounded-lg bg-gray-900 p-3">
                        <div class="text-sm font-bold text-gray-400">Pedido feito por</div>
                        <div class="text-xl font-black">{{ pedidoAutor }}</div>
                    </div>
                    <div class="space-y-3">
                        <div v-for="item in pedido.items" :key="item.id" class="rounded-lg border bg-gray-900 p-3" :class="item.prioridade ? 'animate-pulse border-amber-500' : 'border-gray-700'">
                            <div class="flex items-start justify-between gap-3"><strong>{{ item.produto?.nome }}</strong><button class="rounded bg-red-700 px-3 py-2 font-black" @click="remover(item)">-1</button></div>
                            <div class="mt-3 flex items-center justify-between gap-2">
                                <div class="font-mono text-lg">{{ item.quantidade }} × {{ euros(item.preco_unitario) }} = {{ euros(item.quantidade * item.preco_unitario) }}</div>
                                <button class="rounded px-3 py-2 font-black" :class="item.prioridade ? 'bg-amber-600' : 'bg-gray-700'" @click="urgente(item)">Fim</button>
                            </div>
                        </div>
                    </div>
                    <div class="my-5 text-right text-3xl font-black text-emerald-400">{{ euros(total) }}</div>
                    <button class="w-full rounded-lg bg-emerald-600 p-5 text-xl font-black" @click="pagamentoAberto = true">FECHAR CONTA</button>
                    <button class="mt-3 w-full rounded-lg bg-red-700 p-3 font-black" @click="router.patch(route('pos.rest.pedido.estado', pedido.id), { estado: 'cancelado' })">CANCELAR PEDIDO</button>
                </div>
            </aside>
        </div>
        <div v-if="pagamentoAberto" class="fixed inset-0 z-50 overflow-auto bg-gray-950 p-5">
            <div class="mx-auto max-w-xl">
                <h2 class="mb-4 text-center text-4xl font-black text-emerald-400">{{ euros(total) }}</h2>
                <div class="mb-4 grid grid-cols-3 gap-3"><button v-for="m in ['dinheiro','mbway','multibanco']" :key="m" class="rounded-lg p-4 font-black uppercase" :class="metodo === m ? 'bg-emerald-600' : 'bg-gray-800'" @click="metodo = m">{{ m }}</button></div>
                <input v-model="recebido" class="mb-3 w-full rounded-lg border-gray-700 bg-gray-800 p-4 text-center text-3xl font-black text-white" placeholder="Recebido">
                <div class="grid grid-cols-3 gap-2">
                    <button v-for="n in ['1','2','3','4','5','6','7','8','9','.','0','del']" :key="n" class="rounded-lg bg-gray-800 p-5 text-2xl font-black" @click="tecla(n)">{{ n === 'del' ? '←' : n }}</button>
                </div>
                <div class="my-4 text-center text-2xl font-black text-emerald-400">Troco: {{ euros(troco) }}</div>
                <button class="w-full rounded-lg bg-emerald-600 p-5 text-xl font-black" @click="fechar">✅ CONFIRMAR PAGAMENTO</button>
                <button class="mt-3 w-full rounded-lg bg-gray-700 p-4 font-black" @click="pagamentoAberto = false">✕ CANCELAR</button>
            </div>
        </div>
    </main>
</template>
