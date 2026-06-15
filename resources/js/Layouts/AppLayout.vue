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
    <div class="min-h-screen bg-slate-100 pb-20 text-slate-900 md:pb-0">
        <aside class="fixed inset-y-0 left-0 hidden w-56 border-r border-slate-200 bg-white md:block xl:w-64">
            <div class="border-b border-slate-200 px-5 py-5">
                <div class="text-base font-bold xl:text-lg">Associação de Santana</div>
                <div class="mt-1 text-xs text-slate-500">Gestão interna</div>
            </div>
            <nav class="space-y-1 p-3 xl:p-4">
                <Link v-for="link in linksVisiveis" :key="link[1]" :href="route(link[1])" class="flex items-center justify-between rounded-md px-3 py-2 text-xs font-medium hover:bg-slate-100 xl:text-sm" :class="{ 'bg-slate-900 text-white hover:bg-slate-900': ativo(link[1]) }">
                    <span>{{ link[0] }}</span><span v-if="link[1] === 'pedidos.index' && urgentes()" class="rounded-full bg-amber-600 px-2 py-0.5 text-[11px] text-white">{{ urgentes() }} a terminar</span>
                </Link>
            </nav>
            <div v-if="podeGerir()" class="absolute bottom-0 w-full border-t border-slate-200 p-4 text-sm">
                <div class="mb-2 text-xs font-semibold uppercase text-slate-500">Ecrãs de secção</div>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs xl:text-sm">
                    <a class="truncate text-emerald-700" :href="route('secao.bebidas')" target="_blank">Bebidas</a>
                    <a class="truncate text-emerald-700" :href="route('secao.frango')" target="_blank">Frango</a>
                    <a class="truncate text-emerald-700" :href="route('secao.acompanhamentos')" target="_blank">Acompanhamentos</a>
                    <a class="truncate text-emerald-700" :href="route('secao.comida')" target="_blank">Comida</a>
                    <a class="truncate text-emerald-700" :href="route('secao.sobremesas')" target="_blank">Sobremesas</a>
                    <a class="truncate text-emerald-700" :href="route('secao.bar')" target="_blank">Bar</a>
                </div>
            </div>
        </aside>

        <div v-if="drawerAberto" class="fixed inset-0 z-40 bg-slate-950/40 md:hidden" @click="drawerAberto = false"></div>
        <aside class="fixed inset-y-0 left-0 z-[60] w-72 transform bg-white p-4 shadow-xl transition md:hidden" :class="drawerAberto ? 'translate-x-0' : '-translate-x-full'">
            <div class="mb-4 flex items-center justify-between"><strong>Menu</strong><button type="button" class="rounded-md border px-3 py-2" @click="drawerAberto = false">Fechar</button></div>
            <Link v-for="link in linksVisiveis" :key="link[1]" :href="route(link[1])" class="mb-2 flex justify-between rounded-lg px-3 py-3 font-bold hover:bg-slate-100" @click="drawerAberto = false"><span>{{ link[0] }}</span><span v-if="link[1] === 'pedidos.index' && urgentes()" class="text-amber-700">{{ urgentes() }} a terminar</span></Link>
        </aside>

        <main class="md:pl-56 xl:pl-64">
            <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4 lg:px-8">
                <button type="button" class="rounded-md border border-slate-300 px-3 py-2 font-bold md:hidden" @click="drawerAberto = true">Menu</button>
                <div class="hidden text-sm text-slate-600 md:block">{{ page.props.auth?.user?.name }}</div>
                <Link :href="route('logout')" method="post" as="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Logout</Link>
            </header>
            <section class="p-4 lg:p-8"><slot /></section>
            <footer class="px-4 pb-24 text-center text-xs text-slate-500 md:pb-6 lg:px-8">
                <span>Copyright © {{ year }} Associação de Santana.</span>
                <span class="mx-2">·</span>
                <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-semibold text-slate-700 hover:text-slate-950">
                    #CreatingDevelopingImproving4you
                </a>
            </footer>
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-slate-200 bg-white p-2 md:hidden">
            <Link v-for="link in linksVisiveis.slice(0, 4)" :key="link[1]" :href="route(link[1])" class="rounded-lg px-2 py-2 text-center text-xs font-black" :class="ativo(link[1]) ? 'bg-slate-900 text-white' : 'text-slate-600'">{{ link[3] }}</Link>
            <button type="button" class="rounded-lg px-2 py-2 text-xs font-black text-slate-600" @click="drawerAberto = true">Menu</button>
        </nav>
    </div>
</template>
