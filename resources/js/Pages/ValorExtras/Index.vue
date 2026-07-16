<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

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

watch(() => form.tipo, (val) => {
    categorias.value = val === 'receita' ? CATEGORIAS_RECEITA : CATEGORIAS_DESPESA
    form.categoria = ''
})
</script>

<template>
    <AppLayout title="Valores Extras">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Valores Extras</h2>
        </template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">

            <!-- Filtro data + resumo -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-semibold text-slate-600">Data:</label>
                    <input v-model="dataEscolhida" type="date" class="rounded-md border-slate-300 text-sm">
                </div>
                <div class="flex gap-4 text-sm font-semibold">
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800">Receitas: {{ euros(resumo.total_receitas) }}</span>
                    <span class="rounded-full bg-red-100 px-3 py-1 text-red-700">Despesas: {{ euros(resumo.total_despesas) }}</span>
                    <span class="rounded-full px-3 py-1 font-black" :class="resumo.saldo >= 0 ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800'">
                        Saldo: {{ euros(resumo.saldo) }}
                    </span>
                </div>
            </div>

            <!-- Formulario novo registo -->
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <h3 class="mb-4 text-base font-semibold text-slate-800">Novo Registo</h3>
                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submeter">
                    <div class="grid gap-3 md:col-span-2 md:grid-cols-[120px_1fr_140px_120px]">
                        <select v-model="form.tipo" class="rounded-md border-slate-300 text-sm font-semibold">
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                        </select>
                        <input
                            v-model="form.descricao"
                            type="text"
                            placeholder="Descricao (ex: Patrocinador XYZ, Banda)"
                            class="rounded-md border-slate-300 text-sm"
                            required
                        >
                        <select v-model="form.categoria" class="rounded-md border-slate-300 text-sm">
                            <option value="">Categoria</option>
                            <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                        <input
                            v-model="form.valor"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="Valor (EUR)"
                            class="rounded-md border-slate-300 text-sm"
                            required
                        >
                    </div>
                    <div class="md:col-span-2 grid gap-3 md:grid-cols-[1fr_auto]">
                        <input
                            v-model="form.observacoes"
                            type="text"
                            placeholder="Observacoes (opcional)"
                            class="rounded-md border-slate-300 text-sm"
                        >
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md px-4 py-2 text-sm font-semibold text-white disabled:opacity-60"
                            :class="form.tipo === 'receita' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-red-600 hover:bg-red-700'"
                        >
                            {{ form.processing ? 'A guardar...' : '+ Adicionar' }}
                        </button>
                    </div>
                    <div v-if="Object.keys(form.errors).length" class="md:col-span-2 rounded-md bg-red-50 p-3 text-sm text-red-700">
                        <div v-for="erro in form.errors" :key="erro">{{ erro }}</div>
                    </div>
                </form>
            </div>

            <!-- Lista de registos -->
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-900">Tipo</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-900">Descricao</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-900">Categoria</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-900">Valor</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-900">Observacoes</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-900"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="v in valores" :key="v.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="v.tipo === 'receita' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700'">
                                    {{ v.tipo === 'receita' ? 'Receita' : 'Despesa' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ v.descricao }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ v.categoria || '\u2014' }}</td>
                            <td class="px-4 py-3 text-right font-semibold" :class="v.tipo === 'receita' ? 'text-emerald-700' : 'text-red-600'">
                                {{ euros(v.valor) }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ v.observacoes || '\u2014' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button @click="eliminar(v.id)" class="text-xs text-red-600 hover:underline">Eliminar</button>
                            </td>
                        </tr>
                        <tr v-if="!valores.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">Nenhum registo para este dia.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
