<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import ChamarComissaoModal from '@/Components/ChamarComissaoModal.vue';

defineProps({ sociosEmAtraso: Number, cobradosHoje: [Number, String], cotasHoje: Number });
const agora = ref(new Date());
let timer = null;
let refresh = null;
const euros = (v) => Number(v ?? 0).toFixed(2).replace('.', ',') + ' €';
const chamandoComissao = ref(false);
const imprimir = () => window.print();
const dataHoje = computed(() => {
    const texto = agora.value.toLocaleDateString('pt-PT', { weekday: 'long', day: 'numeric', month: 'long' });
    return texto.charAt(0).toUpperCase() + texto.slice(1);
});
onMounted(() => { timer = setInterval(() => (agora.value = new Date()), 1000); refresh = setInterval(() => router.reload({ preserveScroll: true }), 60000); });
onBeforeUnmount(() => { clearInterval(timer); clearInterval(refresh); });
</script>

<template>
    <div class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums">
        <header class="flex min-h-16 shrink-0 flex-wrap items-center justify-between gap-3 bg-escuro px-4 py-2 text-white sm:px-6">
            <div class="flex items-center gap-4">
                <div class="text-lg font-extrabold tracking-wide">ARDC Santa Ana</div>
                <div class="flex h-8 items-center rounded-full bg-verde px-3 text-sm font-bold">Tesouraria · Cotas</div>
            </div>
            <div class="flex items-center gap-3">
                <span class="mr-2 hidden text-lg font-bold text-escuro-inativo sm:block">{{ agora.toLocaleTimeString('pt-PT') }}</span>
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white" @click="chamandoComissao = true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                    Chamar comissão
                </button>
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] border border-suave bg-transparent px-4 text-[15px] font-semibold text-white" @click="router.post(route('pos.logout'))">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></svg>
                    Sair
                </button>
            </div>
        </header>

        <main class="flex min-h-0 flex-1 flex-col gap-5 p-4 sm:p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h1 class="text-[28px] font-extrabold">Cotas de sócios</h1>
                <p class="text-base text-suave">Associação de Santana · {{ dataHoje }}</p>
            </div>

            <section aria-label="Hoje" class="grid gap-4 md:grid-cols-3">
                <div class="flex flex-col gap-2 rounded-[14px] border border-linha bg-white px-6 py-5">
                    <span class="text-[15px] font-semibold text-suave">Cobrado hoje</span>
                    <span class="text-[44px] font-extrabold leading-none text-verde">{{ euros(cobradosHoje) }}</span>
                </div>
                <div class="flex flex-col gap-2 rounded-[14px] border border-linha bg-white px-6 py-5">
                    <span class="text-[15px] font-semibold text-suave">Cotas pagas hoje</span>
                    <span class="text-[44px] font-extrabold leading-none">{{ cotasHoje }}</span>
                </div>
                <Link :href="route('pos.cotas.em-atraso')" class="flex items-end justify-between gap-3 rounded-[14px] border border-[#F0C9C2] bg-perigo-claro px-6 py-5 text-perigo-texto">
                    <span class="flex flex-col gap-2">
                        <span class="text-[15px] font-semibold">Sócios com cotas em atraso</span>
                        <span class="text-[44px] font-extrabold leading-none text-perigo">{{ sociosEmAtraso }}</span>
                    </span>
                    <span class="flex items-center gap-1 text-[15px] font-bold">
                        Ver lista
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                    </span>
                </Link>
            </section>

            <Link :href="route('pos.cotas.socio.pesquisa')" class="flex min-h-[160px] flex-1 items-center gap-5 rounded-[18px] bg-verde px-6 py-8 text-white hover:bg-verde-escuro sm:gap-8 sm:px-12">
                <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-verde-escuro sm:h-28 sm:w-28">
                    <svg class="h-10 w-10 sm:h-14 sm:w-14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
                </span>
                <span class="flex min-w-0 flex-col gap-2">
                    <span class="text-3xl font-extrabold leading-tight sm:text-[44px]">Pesquisar sócio</span>
                    <span class="text-base font-medium text-verde-claro2 sm:text-xl">Por nome ou número de sócio, para receber a cota</span>
                </span>
                <span class="ml-auto hidden sm:flex">
                    <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14" /><path d="M13 6l6 6-6 6" /></svg>
                </span>
            </Link>

            <nav aria-label="Outras ações" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link :href="route('pos.cotas.socio.novo.form')" class="flex h-24 items-center gap-4 rounded-[14px] border border-linha-forte bg-white px-5 text-[19px] font-bold text-tinta">
                    <span class="flex h-[52px] w-[52px] items-center justify-center rounded-xl bg-verde-claro text-verde"><svg class="h-[26px] w-[26px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M19 8v6M22 11h-6" /></svg></span>
                    Novo sócio
                </Link>
                <Link :href="route('pos.cotas.em-atraso')" class="flex h-24 items-center gap-4 rounded-[14px] border border-linha-forte bg-white px-5 text-[19px] font-bold text-tinta">
                    <span class="flex h-[52px] w-[52px] items-center justify-center rounded-xl bg-perigo-claro text-perigo"><svg class="h-[26px] w-[26px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg></span>
                    Lista em atraso
                </Link>
                <Link :href="route('pos.cotas.resumo-dia')" class="flex h-24 items-center gap-4 rounded-[14px] border border-linha-forte bg-white px-5 text-[19px] font-bold text-tinta">
                    <span class="flex h-[52px] w-[52px] items-center justify-center rounded-xl bg-fundo text-suave"><svg class="h-[26px] w-[26px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18" /><path d="M8 17v-5M13 17V8M18 17v-8" /></svg></span>
                    Resumo do dia
                </Link>
                <button type="button" class="flex h-24 items-center gap-4 rounded-[14px] border border-linha-forte bg-white px-5 text-left text-[19px] font-bold text-tinta" @click="imprimir">
                    <span class="flex h-[52px] w-[52px] items-center justify-center rounded-xl bg-fundo text-suave"><svg class="h-[26px] w-[26px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" /></svg></span>
                    Imprimir resumo
                </button>
            </nav>
        </main>

        <ChamarComissaoModal
            v-if="chamandoComissao"
            operador-nome="Tesouraria"
            @fechar="chamandoComissao = false"
        />
    </div>
</template>
