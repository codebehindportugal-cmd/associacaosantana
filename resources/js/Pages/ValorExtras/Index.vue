<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    valores: Array,
    dataFiltro: String,
    resumo: Object,
})

const dataEscolhida = ref(props.dataFiltro)

watch(dataEscolhida, (val) => {
    router.get(route('valor-extras.index'), { data: val }, { preserveScroll: true })
})

const form = useForm({
    tipo: 'receita',
    descricao: '',
    valor: '',
    categoria: '',
    observacoes: '',
})

const submeter = () => {
    form
        .transform((d) => ({ ...d, data: dataEscolhida.value }))
        .post(route('valor-extras.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset('descricao', 'valor', 'categoria', 'observacoes'),
        })
}

const eliminar = (id) => {
    if (confirm('Eliminar este registo?')) {
        router.delete(route('valor-extras.destroy', id), { preserveScroll: true })
    }
}

const euros = (v) => Number(v ?? 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' \u20ac'

const CATEGORIAS_RECEITA = ['Patrocinador', 'Donativo', 'Subs\u00eddio', 'Outro']
const CATEGORIAS_DESPESA = ['Banda / Anima\u00e7\u00e3o', 'Equipamento', 'Material', 'Servi\u00e7o externo', 'Outro']
const categorias = ref(form.tipo === 'receita' ? CATEGORIAS_RECEITA : CATEGORIAS_DESPESA)

// Navegar dia a dia (só muda a data escolhida; o watcher acima recarrega)
const mudarDia = (delta) => {
    const base = dataEscolhida.value ? new Date(dataEscolhida.value + 'T12:00:00') : new Date()
    base.setDate(base.getDate() + delta)
    const p = (n) => String(n).padStart(2, '0')
    dataEscolhida.value = `${base.getFullYear()}-${p(base.getMonth() + 1)}-${p(base.getDate())}`
}
const dataLegivel = computed(() => {
    if (!dataEscolhida.value) return ''
    return new Date(dataEscolhida.value + 'T12:00:00').toLocaleDateString('pt-PT', { day: 'numeric', month: 'long' })
})

watch(() => form.tipo, (val) => {
    categorias.value = val === 'receita' ? CATEGORIAS_RECEITA : CATEGORIAS_DESPESA
    form.categoria = ''
})
</script>

<template>
    <AppLayout title="Valores Extras">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-[30px] font-extrabold leading-tight">Valores extra</h1>
                    <p class="text-[15px] text-suave">Receitas e despesas do dia que não passam pela caixa: patrocínios, donativos, banda, material.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-icone" aria-label="Dia anterior" @click="mudarDia(-1)">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                    </button>
                    <label class="flex h-12 items-center gap-2 rounded-[10px] border border-linha-forte bg-white pl-3.5 pr-2 text-sm font-semibold text-suave">
                        Dia
                        <input v-model="dataEscolhida" type="date" class="h-10 border-0 bg-transparent p-0 pr-1 text-base font-bold text-tinta focus:ring-0">
                    </label>
                    <button type="button" class="btn-icone" aria-label="Dia seguinte" @click="mudarDia(1)">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                    </button>
                </div>
            </div>

            <!-- Resumo do dia -->
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="cartao p-4 sm:p-5">
                    <div class="text-sm font-bold text-verde-escuro">Receitas</div>
                    <div class="mt-1 text-[28px] font-extrabold leading-none text-verde sm:text-[32px]">{{ euros(resumo.total_receitas) }}</div>
                </div>
                <div class="cartao p-4 sm:p-5">
                    <div class="text-sm font-bold text-perigo-texto">Despesas</div>
                    <div class="mt-1 text-[28px] font-extrabold leading-none text-perigo sm:text-[32px]">{{ euros(resumo.total_despesas) }}</div>
                </div>
                <div class="rounded-[14px] p-4 sm:p-5" :class="resumo.saldo >= 0 ? 'bg-escuro text-white' : 'bg-laranja-claro text-laranja-texto'">
                    <div class="text-sm font-bold opacity-90">Saldo do dia</div>
                    <div class="mt-1 text-[28px] font-extrabold leading-none sm:text-[32px]">{{ euros(resumo.saldo) }}</div>
                </div>
            </div>

            <!-- Novo registo -->
            <form class="cartao flex flex-col gap-4 p-4 sm:p-5" @submit.prevent="submeter">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-extrabold">Novo registo</h2>
                    <div class="flex rounded-[12px] bg-fundo p-1" role="radiogroup" aria-label="Tipo de registo">
                        <button type="button" role="radio" class="h-11 rounded-[10px] px-5 text-[15px] font-bold transition" :class="form.tipo === 'receita' ? 'bg-verde text-white' : 'text-suave'" :aria-checked="form.tipo === 'receita'" @click="form.tipo = 'receita'">Receita</button>
                        <button type="button" role="radio" class="h-11 rounded-[10px] px-5 text-[15px] font-bold transition" :class="form.tipo === 'despesa' ? 'bg-perigo text-white' : 'text-suave'" :aria-checked="form.tipo === 'despesa'" @click="form.tipo = 'despesa'">Despesa</button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px]">
                    <label class="rotulo">Descrição
                        <input v-model="form.descricao" type="text" placeholder="Ex.: Patrocinador XYZ, Banda" class="campo" required>
                    </label>
                    <label class="rotulo">Valor
                        <span class="relative block">
                            <input v-model="form.valor" type="number" step="0.01" min="0.01" placeholder="0,00" inputmode="decimal" class="campo pr-9 text-right text-lg font-bold" required>
                            <span class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-suave">€</span>
                        </span>
                    </label>
                </div>

                <fieldset class="flex flex-col gap-1.5">
                    <legend class="mb-1.5 text-sm font-semibold text-suave">Categoria</legend>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="cat in categorias" :key="cat" class="cursor-pointer">
                            <input v-model="form.categoria" type="radio" :value="cat" class="peer sr-only">
                            <span class="inline-flex h-11 items-center rounded-full border px-4 text-sm font-bold transition peer-focus-visible:ring-2 peer-focus-visible:ring-verde" :class="form.categoria === cat ? 'border-tinta bg-tinta text-white' : 'border-linha-forte bg-white hover:bg-fundo'">{{ cat }}</span>
                        </label>
                        <button v-if="form.categoria" type="button" class="h-11 px-2 text-sm font-semibold text-suave underline" @click="form.categoria = ''">Sem categoria</button>
                    </div>
                </fieldset>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <label class="rotulo">Observações (opcional)
                        <input v-model="form.observacoes" type="text" class="campo">
                    </label>
                    <button type="submit" :disabled="form.processing" class="inline-flex h-12 items-center justify-center gap-2 rounded-[10px] px-6 text-[15px] font-bold text-white transition disabled:opacity-60" :class="form.tipo === 'receita' ? 'bg-verde hover:bg-verde-escuro' : 'bg-perigo hover:opacity-90'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        {{ form.processing ? 'A guardar...' : (form.tipo === 'receita' ? 'Adicionar receita' : 'Adicionar despesa') }}
                    </button>
                </div>
                <div v-if="Object.keys(form.errors).length" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-semibold text-perigo-texto">
                    <div v-for="erro in form.errors" :key="erro">{{ erro }}</div>
                </div>
            </form>

            <!-- Registos -->
            <h2 class="text-lg font-extrabold">Registos de {{ dataLegivel }}</h2>
            <div class="cartao overflow-hidden">
                <div class="hidden grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1fr)_120px_44px] gap-4 border-b border-linha-fraca px-5 py-3 text-[13px] font-semibold text-suave md:grid">
                    <span>Descrição</span><span>Categoria</span><span>Observações</span><span class="text-right">Valor</span><span></span>
                </div>
                <ul class="divide-y divide-linha-fraca">
                    <li v-for="v in valores" :key="v.id" class="grid grid-cols-[minmax(0,1fr)_auto_44px] items-center gap-x-3 gap-y-1 px-4 py-3 sm:px-5 md:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_minmax(0,1fr)_120px_44px] md:gap-4">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-extrabold" :class="v.tipo === 'receita' ? 'bg-verde-claro text-verde-escuro' : 'bg-perigo-claro text-perigo-texto'">{{ v.tipo === 'receita' ? 'Receita' : 'Despesa' }}</span>
                            <span class="truncate font-bold">{{ v.descricao }}</span>
                        </div>
                        <span class="col-start-1 row-start-2 text-[13px] text-suave md:col-start-auto md:row-start-auto md:text-[15px] md:text-tinta">{{ v.categoria || '—' }}<span v-if="v.observacoes" class="md:hidden"> · {{ v.observacoes }}</span></span>
                        <span class="hidden text-sm text-suave md:block">{{ v.observacoes || '—' }}</span>
                        <span class="col-start-2 row-span-2 row-start-1 text-right font-extrabold md:col-start-auto md:row-span-1 md:row-start-auto" :class="v.tipo === 'receita' ? 'text-verde' : 'text-perigo'">{{ v.tipo === 'receita' ? '+' : '−' }} {{ euros(v.valor) }}</span>
                        <button type="button" class="col-start-3 row-span-2 row-start-1 grid h-11 w-11 place-items-center rounded-[10px] border border-linha-forte bg-white text-perigo hover:bg-perigo-claro md:col-start-auto md:row-span-1 md:row-start-auto" :aria-label="`Eliminar ${v.descricao}`" title="Eliminar" @click="eliminar(v.id)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                        </button>
                    </li>
                    <li v-if="!valores.length" class="px-4 py-10 text-center text-suave-2">Nenhum registo para este dia.</li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.cartao { @apply rounded-[14px] border border-linha bg-white; }
.btn-icone { @apply grid h-12 w-12 shrink-0 place-items-center rounded-[10px] border border-linha-forte bg-white text-tinta hover:bg-fundo; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta focus:border-verde focus:ring-verde; }
</style>
