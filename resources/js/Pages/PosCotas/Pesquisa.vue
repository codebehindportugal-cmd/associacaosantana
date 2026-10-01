<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
const props = defineProps({ socios: Array, query: String });
const q = ref(props.query || '');
let timeout = null;
const teclas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'.split('');
const pesquisar = () => router.get(route('pos.cotas.socio.pesquisa'), { q: q.value }, { preserveState: true, replace: true });
watch(q, () => { clearTimeout(timeout); timeout = setTimeout(pesquisar, 300); });
onMounted(() => document.querySelector('#pesquisa-socio')?.focus());
const tecla = (t) => { if (t === 'del') q.value = q.value.slice(0, -1); else q.value += t; };

// A cota e anual: o atraso mostra-se em anos ("1 ano em atraso", "2 anos em atraso")
const estadoCota = (socio) => {
    if (socio.cota_em_dia) return 'Em dia';
    const anos = Number(socio.anos_em_atraso ?? 0);
    if (anos <= 0) return 'Em atraso';
    return anos === 1 ? '1 ano em atraso' : `${anos} anos em atraso`;
};
const totalLabel = computed(() => (props.socios.length === 1 ? '1 sócio encontrado' : `${props.socios.length} sócios encontrados`));
</script>

<template>
    <div class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums lg:h-screen">
        <header class="flex h-16 shrink-0 items-center justify-between gap-3 bg-escuro px-4 text-white sm:px-6">
            <div class="flex items-center gap-4">
                <Link :href="route('pos.cotas.index')" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    Início
                </Link>
                <div class="text-lg font-extrabold">Pesquisar sócio</div>
            </div>
            <div class="hidden text-sm text-[#B9C4BE] sm:block">Tesouraria · Cotas</div>
        </header>

        <div class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_540px]">
            <section aria-label="Resultados" class="flex min-h-0 flex-col gap-4 p-4 sm:px-6 sm:py-5">
                <div>
                    <label for="pesquisa-socio" class="mb-1.5 block text-sm font-bold text-suave">Nome ou número de sócio</label>
                    <div class="flex h-[72px] items-center gap-3 rounded-[14px] border-2 border-verde bg-white px-5">
                        <svg class="h-7 w-7 shrink-0 text-suave-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                        <input id="pesquisa-socio" v-model="q" class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[30px] font-extrabold text-tinta placeholder:text-suave-2 focus:ring-0" placeholder="Pesquisar sócio" autocomplete="off">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-[15px] font-semibold text-suave">{{ totalLabel }}</span>
                    <Link :href="route('pos.cotas.socio.novo.form')" class="text-[15px] font-bold text-verde hover:text-verde-escuro">+ Novo sócio</Link>
                </div>

                <div class="flex min-h-0 flex-1 flex-col gap-2.5 overflow-auto">
                    <div v-if="!socios.length" class="flex flex-col items-center gap-4 rounded-[14px] border border-dashed border-linha-forte bg-white px-6 py-10 text-center">
                        <span class="text-xl font-bold">Nenhum sócio encontrado</span>
                        <Link :href="route('pos.cotas.socio.novo.form')" class="flex h-[60px] items-center rounded-xl bg-verde px-7 text-lg font-extrabold text-white hover:bg-verde-escuro">+ Novo sócio</Link>
                    </div>

                    <Link
                        v-for="socio in socios"
                        :key="socio.id"
                        :href="route('pos.cotas.socio', socio.id)"
                        class="flex min-h-[84px] flex-wrap items-center gap-x-4 gap-y-2 rounded-[14px] border border-linha bg-white px-5 py-3 text-tinta"
                    >
                        <span class="w-16 shrink-0 text-[15px] font-bold text-suave-2">N.º {{ socio.numero_socio }}</span>
                        <span class="min-w-0 flex-1 text-[22px] font-bold">{{ socio.nome }}</span>
                        <span
                            class="flex h-9 items-center gap-2 rounded-full px-3.5 text-[15px] font-bold"
                            :class="socio.cota_em_dia ? 'bg-verde-claro text-verde-escuro' : 'bg-perigo-claro text-perigo-texto'"
                        >
                            <span class="h-2 w-2 rounded-full" :class="socio.cota_em_dia ? 'bg-verde-ok' : 'bg-perigo'" />
                            {{ estadoCota(socio) }}
                        </span>
                        <svg class="h-6 w-6 text-suave-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                    </Link>
                </div>
            </section>

            <aside aria-label="Teclado" class="flex min-h-0 flex-col gap-2.5 border-t border-linha bg-white p-4 sm:px-6 sm:py-5 lg:border-l lg:border-t-0">
                <div class="grid grid-cols-6 gap-2">
                    <button
                        v-for="t in teclas"
                        :key="t"
                        type="button"
                        class="h-14 rounded-[10px] border border-linha-forte text-xl font-bold text-tinta sm:h-[72px] sm:text-2xl"
                        :class="/[0-9]/.test(t) ? 'bg-fundo' : 'bg-white'"
                        @click="tecla(t)"
                    >
                        {{ t }}
                    </button>
                </div>
                <div class="mt-auto grid grid-cols-[2fr_1fr] gap-2">
                    <button type="button" class="h-14 rounded-[10px] border border-linha-forte bg-fundo text-lg font-bold text-tinta sm:h-[72px]" @click="tecla(' ')">Espaço</button>
                    <button type="button" class="flex h-14 items-center justify-center gap-2 rounded-[10px] border border-[#F0C9C2] bg-perigo-claro text-lg font-bold text-perigo-texto sm:h-[72px]" @click="tecla('del')">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z" /><path d="M18 9l-6 6M12 9l6 6" /></svg>
                        Apagar
                    </button>
                </div>
            </aside>
        </div>
    </div>
</template>
