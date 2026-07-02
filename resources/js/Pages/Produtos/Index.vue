<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    categorias: Array,
    categoriasOptions: Array,
});

const form = useForm({
    categoria_id: props.categoriasOptions?.[0]?.id ?? '',
    nome: '',
    preco: '',
    stock_atual: 0,
    disponivel: true,
    disponivel_restaurante: true,
    disponivel_bar: true,
});

const criarProduto = () => {
    form.post(route('produtos.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('nome', 'preco'),
    });
};

const atualizarProduto = (produto) => {
    router.put(route('produtos.update', produto.id), {
        categoria_id: produto.categoria_id,
        nome: produto.nome,
        preco: produto.preco,
        stock_atual: produto.stock_atual,
        disponivel: produto.disponivel,
        disponivel_restaurante: produto.disponivel_restaurante,
        disponivel_bar: produto.disponivel_bar,
    }, { preserveScroll: true });
};

const eliminarProduto = (produto) => {
    if (confirm(`Eliminar o produto ${produto.nome}?`)) {
        router.delete(route('produtos.destroy', produto.id), { preserveScroll: true });
    }
};
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Produtos</h1>
            <p class="mt-1 text-sm text-slate-500">Lista de precos, stock e disponibilidade.</p>
        </div>

        <form class="mb-6 grid gap-3 rounded-lg bg-white p-5 shadow-sm md:grid-cols-[1fr_2fr_120px_120px_1fr_auto]" @submit.prevent="criarProduto">
            <select v-model="form.categoria_id" class="rounded-md border-slate-300 text-sm">
                <option v-for="categoria in categoriasOptions" :key="categoria.id" :value="categoria.id">
                    {{ categoria.nome }}
                </option>
            </select>
            <input v-model="form.nome" class="rounded-md border-slate-300 text-sm" placeholder="Nome do produto">
            <input v-model="form.preco" type="number" min="0" step="0.01" class="rounded-md border-slate-300 text-sm" placeholder="Preco">
            <input v-model="form.stock_atual" type="number" min="0" step="0.001" class="rounded-md border-slate-300 text-sm" placeholder="Stock">
            <div class="grid gap-2 text-sm font-medium text-slate-700 sm:grid-cols-3">
                <label class="flex min-h-10 items-center gap-2 rounded-md border border-slate-200 px-3 py-2">
                    <input v-model="form.disponivel" type="checkbox" class="rounded border-slate-300 text-slate-900">
                    Ativo
                </label>
                <label class="flex min-h-10 items-center gap-2 rounded-md border border-slate-200 px-3 py-2">
                    <input v-model="form.disponivel_restaurante" type="checkbox" class="rounded border-slate-300 text-slate-900">
                    Restaurante
                </label>
                <label class="flex min-h-10 items-center gap-2 rounded-md border border-slate-200 px-3 py-2">
                    <input v-model="form.disponivel_bar" type="checkbox" class="rounded border-slate-300 text-slate-900">
                    Bar
                </label>
            </div>
            <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Adicionar</button>
        </form>

        <div class="space-y-6">
            <section v-for="categoria in categorias" :key="categoria.id" class="rounded-lg bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="font-bold">{{ categoria.nome }}</h2>
                        <p class="text-xs uppercase text-slate-500">{{ categoria.secao }}</p>
                    </div>
                    <span class="text-sm text-slate-500">{{ categoria.produtos.length }} produtos</span>
                </div>

                <div class="divide-y divide-slate-100">
                    <div v-for="produto in categoria.produtos" :key="produto.id" class="grid gap-3 px-5 py-3 md:grid-cols-[1fr_120px_120px_1fr_auto]">
                        <input v-model="produto.nome" class="rounded-md border-slate-300 text-sm">
                        <input v-model="produto.preco" type="number" min="0" step="0.01" class="rounded-md border-slate-300 text-sm">
                        <input v-model="produto.stock_atual" type="number" min="0" step="0.001" class="rounded-md border-slate-300 text-sm">
                        <div class="grid gap-2 text-sm text-slate-700 sm:grid-cols-3">
                            <label class="flex min-h-10 items-center gap-2 rounded-md border border-slate-200 px-3 py-2">
                                <input v-model="produto.disponivel" type="checkbox" class="rounded border-slate-300 text-slate-900">
                                Ativo
                            </label>
                            <label class="flex min-h-10 items-center gap-2 rounded-md border border-slate-200 px-3 py-2">
                                <input v-model="produto.disponivel_restaurante" type="checkbox" class="rounded border-slate-300 text-slate-900">
                                Restaurante
                            </label>
                            <label class="flex min-h-10 items-center gap-2 rounded-md border border-slate-200 px-3 py-2">
                                <input v-model="produto.disponivel_bar" type="checkbox" class="rounded border-slate-300 text-slate-900">
                                Bar
                            </label>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" class="font-semibold text-emerald-700" @click="atualizarProduto(produto)">Guardar</button>
                            <button type="button" class="font-semibold text-red-700" @click="eliminarProduto(produto)">Eliminar</button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
