<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({ totais: Object });
const page = usePage();

const userName = page.props.auth?.user?.name ?? '';
const firstName = userName.split(' ')[0];
const hora = new Date().getHours();
const saudacao = hora < 12 ? 'Bom dia' : hora < 19 ? 'Boa tarde' : 'Boa noite';
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <p class="text-sm text-stone-400">{{ saudacao }}, <span class="font-semibold text-stone-600">{{ firstName }}</span></p>
            <h1 class="mt-0.5 text-2xl font-bold text-stone-800">Dashboard</h1>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">

            <div class="flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-100">
                <div class="flex items-start justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-stone-400">Mesas livres</p>
                    <div class="rounded-lg bg-stone-50 p-1.5">
                        <svg class="h-4 w-4 text-stone-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-stone-800">{{ totais.mesas_livres }}</p>
                <p class="mt-1 text-xs text-stone-400">disponíveis agora</p>
            </div>

            <div class="flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-100">
                <div class="flex items-start justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Pedidos ativos</p>
                    <div class="rounded-lg bg-amber-50 p-1.5">
                        <svg class="h-4 w-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-stone-800">{{ totais.pedidos_ativos }}</p>
                <p class="mt-1 text-xs text-amber-500">em curso</p>
            </div>

            <div class="flex flex-col rounded-xl p-5 shadow-sm ring-1 transition-colors"
                :class="totais.socios_em_atraso ? 'bg-red-50 ring-red-100' : 'bg-white ring-stone-100'">
                <div class="flex items-start justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide"
                        :class="totais.socios_em_atraso ? 'text-red-500' : 'text-stone-400'">Sócios em atraso</p>
                    <div class="rounded-lg p-1.5" :class="totais.socios_em_atraso ? 'bg-red-100' : 'bg-stone-50'">
                        <svg class="h-4 w-4" :class="totais.socios_em_atraso ? 'text-red-500' : 'text-stone-400'"
                            fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold" :class="totais.socios_em_atraso ? 'text-red-700' : 'text-stone-800'">
                    {{ totais.socios_em_atraso }}
                </p>
                <p class="mt-1 text-xs" :class="totais.socios_em_atraso ? 'text-red-400' : 'text-stone-400'">cotas em falta</p>
            </div>

            <div class="flex flex-col rounded-xl bg-white p-5 shadow-sm ring-1 ring-stone-100">
                <div class="flex items-start justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Fechados hoje</p>
                    <div class="rounded-lg bg-emerald-50 p-1.5">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-stone-800">{{ totais.pedidos_fechados_hoje }}</p>
                <p class="mt-1 text-xs text-emerald-500">pedidos concluídos</p>
            </div>

            <div class="flex flex-col rounded-xl bg-emerald-50 p-5 shadow-sm ring-1 ring-emerald-100">
                <div class="flex items-start justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Bar hoje</p>
                    <div class="rounded-lg bg-emerald-100 p-1.5">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold text-emerald-800">{{ totais.pedidos_bar_hoje }}</p>
                <p class="mt-1 text-xs text-emerald-600">pedidos de bar</p>
            </div>
        </div>

        <Link v-if="totais.socios_em_atraso" :href="route('socios.emAtraso')"
            class="mt-5 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-semibold text-red-700 transition hover:border-red-300 hover:bg-red-100">
            <svg class="h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="flex-1">
                <strong>{{ totais.socios_em_atraso }}</strong> {{ totais.socios_em_atraso === 1 ? 'sócio' : 'sócios' }} com cotas em atraso
            </span>
            <svg class="h-4 w-4 shrink-0 text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </Link>

        <div class="mt-8">
            <h2 class="mb-3 text-xs font-bold uppercase tracking-widest text-stone-400">Acesso rápido</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">

                <Link :href="route('pedidos.index')"
                    class="group flex items-center gap-3 rounded-xl border border-stone-100 bg-white p-4 text-sm font-semibold text-stone-700 shadow-sm transition hover:border-amber-200 hover:bg-amber-50 hover:text-amber-800">
                    <div class="rounded-lg bg-amber-50 p-2 transition group-hover:bg-amber-100">
                        <svg class="h-4 w-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    Pedidos
                </Link>

                <Link :href="route('reservas.index')"
                    class="group flex items-center gap-3 rounded-xl border border-stone-100 bg-white p-4 text-sm font-semibold text-stone-700 shadow-sm transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800">
                    <div class="rounded-lg bg-blue-50 p-2 transition group-hover:bg-blue-100">
                        <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    Reservas
                </Link>

                <Link :href="route('socios.index')"
                    class="group flex items-center gap-3 rounded-xl border border-stone-100 bg-white p-4 text-sm font-semibold text-stone-700 shadow-sm transition hover:border-violet-200 hover:bg-violet-50 hover:text-violet-800">
                    <div class="rounded-lg bg-violet-50 p-2 transition group-hover:bg-violet-100">
                        <svg class="h-4 w-4 text-violet-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    Sócios
                </Link>

                <Link :href="route('relatorios.index')"
                    class="group flex items-center gap-3 rounded-xl border border-stone-100 bg-white p-4 text-sm font-semibold text-stone-700 shadow-sm transition hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800">
                    <div class="rounded-lg bg-emerald-50 p-2 transition group-hover:bg-emerald-100">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    Relatórios
                </Link>
            </div>
        </div>

    </AppLayout>
</template>
