<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    categorias: Array,
    categoriasOptions: Array,
});

const form = useForm({
    categoria_id: props.categoriasOptions?.[0]?.id ?? '',
    nome: '',
    imagem: null,
    preco: '',
    caucao: 0,
    stock_atual: 0,
    disponivel: true,
    disponivel_restaurante: true,
    disponivel_bar: true,
});

const criarProduto = () => {
    form.post(route('produtos.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('nome', 'preco', 'caucao', 'stock_atual');
            form.imagem = null;
        },
    });
};

// Imagens pendentes para edição (id -> File)
const imagensPendentes = ref({});

// Filtros
const pesquisaProduto = ref('');
const filtroCategoria = ref('');
const filtroAtivo = ref('');

const categoriasFiltradas = computed(() => (props.categorias ?? [])
    .filter((categoria) => !filtroCategoria.value || categoria.id === filtroCategoria.value)
    .map((categoria) => ({
        ...categoria,
        produtosVisiveis: categoria.produtos.filter((produto) => {
            if (filtroAtivo.value === 'ativos' && !produto.ativo) return false;
            if (filtroAtivo.value === 'inativos' && produto.ativo) return false;
            const termo = pesquisaProduto.value.trim().toLowerCase();
            if (termo && !String(produto.nome).toLowerCase().includes(termo)) return false;
            return true;
        }),
    }))
    .filter((categoria) => categoria.produtosVisiveis.length > 0));

// Erros de validação da edição em linha, por produto
const errosProduto = ref({});
const atualizarProduto = (produto) => {
    errosProduto.value[produto.id] = {};
    const data = {
        _method: 'PUT',
        categoria_id: produto.categoria_id,
        nome: produto.nome,
        preco: produto.preco,
        caucao: produto.caucao ?? 0,
        stock_atual: produto.stock_atual,
        disponivel: produto.disponivel,
        disponivel_restaurante: produto.disponivel_restaurante,
        disponivel_bar: produto.disponivel_bar,
    };
    if (imagensPendentes.value[produto.id]) {
        data.imagem = imagensPendentes.value[produto.id];
    }
    router.post(route('produtos.update', produto.id), data, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { delete imagensPendentes.value[produto.id]; },
        onError: (erros) => { errosProduto.value[produto.id] = erros; },
    });
};

const eliminarProduto = (produto) => {
    if (confirm(`Eliminar o produto ${produto.nome}?`)) {
        router.delete(route('produtos.destroy', produto.id), { preserveScroll: true });
    }
};

// Apresentação
const corSecao = (secao) => ({
    bebidas: { texto: 'text-secao-bar', borda: 'border-l-secao-bar' },
    frango: { texto: 'text-secao-grelhados', borda: 'border-l-secao-grelhados' },
    acompanhamentos: { texto: 'text-secao-acompanhamentos', borda: 'border-l-secao-acompanhamentos' },
    comida: { texto: 'text-secao-cozinha', borda: 'border-l-secao-cozinha' },
    sobremesas: { texto: 'text-secao-sobremesas', borda: 'border-l-secao-sobremesas' },
}[secao] ?? { texto: 'text-secao-servico', borda: 'border-l-secao-servico' });
const nomeSecao = (secao) => secao ? String(secao).charAt(0).toUpperCase() + String(secao).slice(1) : '';
const filtrosAtivo = [['', 'Todos'], ['ativos', 'Ativos'], ['inativos', 'Inativos']];
</script>

<template>
    <AppLayout>
        <div class="flex flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-col gap-1.5">
                <h1 class="text-[30px] font-extrabold leading-tight">Produtos</h1>
                <p class="text-[15px] text-suave">Lista de preços, stock e disponibilidade.</p>
            </div>

            <form aria-labelledby="novo-produto" class="flex flex-col gap-3.5 rounded-[14px] border border-linha bg-white p-5" @submit.prevent="criarProduto">
                <h2 id="novo-produto" class="text-lg font-extrabold">Novo produto</h2>
                <div class="grid items-end gap-3 sm:grid-cols-2 xl:grid-cols-[180px_minmax(0,1fr)_120px_110px_110px]">
                    <label class="flex min-w-0 flex-col gap-1.5">
                        <span class="text-sm font-bold text-suave">Categoria</span>
                        <select v-model="form.categoria_id" class="h-12 w-full rounded-[10px] border-linha-forte text-base font-semibold focus:border-verde focus:ring-verde">
                            <option v-for="categoria in categoriasOptions" :key="categoria.id" :value="categoria.id">{{ categoria.nome }}</option>
                        </select>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1.5 sm:col-span-2 xl:col-span-1">
                        <span class="text-sm font-bold text-suave">Nome do produto</span>
                        <input v-model="form.nome" class="h-12 w-full rounded-[10px] border-linha-forte px-3 text-base focus:border-verde focus:ring-verde" placeholder="Nome do produto">
                    </label>
                    <label class="flex min-w-0 flex-col gap-1.5">
                        <span class="text-sm font-bold text-suave">Preço</span>
                        <span class="flex h-12 items-center rounded-[10px] border border-linha-forte px-3 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                            <input v-model="form.preco" type="number" min="0" step="0.01" inputmode="decimal" class="w-full border-0 bg-transparent p-0 text-right text-base font-extrabold focus:ring-0" placeholder="0,00">
                            <span class="pl-1 text-suave-2">€</span>
                        </span>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1.5">
                        <span class="text-sm font-bold text-suave">Stock</span>
                        <input v-model="form.stock_atual" type="number" min="0" step="0.001" class="h-12 w-full rounded-[10px] border-linha-forte px-3 text-right text-base focus:border-verde focus:ring-verde" placeholder="Stock">
                    </label>
                    <div class="flex min-w-0 flex-col gap-1.5">
                        <span class="text-sm font-bold text-suave">Imagem</span>
                        <label class="flex h-12 cursor-pointer items-center justify-center gap-1.5 overflow-hidden rounded-[10px] border border-dashed border-linha-forte px-2 text-sm font-bold text-suave hover:bg-fundo">
                            <svg class="shrink-0" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h4l2-3h6l2 3h4v13H3zM9 13a3 3 0 1 0 6 0a3 3 0 1 0-6 0" /></svg>
                            <span class="truncate">{{ form.imagem ? form.imagem.name : 'Escolher' }}</span>
                            <input type="file" accept="image/*" class="sr-only" @change="(e) => form.imagem = e.target.files[0]">
                        </label>
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-2">
                        <label class="flex h-11 cursor-pointer items-center gap-2 rounded-full border border-[#C6E2D4] bg-verde-claro px-3.5 text-[15px] font-bold text-verde-escuro"><input v-model="form.disponivel" type="checkbox" class="h-[18px] w-[18px] rounded border-linha-forte text-verde focus:ring-verde">Ativo</label>
                        <label class="flex h-11 cursor-pointer items-center gap-2 rounded-full border border-[#C6E2D4] bg-verde-claro px-3.5 text-[15px] font-bold text-verde-escuro"><input v-model="form.disponivel_restaurante" type="checkbox" class="h-[18px] w-[18px] rounded border-linha-forte text-verde focus:ring-verde">Restaurante</label>
                        <label class="flex h-11 cursor-pointer items-center gap-2 rounded-full border border-[#C6E2D4] bg-verde-claro px-3.5 text-[15px] font-bold text-verde-escuro"><input v-model="form.disponivel_bar" type="checkbox" class="h-[18px] w-[18px] rounded border-linha-forte text-verde focus:ring-verde">Bar</label>
                        <label class="flex h-11 items-center gap-2 rounded-full border border-linha-forte bg-white px-3.5 text-[15px] font-bold" title="Valor devolvido quando o cliente entrega o artigo (ex.: metro, jarro de vinho)">
                            Caução
                            <input v-model="form.caucao" type="number" min="0" step="0.01" inputmode="decimal" aria-label="Caução" class="h-8 w-20 rounded-lg border-linha-forte px-2 text-right text-[15px] focus:border-verde focus:ring-verde">
                            <span class="text-suave-2">€</span>
                        </label>
                    </div>
                    <button type="submit" class="inline-flex h-12 items-center gap-2 rounded-[10px] bg-verde px-6 text-base font-bold text-white hover:bg-verde-escuro disabled:opacity-60" :disabled="form.processing">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        {{ form.processing ? 'A adicionar...' : 'Adicionar' }}
                    </button>
                </div>
                <div v-if="Object.keys(form.errors).length" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto">
                    <div v-for="(erro, campo) in form.errors" :key="campo">{{ erro }}</div>
                </div>
            </form>

            <div class="flex flex-wrap items-center gap-2.5">
                <select v-model="filtroCategoria" aria-label="Categoria" class="h-12 rounded-[10px] border-linha-forte bg-white text-[15px] font-bold focus:border-verde focus:ring-verde">
                    <option value="">Todas as categorias</option>
                    <option v-for="categoria in categorias" :key="categoria.id" :value="categoria.id">{{ categoria.nome }}</option>
                </select>
                <div role="group" aria-label="Filtrar por estado" class="flex flex-wrap gap-2">
                    <button
                        v-for="opcao in filtrosAtivo"
                        :key="opcao[0]"
                        type="button"
                        :aria-pressed="filtroAtivo === opcao[0]"
                        class="inline-flex h-11 items-center rounded-full border px-[18px] text-[15px] font-bold transition"
                        :class="filtroAtivo === opcao[0] ? 'border-escuro bg-escuro text-white' : 'border-linha-forte bg-white text-tinta hover:bg-fundo'"
                        @click="filtroAtivo = opcao[0]"
                    >{{ opcao[1] }}</button>
                </div>
                <label class="flex h-12 min-w-0 flex-[1_1_240px] items-center gap-2.5 rounded-[10px] border border-linha-forte bg-white px-3.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                    <svg class="shrink-0 text-suave-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
                    <span class="sr-only">Pesquisar produto</span>
                    <input v-model="pesquisaProduto" type="search" class="w-full border-0 p-0 text-base focus:ring-0" placeholder="Nome do produto...">
                </label>
            </div>

            <div v-if="!categoriasFiltradas.length" class="rounded-[14px] border border-linha bg-white p-8 text-center text-[15px] text-suave">Nenhum produto encontrado.</div>

            <section v-for="categoria in categoriasFiltradas" :key="categoria.id" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-l-[6px] border-b-linha-fraca px-5 py-3.5" :class="corSecao(categoria.secao).borda">
                    <div class="flex flex-col gap-0.5">
                        <h2 class="text-[19px] font-extrabold">{{ categoria.nome }}</h2>
                        <span class="text-sm font-bold" :class="corSecao(categoria.secao).texto">{{ nomeSecao(categoria.secao) }}</span>
                    </div>
                    <span class="text-sm font-semibold text-suave-2">{{ categoria.produtos.length }} produtos</span>
                </div>

                <div v-for="produto in categoria.produtosVisiveis" :key="produto.id"
                    class="grid grid-cols-[56px_minmax(0,1fr)_minmax(0,1fr)] items-center gap-3 border-b border-linha-fraca px-5 py-3 last:border-b-0 md:grid-cols-[56px_minmax(0,1fr)_110px_100px_112px] 2xl:grid-cols-[56px_minmax(0,1fr)_110px_100px_112px_210px_auto]"
                    :class="produto.disponivel ? '' : 'opacity-60'">
                    <label :aria-label="`Imagem de ${produto.nome}`" class="relative flex h-14 w-14 cursor-pointer items-center justify-center overflow-hidden rounded-[10px] bg-[#E3E6E1] text-suave-2 ring-verde focus-within:ring-2"
                        :class="imagensPendentes[produto.id] ? 'ring-2 ring-laranja' : ''">
                        <img v-if="produto.imagem && !imagensPendentes[produto.id]" :src="`/storage/${produto.imagem}`" :alt="produto.nome" class="h-full w-full object-cover">
                        <span v-else-if="imagensPendentes[produto.id]" class="px-1 text-center text-[10px] font-bold leading-tight text-laranja-texto">Nova<br>imagem</span>
                        <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7h4l2-3h6l2 3h4v13H3zM9 13a3 3 0 1 0 6 0a3 3 0 1 0-6 0" /></svg>
                        <input type="file" accept="image/*" class="absolute inset-0 cursor-pointer opacity-0" @change="(e) => imagensPendentes[produto.id] = e.target.files[0]">
                    </label>
                    <label class="col-span-2 flex min-w-0 flex-col gap-1 md:col-span-1">
                        <span class="text-xs font-bold text-suave-2">Nome</span>
                        <input v-model="produto.nome" class="h-11 min-w-0 rounded-lg border-linha-forte px-2.5 text-base font-bold focus:border-verde focus:ring-verde">
                    </label>
                    <label class="col-span-2 flex min-w-0 flex-col gap-1 md:col-span-1">
                        <span class="text-xs font-bold text-suave-2">Preço</span>
                        <span class="flex h-11 items-center rounded-lg border border-linha-forte px-2.5 focus-within:border-verde focus-within:ring-1 focus-within:ring-verde">
                            <input v-model="produto.preco" type="number" min="0" step="0.01" inputmode="decimal" class="w-full border-0 bg-transparent p-0 text-right text-base font-extrabold focus:ring-0">
                            <span class="pl-1 text-suave-2">€</span>
                        </span>
                    </label>
                    <label class="flex min-w-0 flex-col gap-1">
                        <span class="text-xs font-bold text-suave-2">Stock</span>
                        <input v-model="produto.stock_atual" type="number" min="0" step="0.001" class="h-11 w-full min-w-0 rounded-lg border-linha-forte px-2.5 text-right text-base focus:border-verde focus:ring-verde">
                    </label>
                    <div class="col-span-3 flex flex-col gap-1 md:col-span-1">
                        <span class="text-xs font-bold text-suave-2">Ativo</span>
                        <button type="button" role="switch" :aria-checked="!!produto.disponivel" :aria-label="`${produto.nome} ativo`"
                            class="h-11 w-full rounded-full text-sm font-extrabold transition md:w-28"
                            :class="produto.disponivel ? 'bg-verde-claro2 text-verde-escuro' : 'bg-perigo-claro text-perigo-texto'"
                            @click="produto.disponivel = !produto.disponivel">{{ produto.disponivel ? 'Ativo' : 'Inativo' }}</button>
                    </div>
                    <div class="col-span-3 flex flex-col gap-1 md:col-span-4 md:col-start-2 2xl:col-span-1 2xl:col-start-auto">
                        <span class="text-xs font-bold text-suave-2">Vende em</span>
                        <div class="flex flex-wrap gap-1.5">
                            <label class="flex h-11 cursor-pointer items-center gap-1.5 rounded-full border border-linha-forte px-2.5 text-[13px] font-bold"><input v-model="produto.disponivel_restaurante" type="checkbox" class="h-[18px] w-[18px] rounded border-linha-forte text-verde focus:ring-verde">Restaurante</label>
                            <label class="flex h-11 cursor-pointer items-center gap-1.5 rounded-full border border-linha-forte px-2.5 text-[13px] font-bold"><input v-model="produto.disponivel_bar" type="checkbox" class="h-[18px] w-[18px] rounded border-linha-forte text-verde focus:ring-verde">Bar</label>
                            <label class="flex h-11 items-center gap-1.5 rounded-full border border-linha-forte px-2.5 text-[13px] font-bold" :class="Number(produto.caucao) > 0 ? 'border-laranja bg-laranja-claro text-laranja-texto' : ''">
                                Caução
                                <input v-model="produto.caucao" type="number" min="0" step="0.01" inputmode="decimal" :aria-label="`Caução de ${produto.nome}`" class="h-8 w-16 rounded-md border-linha-forte px-1.5 text-right text-[13px] text-tinta focus:border-verde focus:ring-verde">€
                            </label>
                        </div>
                    </div>
                    <div class="col-span-3 flex justify-end gap-1.5 self-end md:col-span-5 2xl:col-span-1">
                        <button type="button" class="h-11 rounded-[10px] border border-verde bg-verde-claro px-3.5 text-sm font-bold text-verde-escuro hover:bg-verde-claro2" @click="atualizarProduto(produto)">Guardar</button>
                        <button type="button" :aria-label="`Eliminar ${produto.nome}`" class="flex h-11 w-11 items-center justify-center rounded-[10px] border border-[#F0C9C2] bg-white text-perigo hover:bg-perigo-claro" @click="eliminarProduto(produto)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3" /></svg>
                        </button>
                    </div>
                    <AvisoErros :errors="errosProduto[produto.id] || {}" class="col-span-full" />
                </div>
            </section>
        </div>
    </AppLayout>
</template>
