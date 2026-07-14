<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';

const page = usePage();
const { can, hasRole } = usePermissions();
const drawerAberto = ref(false);
let polling = null;
const year = new Date().getFullYear();

const podeGerir = () => hasRole('admin') || hasRole('gerente');
const itemVisivel = (perm) => perm ? can(perm) : podeGerir();
const urgentes = () => page.props.urgentes_count ?? 0;
const ativo = (nome) => route().current(nome) || route().current(nome.replace('.index', '.*'));

const grupos = [
    { label: 'Restaurante',       items: [['Sala','sala.index','mesas.ver'],['Mesas','mesas.index','restaurante.ver'],['Pedidos','pedidos.index','pedidos.ver'],['Caixas','caixa.index','caixa.ver'],['Impressoras','impressoras.index',null]] },
    { label: 'Produtos',          items: [['Produtos','produtos.index','produtos.ver'],['Faturas/Stock','faturas-compras.index','produtos.ver']] },
    { label: 'Eventos & Reservas',items: [['Reservas','reservas.index','reservas.ver'],['Eventos','eventos.index',null]] },
    { label: 'Site',              items: [['Páginas','paginas.index',null],['Patrocinadores','patrocinadores.index',null]] },
    { label: 'Sócios',      items: [['Sócios','socios.index','socios.ver'],['Cotas','cotas.index','cotas.ver']] },
    { label: 'Relatórios',  items: [['Relatórios','relatorios.index','relatorios.ver'],['Contas da Festa','contas-festa.index','relatorios.ver']] },
    { label: 'Sistema',           items: [['Limpeza','manutencao.limpeza.index',null],['Logs','manutencao.logs.index',null],['Utilizadores','users.index','users.ver']] },
];

const gruposVisiveis = computed(() =>
    grupos.map((g) => ({ ...g, items: g.items.filter(([,,perm]) => itemVisivel(perm)) })).filter((g) => g.items.length > 0)
);

const bottomLinks = computed(() =>
    [['Início','dashboard','dashboard.ver','🏠'],['Pedidos','pedidos.index','pedidos.ver','🍽️'],['Reservas','reservas.index','reservas.ver','📋'],['Sala','sala.index','mesas.ver','🪑']]
    .filter(([,,perm]) => itemVisivel(perm))
);

onMounted(() => { polling = setInterval(() => router.reload({ only: ['urgentes_count'], preserveScroll: true }), 30000); });
onBeforeUnmount(() => clearInterval(polling));
</script>

<template>
    <div class="min-h-screen bg-amber-50 pb-20 text-stone-800 md:pb-0">

        <!-- Sidebar desktop -->
        <aside class="fixed inset-y-0 left-0 hidden w-56 flex-col border-r border-amber-200 bg-white md:flex xl:w-64">
            <div class="shrink-0 border-b border-amber-200 px-5 py-5">
                <Link :href="route('dashboard')" class="text-sm font-bold text-stone-800 xl:text-base hover:text-amber-700 transition">Associação de Santana</Link>
                <div class="mt-1 text-xs text-stone-400">Gestão interna</div>
            </div>
            <nav class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-3 pb-5 xl:p-4">
                <Link v-if="itemVisivel('dashboard.ver')" :href="route('dashboard')"
                    class="mb-3 flex min-h-9 items-center rounded-md px-3 py-2 text-xs font-bold text-stone-600 transition hover:bg-amber-50 hover:text-stone-900 xl:text-sm"
                    :class="{ 'bg-amber-600 text-white hover:bg-amber-600 hover:text-white': ativo('dashboard') }">
                    Dashboard
                </Link>
                <div v-for="grupo in gruposVisiveis" :key="grupo.label" class="mb-3">
                    <p class="mb-1 px-3 text-[10px] font-semibold uppercase tracking-widest text-stone-400 xl:text-[11px]">{{ grupo.label }}</p>
                    <Link v-for="[nome, rota] in grupo.items" :key="rota" :href="route(rota)"
                        class="flex min-h-9 items-center justify-between rounded-md px-3 py-1.5 text-xs font-medium text-stone-600 transition hover:bg-amber-50 hover:text-stone-900 xl:text-sm"
                        :class="{ 'bg-amber-600 text-white hover:bg-amber-600 hover:text-white': ativo(rota) }">
                        <span>{{ nome }}</span>
                        <span v-if="rota === 'pedidos.index' && urgentes()" class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ urgentes() }}</span>
                    </Link>
                </div>
            </nav>
        </aside>

        <!-- Drawer mobile overlay -->
        <div v-if="drawerAberto" class="fixed inset-0 z-40 bg-stone-900/40 md:hidden" @click="drawerAberto = false" />
        <aside class="fixed inset-y-0 left-0 z-[60] flex w-72 transform flex-col bg-white shadow-xl transition md:hidden" :class="drawerAberto ? 'translate-x-0' : '-translate-x-full'">
            <div class="shrink-0 border-b border-amber-200 p-4">
                <div class="flex items-center justify-between">
                    <strong class="text-stone-800">Menu</strong>
                    <button type="button" class="rounded-md border border-amber-200 px-3 py-2 text-sm font-medium text-stone-600" @click="drawerAberto = false">Fechar</button>
                </div>
            </div>
            <nav class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 pb-24">
                <Link v-if="itemVisivel('dashboard.ver')" :href="route('dashboard')"
                    class="mb-3 flex min-h-12 items-center rounded-lg px-3 py-3 font-bold text-stone-700 hover:bg-amber-50 transition"
                    :class="{ 'bg-amber-600 text-white hover:bg-amber-600': ativo('dashboard') }"
                    @click="drawerAberto = false">
                    Dashboard
                </Link>
                <div v-for="grupo in gruposVisiveis" :key="grupo.label" class="mb-4">
                    <p class="mb-1.5 px-3 text-[11px] font-semibold uppercase tracking-widest text-stone-400">{{ grupo.label }}</p>
                    <Link v-for="[nome, rota] in grupo.items" :key="rota" :href="route(rota)"
                        class="mb-1 flex min-h-12 items-center justify-between rounded-lg px-3 py-3 font-medium text-stone-700 hover:bg-amber-50 transition"
                        :class="{ 'bg-amber-600 text-white hover:bg-amber-600': ativo(rota) }"
                        @click="drawerAberto = false">
                        <span>{{ nome }}</span>
                        <span v-if="rota === 'pedidos.index' && urgentes()" class="text-amber-300">{{ urgentes() }} urgentes</span>
                    </Link>
                </div>
                <div v-if="podeGerir()" class="mt-2 border-t border-amber-100 pt-4">
                    <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-widest text-stone-400">Ecrãs de secção</p>
                    <div class="grid grid-cols-2 gap-2 px-1">
                        <a class="rounded-lg border border-amber-100 p-2 text-center text-xs font-semibold text-amber-700 hover:bg-amber-50 transition" :href="route('secao.bebidas')"          target="_blank">Bebidas</a>
                        <a class="rounded-lg border border-amber-100 p-2 text-center text-xs font-semibold text-amber-700 hover:bg-amber-50 transition" :href="route('secao.frango')"           target="_blank">Frango</a>
                        <a class="rounded-lg border border-amber-100 p-2 text-center text-xs font-semibold text-amber-700 hover:bg-amber-50 transition" :href="route('secao.comida')"           target="_blank">Comida</a>
                        <a class="rounded-lg border border-amber-100 p-2 text-center text-xs font-semibold text-amber-700 hover:bg-amber-50 transition" :href="route('secao.sobremesas')"       target="_blank">Sobremesas</a>
                        <a class="rounded-lg border border-amber-100 p-2 text-center text-xs font-semibold text-amber-700 hover:bg-amber-50 transition" :href="route('secao.acompanhamentos')"  target="_blank">Acompanhamentos</a>
                        <a class="rounded-lg border border-amber-100 p-2 text-center text-xs font-semibold text-amber-700 hover:bg-amber-50 transition" :href="route('secao.bar')"              target="_blank">Bar</a>
                        <a class="col-span-2 rounded-lg border border-amber-100 p-2 text-center text-xs font-semibold text-amber-700 hover:bg-amber-50 transition" :href="route('pos.reservas.index')" target="_blank">Reservas POS</a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Conteúdo principal -->
        <main class="backoffice-main md:pl-56 xl:pl-64">
            <header class="flex items-center justify-between border-b border-amber-200 bg-white px-4 py-3.5 lg:px-8">
                <button type="button" class="rounded-md border border-amber-200 px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-amber-50 md:hidden" @click="drawerAberto = true">Menu</button>
                <div class="hidden text-sm font-medium text-stone-500 md:block">{{ page.props.auth?.user?.name }}</div>
                <Link :href="route('logout')" method="post" as="button" class="rounded-md border border-amber-200 bg-white px-3 py-2 text-sm font-semibold text-stone-700 transition hover:bg-amber-50">Logout</Link>
            </header>
            <section class="p-4 lg:p-8">
                <div v-if="page.props.flash?.success" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">
                    {{ page.props.flash.success }}
                </div>
                <slot />
            </section>
            <footer class="px-4 pb-24 text-center text-xs text-stone-400 md:pb-6 lg:px-8">
                <span>Copyright © {{ year }} Associação de Santana.</span>
                <span class="mx-2">·</span>
                <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-semibold text-stone-500 hover:text-amber-700 transition">#CreatingDevelopingImproving4you</a>
            </footer>
        </main>

        <!-- Bottom nav mobile -->
        <nav class="fixed inset-x-0 bottom-0 z-50 grid border-t border-amber-200 bg-white p-2 md:hidden" :style="`grid-template-columns: repeat(${bottomLinks.length + 1}, 1fr)`">
            <Link v-for="[label, rota,, icon] in bottomLinks" :key="rota" :href="route(rota)"
                class="relative flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-lg px-1 py-1 text-[10px] font-bold transition"
                :class="ativo(rota) ? 'bg-amber-600 text-white' : 'text-stone-600 hover:bg-amber-50'">
                <span class="text-base leading-none">{{ icon }}</span>
                <span>{{ label }}</span>
                <span v-if="rota === 'pedidos.index' && urgentes()" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-black text-white">{{ urgentes() }}</span>
            </Link>
            <button type="button" class="flex min-h-12 flex-col items-center justify-center gap-0.5 rounded-lg px-1 py-1 text-[10px] font-bold text-stone-600 hover:bg-amber-50" @click="drawerAberto = true">
                <span class="text-base leading-none">☰</span>
                <span>Menu</span>
            </button>
        </nav>
    </div>
</template>
