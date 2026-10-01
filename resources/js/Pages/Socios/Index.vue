<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Paginacao from '@/Components/Paginacao.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ socios: Object, filters: Object });

const pesquisa = ref(props.filters?.pesquisa ?? '');
const estado = ref(props.filters?.estado ?? '');
let debounce = null;

const aplicarFiltros = () => {
    router.get(route('socios.index'), {
        pesquisa: pesquisa.value || undefined,
        estado: estado.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const pesquisar = () => {
    clearTimeout(debounce);
    debounce = setTimeout(aplicarFiltros, 350);
};

const escolherEstado = (valor) => {
    estado.value = valor;
    aplicarFiltros();
};

const eliminarSocio = (socio) => {
    if (confirm(`Eliminar o sócio ${socio.nome}?`)) {
        router.delete(route('socios.destroy', socio.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1.5">
                    <h1 class="text-[30px] font-extrabold leading-tight">Sócios</h1>
                    <p v-if="socios?.total !== undefined" class="text-[15px] text-suave">{{ socios.total }} {{ socios.total === 1 ? 'sócio' : 'sócios' }}</p>
                </div>
                <Link :href="route('socios.create')" class="inline-flex h-12 items-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white hover:bg-verde-escuro">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                    Novo sócio
                </Link>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <label class="flex h-12 min-w-0 flex-[1_1_300px] items-center gap-2.5 rounded-[10px] border border-linha-forte bg-white px-3.5 text-suave-2">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
                    <span class="sr-only">Procurar sócio</span>
                    <input v-model="pesquisa" type="search" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-tinta placeholder:text-suave-2 focus:ring-0" placeholder="Nome, n.º de sócio ou terra…" @input="pesquisar">
                </label>
                <div role="group" aria-label="Filtrar por estado" class="flex flex-wrap gap-2">
                    <button
                        v-for="opcao in [['', 'Todos'], ['ativo', 'Ativos'], ['inativo', 'Inativos']]"
                        :key="opcao[0]"
                        type="button"
                        :aria-pressed="estado === opcao[0]"
                        class="h-11 rounded-full px-4 text-sm font-bold"
                        :class="estado === opcao[0] ? 'bg-tinta text-white' : 'border border-linha-forte bg-white text-tinta hover:bg-fundo'"
                        @click="escolherEstado(opcao[0])"
                    >
                        {{ opcao[1] }}
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <!-- Ecrãs largos: tabela -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full border-collapse text-base">
                        <thead>
                            <tr class="text-left text-[13px] text-suave-2">
                                <th scope="col" class="w-[70px] border-b border-linha-fraca px-5 py-3.5 font-semibold">N.º</th>
                                <th scope="col" class="border-b border-linha-fraca px-3 py-3.5 font-semibold">Nome</th>
                                <th scope="col" class="border-b border-linha-fraca px-3 py-3.5 font-semibold">Terra</th>
                                <th scope="col" class="border-b border-linha-fraca px-3 py-3.5 font-semibold">Telefone</th>
                                <th scope="col" class="border-b border-linha-fraca px-3 py-3.5 font-semibold">Cota</th>
                                <th scope="col" class="border-b border-linha-fraca px-5 py-3.5 text-right font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="socio in socios.data" :key="socio.id" class="border-b border-linha-fraca">
                                <td class="px-5 py-2.5 font-extrabold text-suave">{{ socio.numero_socio }}</td>
                                <td class="px-3 py-2.5"><Link :href="route('socios.show', socio.id)" class="font-bold text-tinta hover:text-verde">{{ socio.nome }}</Link></td>
                                <td class="px-3 py-2.5 text-suave">{{ socio.morada || '—' }}</td>
                                <td class="px-3 py-2.5 text-suave">{{ socio.telefone || '—' }}</td>
                                <td class="px-3 py-2.5">
                                    <span class="inline-flex h-7 items-center gap-1.5 whitespace-nowrap rounded-full px-3 text-[13px] font-extrabold" :class="socio.cota_em_dia ? 'bg-verde-claro text-verde-escuro' : 'bg-perigo-claro text-perigo-texto'">
                                        <span class="h-[7px] w-[7px] rounded-full" :class="socio.cota_em_dia ? 'bg-verde-ok' : 'bg-perigo'" />
                                        {{ socio.cota_em_dia ? 'Em dia' : 'Em atraso' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-2.5 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <Link :href="route('socios.show', socio.id)" class="inline-flex h-10 items-center rounded-[10px] bg-verde-claro px-3.5 text-sm font-bold text-verde-escuro hover:bg-verde-claro2">Ver</Link>
                                        <Link :href="route('socios.edit', socio.id)" class="inline-flex h-10 items-center rounded-[10px] border border-linha-forte px-3.5 text-sm font-semibold text-tinta hover:bg-fundo">Editar</Link>
                                        <button type="button" :aria-label="`Eliminar ${socio.nome}`" class="inline-flex h-10 w-10 items-center justify-center rounded-[10px] border border-[#F0D3CD] bg-white text-perigo hover:bg-perigo-claro" @click="eliminarSocio(socio)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Ecrãs estreitos: cartões -->
                <ul class="divide-y divide-linha-fraca md:hidden">
                    <li v-for="socio in socios.data" :key="socio.id" class="flex flex-col gap-1 px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="font-extrabold text-suave">{{ socio.numero_socio }}</span>
                            <Link :href="route('socios.show', socio.id)" class="min-w-0 flex-1 truncate font-bold text-tinta">{{ socio.nome }}</Link>
                            <span class="inline-flex h-7 shrink-0 items-center gap-1.5 rounded-full px-3 text-[13px] font-extrabold" :class="socio.cota_em_dia ? 'bg-verde-claro text-verde-escuro' : 'bg-perigo-claro text-perigo-texto'">
                                <span class="h-[7px] w-[7px] rounded-full" :class="socio.cota_em_dia ? 'bg-verde-ok' : 'bg-perigo'" />
                                {{ socio.cota_em_dia ? 'Em dia' : 'Em atraso' }}
                            </span>
                        </div>
                        <div class="text-sm text-suave">{{ socio.morada || '—' }}</div>
                        <div class="text-sm text-suave">{{ socio.telefone || '—' }}</div>
                        <div class="mt-1.5 flex gap-2">
                            <Link :href="route('socios.show', socio.id)" class="inline-flex h-11 flex-1 items-center justify-center rounded-[10px] bg-verde-claro text-sm font-bold text-verde-escuro">Ver</Link>
                            <Link :href="route('socios.edit', socio.id)" class="inline-flex h-11 flex-1 items-center justify-center rounded-[10px] border border-linha-forte text-sm font-semibold text-tinta">Editar</Link>
                            <button type="button" :aria-label="`Eliminar ${socio.nome}`" class="inline-flex h-11 w-11 items-center justify-center rounded-[10px] border border-[#F0D3CD] bg-white text-perigo" @click="eliminarSocio(socio)">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                            </button>
                        </div>
                    </li>
                </ul>

                <div v-if="!socios.data?.length" class="p-8 text-center text-suave">Não há sócios para estes filtros.</div>

                <Paginacao :dados="socios" etiqueta="sócios" />
            </div>
        </div>
    </AppLayout>
</template>
