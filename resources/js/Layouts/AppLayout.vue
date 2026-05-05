<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';

const page = usePage();
const { can, hasRole } = usePermissions();
const drawerAberto = ref(false);
let polling = null;
const links = [
    ['Dashboard', 'dashboard', 'dashboard.ver', 'Início'],
    ['Mesas', 'mesas.index', 'mesas.ver', 'Mesas'],
    ['Pedidos', 'pedidos.index', 'pedidos.ver', 'Pedidos'],
    ['Caixas', 'caixa.index', 'pedidos.ver', 'Caixas'],
    ['Produtos', 'produtos.index', 'pedidos.ver', 'Produtos'],
    ['Reservas', 'reservas.index', 'reservas.ver', 'Reservas'],
    ['Sócios', 'socios.index', 'socios.ver', 'Sócios'],
    ['Cotas', 'cotas.index', 'cotas.ver', 'Cotas'],
    ['Relatórios', 'relatorios.index', 'relatorios.ver', 'Relatórios'],
];
const podeGerir = () => hasRole('admin') || hasRole('gerente');
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
                <Link v-for="link in links" v-show="can(link[2])" :key="link[1]" :href="route(link[1])" class="flex items-center justify-between rounded-md px-3 py-2 text-xs font-medium hover:bg-slate-100 xl:text-sm" :class="{ 'bg-slate-900 text-white hover:bg-slate-900': ativo(link[1]) }">
                    <span>{{ link[0] }}</span><span v-if="link[1] === 'pedidos.index' && urgentes()" class="rounded-full bg-red-600 px-2 py-0.5 text-[11px] text-white">{{ urgentes() }}⚡</span>
                </Link>
                <Link v-if="hasRole('admin')" :href="route('users.index')" class="block rounded-md px-3 py-2 text-xs font-medium hover:bg-slate-100 xl:text-sm">Utilizadores</Link>
            </nav>
            <div v-if="podeGerir()" class="absolute bottom-0 w-full border-t border-slate-200 p-4 text-sm">
                <div class="mb-2 text-xs font-semibold uppercase text-slate-500">Ecrãs de secção</div>
                <a class="mr-2 text-emerald-700" :href="route('secao.bebidas')" target="_blank">Bebidas</a>
                <a class="mr-2 text-emerald-700" :href="route('secao.comida')" target="_blank">Comida</a>
                <a class="mr-2 text-emerald-700" :href="route('secao.sobremesas')" target="_blank">Sobremesas</a>
                <a class="text-emerald-700" :href="route('secao.bar')" target="_blank">Bar</a>
            </div>
        </aside>

        <div v-if="drawerAberto" class="fixed inset-0 z-40 bg-slate-950/40 md:hidden" @click="drawerAberto = false"></div>
        <aside class="fixed inset-y-0 left-0 z-50 w-72 transform bg-white p-4 shadow-xl transition md:hidden" :class="drawerAberto ? 'translate-x-0' : '-translate-x-full'">
            <div class="mb-4 flex items-center justify-between"><strong>Menu</strong><button class="rounded-md border px-3 py-2" @click="drawerAberto = false">Fechar</button></div>
            <Link v-for="link in links" v-show="can(link[2])" :key="link[1]" :href="route(link[1])" class="mb-2 flex justify-between rounded-lg px-3 py-3 font-bold hover:bg-slate-100" @click="drawerAberto = false"><span>{{ link[0] }}</span><span v-if="link[1] === 'pedidos.index' && urgentes()" class="text-red-600">{{ urgentes() }}⚡</span></Link>
        </aside>

        <main class="md:pl-56 xl:pl-64">
            <header class="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-4 lg:px-8">
                <button class="rounded-md border border-slate-300 px-3 py-2 font-bold md:hidden" @click="drawerAberto = true">Menu</button>
                <div class="hidden text-sm text-slate-600 md:block">{{ page.props.auth?.user?.name }}</div>
                <Link :href="route('logout')" method="post" as="button" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Logout</Link>
            </header>
            <section class="p-4 lg:p-8"><slot /></section>
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-slate-200 bg-white p-2 md:hidden">
            <Link v-for="link in links.slice(0, 4)" v-show="can(link[2])" :key="link[1]" :href="route(link[1])" class="rounded-lg px-2 py-2 text-center text-xs font-black" :class="ativo(link[1]) ? 'bg-slate-900 text-white' : 'text-slate-600'">{{ link[3] }}</Link>
            <button class="rounded-lg px-2 py-2 text-xs font-black text-slate-600" @click="drawerAberto = true">Menu</button>
        </nav>
    </div>
</template>
