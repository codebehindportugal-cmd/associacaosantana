<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    filters: Object,
    preview: Object,
});

const filtros = reactive({
    data_inicio: props.filters?.data_inicio,
    data_fim: props.filters?.data_fim,
    tipo: props.filters?.tipo ?? 'ambos',
    manter_pedido_id: props.filters?.manter_pedido_id ?? '',
    apenas_relatorios: props.filters?.apenas_relatorios ?? true,
});

const form = useForm({
    ...filtros,
    confirmacao: '',
});

watch(filtros, () => {
    form.data_inicio = filtros.data_inicio;
    form.data_fim = filtros.data_fim;
    form.tipo = filtros.tipo;
    form.manter_pedido_id = filtros.manter_pedido_id;
    form.apenas_relatorios = filtros.apenas_relatorios;
}, { deep: true });

const euros = (valor) => Number(valor ?? 0).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });

const atualizarPreview = () => {
    router.get(route('manutencao.limpeza.index'), filtros, {
        preserveState: true,
        preserveScroll: true,
    });
};

const apagar = () => {
    form.delete(route('manutencao.limpeza.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            form.confirmacao = '';
            atualizarPreview();
        },
    });
};
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-950">Limpeza de dados</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-600">
                    Remove dados operacionais que alimentam relatórios. Usa primeiro a pré-visualização e mantém o pedido correto pelo número.
                </p>
            </div>
            <Link :href="route('manutencao.logs.index')" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-black text-white">
                Ver logs
            </Link>
        </div>

        <div class="mb-6 border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
            Esta ação é destrutiva em produção. Faz backup antes de apagar e confirma que o pedido correto está indicado no campo “Manter pedido”.
        </div>

        <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <form class="space-y-4 bg-white p-5 shadow-sm ring-1 ring-slate-200" @submit.prevent="atualizarPreview">
                <h2 class="text-lg font-black">Filtros</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-bold text-slate-700">Data início</span>
                        <input v-model="filtros.data_inicio" type="date" class="w-full rounded-md border-slate-300">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-bold text-slate-700">Data fim</span>
                        <input v-model="filtros.data_fim" type="date" class="w-full rounded-md border-slate-300">
                    </label>
                </div>

                <label class="block">
                    <span class="mb-1 block text-sm font-bold text-slate-700">O que apagar</span>
                    <select v-model="filtros.tipo" class="w-full rounded-md border-slate-300">
                        <option value="ambos">Pedidos e caixas</option>
                        <option value="pedidos">Só pedidos</option>
                        <option value="caixas">Só caixas</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-bold text-slate-700">Manter pedido</span>
                    <input v-model="filtros.manter_pedido_id" type="number" min="1" placeholder="Ex.: 23" class="w-full rounded-md border-slate-300">
                    <span class="mt-1 block text-xs text-slate-500">Este pedido não será apagado, mesmo estando dentro do período.</span>
                </label>

                <label class="flex items-start gap-3 rounded-md bg-slate-50 p-3 text-sm">
                    <input v-model="filtros.apenas_relatorios" type="checkbox" class="mt-1 rounded border-slate-300">
                    <span>
                        <strong class="block text-slate-900">Apagar apenas pedidos que entram nos relatórios</strong>
                        <span class="text-slate-600">Recomendado: limita aos pedidos entregues ou pré-pagos.</span>
                    </span>
                </label>

                <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-3 font-black text-white">
                    Atualizar pré-visualização
                </button>
            </form>

            <div class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <article class="bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <div class="text-sm font-bold uppercase text-slate-500">Pedidos a apagar</div>
                        <div class="mt-2 text-4xl font-black">{{ preview?.pedidos?.count ?? 0 }}</div>
                        <div class="mt-2 text-sm text-slate-600">{{ preview?.pedidos?.items ?? 0 }} itens · {{ euros(preview?.pedidos?.total) }}</div>
                    </article>
                    <article class="bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <div class="text-sm font-bold uppercase text-slate-500">Caixas a apagar</div>
                        <div class="mt-2 text-4xl font-black">{{ preview?.caixas?.count ?? 0 }}</div>
                        <div class="mt-2 text-sm text-slate-600">Fundo de maneio: {{ euros(preview?.caixas?.fundo_maneio) }}</div>
                    </article>
                </div>

                <section class="bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h2 class="mb-3 text-lg font-black">Pedidos encontrados</h2>
                    <div v-if="!preview?.pedidos?.samples?.length" class="text-sm text-slate-500">Nenhum pedido neste filtro.</div>
                    <div v-for="pedido in preview?.pedidos?.samples" :key="pedido.id" class="grid gap-2 border-t py-3 text-sm md:grid-cols-[5rem_1fr_1fr_1fr]">
                        <strong>#{{ pedido.id }}</strong>
                        <span>{{ pedido.created_at }} · {{ pedido.ponto }}</span>
                        <span>{{ pedido.tipo }} · {{ pedido.estado }}</span>
                        <strong class="md:text-right">{{ euros(pedido.total) }}</strong>
                    </div>
                </section>

                <section class="bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <h2 class="mb-3 text-lg font-black">Caixas encontradas</h2>
                    <div v-if="!preview?.caixas?.samples?.length" class="text-sm text-slate-500">Nenhuma caixa neste filtro.</div>
                    <div v-for="caixa in preview?.caixas?.samples" :key="caixa.id" class="grid gap-2 border-t py-3 text-sm md:grid-cols-[1fr_1fr_1fr_1fr]">
                        <strong>{{ caixa.data }}</strong>
                        <span>{{ caixa.ponto }}</span>
                        <span>{{ caixa.estado }}</span>
                        <strong class="md:text-right">{{ euros(caixa.fundo_maneio) }}</strong>
                    </div>
                </section>

                <form class="border border-red-300 bg-red-50 p-5" @submit.prevent="apagar">
                    <h2 class="text-lg font-black text-red-950">Confirmar apagamento</h2>
                    <p class="mt-2 text-sm text-red-900">
                        Para apagar os dados desta pré-visualização, escreve exatamente <strong>APAGAR DADOS</strong>.
                    </p>
                    <input v-model="form.confirmacao" type="text" class="mt-4 w-full rounded-md border-red-300" placeholder="APAGAR DADOS">
                    <div v-if="form.errors.confirmacao" class="mt-2 text-sm font-bold text-red-700">{{ form.errors.confirmacao }}</div>
                    <button type="submit" class="mt-4 w-full rounded-md bg-red-700 px-4 py-3 font-black text-white disabled:opacity-50" :disabled="form.processing">
                        {{ form.processing ? 'A apagar...' : 'Apagar dados selecionados' }}
                    </button>
                </form>
            </div>
        </section>
    </AppLayout>
</template>
