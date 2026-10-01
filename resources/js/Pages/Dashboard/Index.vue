<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({ totais: Object });
const page = usePage();

const userName = page.props.auth?.user?.name ?? '';
const firstName = userName.split(' ')[0];
const hora = new Date().getHours();
const saudacao = hora < 12 ? 'Bom dia' : hora < 19 ? 'Boa tarde' : 'Boa noite';
const dataHoje = new Date().toLocaleDateString('pt-PT', { weekday: 'long', day: 'numeric', month: 'long' });

const cartoes = [
    { chave: 'mesas_livres', titulo: 'Mesas livres', sub: 'disponíveis agora', cor: 'text-verde', icone: 'M3 6h18v4H3zM6 10v9M18 10v9' },
    { chave: 'pedidos_ativos', titulo: 'Pedidos ativos', sub: 'em curso', cor: 'text-laranja', icone: 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6' },
    { chave: 'socios_em_atraso', titulo: 'Sócios em atraso', sub: 'cotas em falta', cor: 'text-perigo', icone: 'M6 8a3 3 0 1 0 6 0a3 3 0 1 0-6 0M3 20a6 6 0 0 1 12 0M16 5a3 3 0 0 1 0 6M21 20a6 6 0 0 0-4-5.6', alerta: true },
    { chave: 'pedidos_fechados_hoje', titulo: 'Fechados hoje', sub: 'pedidos concluídos', cor: 'text-verde', icone: 'M5 12l5 5L20 7' },
    { chave: 'pedidos_bar_hoje', titulo: 'Bar hoje', sub: 'pedidos de bar', cor: 'text-azul', icone: 'M8 3h8l-1 9a3 3 0 0 1-6 0zM12 15v6M8 21h8' },
];
const atalhos = [
    { rota: 'pedidos.index', nome: 'Pedidos', fundo: 'bg-laranja-claro text-laranja', icone: 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6' },
    { rota: 'reservas.index', nome: 'Reservas', fundo: 'bg-[#E8EEFA] text-azul', icone: 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4' },
    { rota: 'socios.index', nome: 'Sócios', fundo: 'bg-verde-claro text-verde', icone: 'M6 8a3 3 0 1 0 6 0a3 3 0 1 0-6 0M3 20a6 6 0 0 1 12 0M16 5a3 3 0 0 1 0 6M21 20a6 6 0 0 0-4-5.6' },
    { rota: 'relatorios.index', nome: 'Relatórios', fundo: 'bg-[#F3ECF7] text-roxo', icone: 'M3 21h18M6 17v-6M11 17V6M16 17v-4M20 17V9' },
];
</script>

<template>
    <AppLayout>
        <div class="flex flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-col gap-1">
                <p class="text-[15px] text-suave">Dashboard · {{ dataHoje }}</p>
                <h1 class="text-[30px] font-extrabold leading-tight">{{ saudacao }}<template v-if="firstName">, {{ firstName }}</template></h1>
            </div>

            <Link v-if="totais.socios_em_atraso" :href="route('socios.emAtraso')"
                class="flex flex-wrap items-center gap-3.5 rounded-[14px] border border-[#F2C7C1] bg-perigo-claro px-5 py-4 text-perigo-texto transition hover:brightness-[.98]">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] bg-perigo text-white">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l10 18H2z" /><path d="M12 10v5M12 18h.01" /></svg>
                </span>
                <span class="min-w-0 flex-1 text-[17px]"><strong class="font-extrabold">{{ totais.socios_em_atraso }} {{ totais.socios_em_atraso === 1 ? 'sócio' : 'sócios' }}</strong> com cotas em atraso</span>
                <span class="inline-flex items-center gap-1.5 text-[15px] font-bold">Ver lista
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                </span>
            </Link>

            <section aria-labelledby="agora" class="flex flex-col gap-3">
                <h2 id="agora" class="text-lg font-extrabold">Agora</h2>
                <div class="grid grid-cols-2 gap-3.5 md:grid-cols-3 xl:grid-cols-5">
                    <div v-for="c in cartoes" :key="c.chave"
                        class="flex flex-col gap-1 rounded-[14px] border p-5"
                        :class="c.alerta && totais[c.chave] ? 'border-[#F2C7C1] bg-perigo-claro' : 'border-linha bg-white'">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-bold" :class="c.alerta && totais[c.chave] ? 'text-perigo-texto' : 'text-suave'">{{ c.titulo }}</span>
                            <svg class="shrink-0" :class="c.cor" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="c.icone" /></svg>
                        </div>
                        <span class="mt-1 text-[38px] font-extrabold leading-tight" :class="c.alerta && totais[c.chave] ? 'text-perigo-texto' : ''">{{ totais[c.chave] }}</span>
                        <span class="text-sm" :class="c.alerta && totais[c.chave] ? 'text-perigo-texto' : 'text-suave-2'">{{ c.sub }}</span>
                    </div>
                </div>
            </section>

            <section aria-labelledby="acesso" class="flex flex-col gap-3">
                <h2 id="acesso" class="text-lg font-extrabold">Acesso rápido</h2>
                <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                    <Link v-for="a in atalhos" :key="a.rota" :href="route(a.rota)"
                        class="flex min-h-[76px] items-center gap-3.5 rounded-[14px] border border-linha bg-white px-[18px] text-[17px] font-bold text-tinta transition hover:border-linha-forte hover:bg-fundo">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px]" :class="a.fundo">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="a.icone" /></svg>
                        </span>
                        {{ a.nome }}
                    </Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
