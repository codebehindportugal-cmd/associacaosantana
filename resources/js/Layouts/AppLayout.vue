<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';

const page = usePage();
const { can, hasRole } = usePermissions();
const drawerAberto = ref(false);
let polling = null;
const year = new Date().getFullYear();
const links = [
    ['Dashboard', 'dashboard', 'dashboard.ver', 'Início'],
    ['Sala', 'sala.index', 'mesas.ver', 'Sala'],
    ['Mesas', 'mesas.index', 'restaurante.ver', 'Mesas'],
    ['Pedidos', 'pedidos.index', 'pedidos.ver', 'Pedidos'],
    ['Caixas', 'caixa.index', 'caixa.ver', 'Caixas'],
    ['Produtos', 'produtos.index', 'produtos.ver', 'Produtos'],
    ['Impressoras', 'impressoras.index', null, 'Impressoras'],
    ['Reservas', 'reservas.index', 'reservas.ver', 'Reservas'],
    ['Eventos', 'eventos.index', null, 'Eventos'],
    ['Páginas', 'paginas.index', null, 'Páginas'],
    ['Patrocinadores', 'patrocinadores.index', null, 'Patroc.'],
    ['Sócios', 'socios.index', 'socios.ver', 'Sócios'],
    ['Cotas', 'cotas.index', 'cotas.ver', 'Cotas'],
    ['Relatórios', 'relatorios.index', 'relatorios.ver', 'Relatórios'],
    ['Limpeza', 'manutencao.limpeza.index', null, 'Limpeza'],
    ['Logs', 'manutencao.logs.index', null, 'Logs'],
    ['Utilizadores', 'users.index', 'users.ver', 'Users'],
];
const podeGerir = () => hasRole('admin') || hasRole('gerente');
const linkVisivel = (link) => link[2] ? can(link[2]) : podeGerir();
const linksVisiveis = computed(() => links.filter(linkVisivel));
const urgentes = () => page.props.urgentes_count ?? 0;
const ativo = (nome) => route().current(nome) || route().current(nome.replace('.index', '.*'));

onMounted(() => {
    polling = setInterval(() => router.reload({ only: ['urgentes_count'], preserveScroll: true }), 30000);
});
onBeforeUnmount(() => clearInterval(polling));
</script>

<template>
    <div class="min-h-screen bg-amber-50 pb-20 text-stone-800 md:pb-0">
        <!-- Sidebar desktop -->
        <aside class="fixed inset-y-0 left-0 hidden w-56 flex-col border-r border-amber-200 bg-white md:flex xl:w-64">
            <div class="shrink-0 border-b border-amber-200 px-5 py-5">
                <div class="text-sm font-bold text-stone-800 xl:text-base">Associação de Santana</div>
                <div class="mt-1 text-xs text-stone-400">Gestão interna</div>
            </div>
            <nav class="min-h-0 flex-1 space-y-0.5 overflow-y-auto overscroll-contain p-3 pb-5 xl:p-4">
                <Link
                    v-for="link in linksVisiveis"
                    :key="link[1]"
                    :href="route(link[1])"
                    class="flex min-h-9 items-center justify-between rounded-md px-3 py-2 text-xs font-medium text-stone-600 transition hover:bg-amber-50 hover:text-stone-900 xl:text-sm"
                    :class="{ 'bg-amber-600 text-white hover:bg-amber-600 hover:text-white': ativo(link[1]) }"
                >
                    <span>{{ link[0] }}</span>
                    <span v-if="link[1] === 'pedidos.index' && urgentes()" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-800">{{ urgentes() }}</span>
                </Link>
            </nav>
            <div v-if="podeGerir()" class="shrink-0 border-t border-amber-200 p-4">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-stone-400">Ecrãs de secção</div>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs xl:text-sm">
                    <a class="truncate text-amber-700 hover:text-amber-900 transition" :href="route('secao.bebidas')" target="_blank">Bebidas</a>
                    <a class="truncate text-amber-700 hover:text-amber-900 transition" :href="route('secao.frango')" target="_blank">Frango</a>
                    <a class="truncate text-amber-700 hover:text-amber-900 transition" :href="route('secao.acompanhamentos')" target="_blank">Acompanhamentos</a>
                    <a class="truncate text-amber-700 hover:text-amber-900 transition" :href="route('secao.comida')" target="_blank">Comida</a>
                    <a class="truncate text-amber-700 hover:text-amber-900 transition" :href="route('secao.sobremesas')" target="_blank">Sobremesas</a>
                    <a class="truncate text-amber-700 hover:text-amber-900 transition" :href="route('secao.bar')" target="_blank">Bar</a>
                </div>
            </div>
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
                <Link
                    v-for="link in linksVisiveis"
                    :key="link[1]"
                    :href="route(link[1])"
                    class="mb-1 flex min-h-12 items-center justify-between rounded-lg px-3 py-3 font-medium text-stone-700 hover:bg-amber-50 transition"
                    :class="{ 'bg-amber-600 text-white hover:bg-amber-600': ativo(link[1]) }"
                    @click="drawerAberto = false"
                >
                    <span>{{ link[0] }}</span>
                    <span v-if="link[1] === 'pedidos.index' && urgentes()" class="text-amber-300">{{ urgentes() }} a terminar</span>
                </Link>
            </nav>
        </aside>

        <main class="md:pl-56 xl:pl-64">
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
                <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-semibold text-stone-500 hover:text-amber-700 transition">
                    #CreatingDevelopingImproving4you
                </a>
            </footer>
        </main>

        <!-- Bottom nav mobile -->
        <nav class="fixed inset-x-0 bottom-0 z-50 grid grid-cols-5 border-t border-amber-200 bg-white p-2 md:hidden">
            <Link
                v-for="link in linksVisiveis.slice(0, 4)"
                :key="link[1]"
                :href="route(link[1])"
                class="flex min-h-12 items-center justify-center rounded-lg px-2 py-2 text-center text-xs font-bold transition"
                :class="ativo(link[1]) ? 'bg-amber-600 text-white' : 'text-stone-600 hover:bg-amber-50'"
            >
                {{ link[3] }}
            </Link>
            <button type="button" class="min-h-12 rounded-lg px-2 py-2 text-xs font-bold text-stone-600 hover:bg-amber-50" @click="drawerAberto = true">Menu</button>
        </nav>
    </div>
</template>
