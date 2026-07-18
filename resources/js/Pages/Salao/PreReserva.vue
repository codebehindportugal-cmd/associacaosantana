<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PublicShell from '@/Components/PublicShell.vue';
import { computed } from 'vue';

const props = defineProps({
    opcoes:   Array,
    ocupadas: Array, // [{inicio, fim}, ...]
});

const page = usePage();
const enviado  = computed(() => !!page.props.flash?.success);
const msgSucc  = computed(() => page.props.flash?.success ?? '');

const form = useForm({
    nome_cliente: '',
    telefone:     '',
    email:        '',
    data_inicio:  '',
    data_fim:     '',
    notas:        '',
    opcoes:       [],
});

// Detectar conflito cliente-side
const temConflito = computed(() => {
    if (!form.data_inicio || !form.data_fim) return false;
    return props.ocupadas.some(
        (o) => form.data_fim >= o.inicio && form.data_inicio <= o.fim,
    );
});

// Número de dias selecionados
const numeroDias = computed(() => {
    if (!form.data_inicio || !form.data_fim) return null;
    const d = Math.round((new Date(form.data_fim) - new Date(form.data_inicio)) / 86400000) + 1;
    return d > 0 ? d : null;
});

const hoje = new Date().toISOString().slice(0, 10);

function toggleOpcao(id) {
    const i = form.opcoes.indexOf(id);
    if (i === -1) form.opcoes.push(id);
    else form.opcoes.splice(i, 1);
}

function submeter() {
    form.post(route('salao.pre-reserva.store'));
}

// ── Calendário mini (3 meses) ────────────────────────────────────────────────
const mesesNomes = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

function mesesExibir() {
    const agora = new Date();
    const meses = [];
    for (let i = 0; i < 3; i++) {
        const d = new Date(agora.getFullYear(), agora.getMonth() + i, 1);
        meses.push({ ano: d.getFullYear(), mes: d.getMonth() }); // mes 0-indexed
    }
    return meses;
}

function diasDoMes(ano, mes) {
    const primeiro = new Date(ano, mes, 1);
    const ultimo   = new Date(ano, mes + 1, 0);
    const dias = [];
    for (let i = 0; i < primeiro.getDay(); i++) dias.push(null);
    for (let d = 1; d <= ultimo.getDate(); d++) dias.push(d);
    return dias;
}

function toDateStr(ano, mes, dia) {
    return `${ano}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
}

function isDiaBusy(dateStr) {
    return props.ocupadas.some((o) => dateStr >= o.inicio && dateStr <= o.fim);
}

function isPast(dateStr) {
    return dateStr < hoje;
}

function clicarDia(dateStr) {
    if (isPast(dateStr) || isDiaBusy(dateStr)) return;
    if (!form.data_inicio || (form.data_inicio && form.data_fim)) {
        form.data_inicio = dateStr;
        form.data_fim    = dateStr;
    } else if (dateStr < form.data_inicio) {
        form.data_inicio = dateStr;
    } else {
        form.data_fim = dateStr;
    }
}

function estaNoIntervalo(dateStr) {
    if (!form.data_inicio || !form.data_fim) return false;
    return dateStr >= form.data_inicio && dateStr <= form.data_fim;
}
</script>

<template>
    <PublicShell>
        <Head title="Reservar o Salão — ARDC Santana" />

        <!-- Hero -->
        <section class="bg-gradient-to-b from-amber-600 to-amber-700 py-14 text-center text-white">
            <div class="mx-auto max-w-2xl px-5">
                <div class="mb-3 text-4xl">🏛️</div>
                <h1 class="font-display text-3xl font-bold sm:text-4xl">Reservar o Salão</h1>
                <p class="mt-3 text-base text-amber-100">
                    Preenche o formulário para solicitar uma pré-reserva do espaço da Associação de Santana.
                    Entraremos em contacto para confirmar a disponibilidade e acertar os detalhes.
                </p>
            </div>
        </section>

        <main class="mx-auto max-w-4xl px-5 py-10 lg:px-8">

            <!-- Sucesso -->
            <div v-if="enviado" class="mb-8 rounded-xl border-2 border-emerald-300 bg-emerald-50 p-6 text-center">
                <div class="mb-2 text-4xl">✅</div>
                <p class="text-lg font-black text-emerald-800">{{ msgSucc }}</p>
                <p class="mt-1 text-sm text-emerald-700">Guarda este número de contacto caso precises: <strong>ardcsantana@outlook.com</strong></p>
            </div>

            <div class="grid gap-8 lg:grid-cols-[1fr_380px]">

                <!-- Formulário -->
                <div class="order-2 lg:order-1">
                    <form @submit.prevent="submeter" class="space-y-6">

                        <!-- Dados de contacto -->
                        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
                            <h2 class="mb-4 font-black text-stone-800">Os seus dados</h2>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-sm font-bold text-stone-700">Nome completo <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.nome_cliente"
                                        type="text"
                                        class="w-full rounded-lg border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                        placeholder="Ex: João Silva"
                                        required
                                    />
                                    <p v-if="form.errors.nome_cliente" class="mt-1 text-xs text-red-600">{{ form.errors.nome_cliente }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-bold text-stone-700">Telefone <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.telefone"
                                        type="tel"
                                        class="w-full rounded-lg border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                        placeholder="912 345 678"
                                        required
                                    />
                                    <p v-if="form.errors.telefone" class="mt-1 text-xs text-red-600">{{ form.errors.telefone }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-bold text-stone-700">Email</label>
                                    <input
                                        v-model="form.email"
                                        type="email"
                                        class="w-full rounded-lg border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                        placeholder="email@exemplo.com"
                                    />
                                    <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Datas -->
                        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
                            <h2 class="mb-4 font-black text-stone-800">Datas pretendidas</h2>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-bold text-stone-700">Data de início <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.data_inicio"
                                        type="date"
                                        :min="hoje"
                                        class="w-full rounded-lg border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                        required
                                    />
                                    <p v-if="form.errors.data_inicio" class="mt-1 text-xs text-red-600">{{ form.errors.data_inicio }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-bold text-stone-700">Data de fim <span class="text-red-500">*</span></label>
                                    <input
                                        v-model="form.data_fim"
                                        type="date"
                                        :min="form.data_inicio || hoje"
                                        class="w-full rounded-lg border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                        required
                                    />
                                    <p v-if="form.errors.data_fim" class="mt-1 text-xs text-red-600">{{ form.errors.data_fim }}</p>
                                </div>
                            </div>

                            <!-- Resumo de dias -->
                            <div v-if="numeroDias" class="mt-3 rounded-lg bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-900">
                                🗓️ {{ numeroDias }} {{ numeroDias === 1 ? 'dia selecionado' : 'dias selecionados' }}
                            </div>

                            <!-- Aviso de conflito -->
                            <div v-if="temConflito" class="mt-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3">
                                <p class="text-sm font-bold text-red-700">⚠️ Estas datas já estão reservadas ou têm uma pré-reserva pendente.</p>
                                <p class="mt-0.5 text-xs text-red-600">Consulte o calendário ao lado e escolha outras datas.</p>
                            </div>
                        </div>

                        <!-- Opções -->
                        <div v-if="opcoes && opcoes.length" class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
                            <h2 class="mb-1 font-black text-stone-800">Opções pretendidas</h2>
                            <p class="mb-4 text-xs text-stone-500">Selecione o que necessita. A associação confirmará a disponibilidade de cada item.</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="o in opcoes"
                                    :key="o.id"
                                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition hover:bg-amber-50"
                                    :class="form.opcoes.includes(o.id)
                                        ? 'border-amber-500 bg-amber-50 ring-1 ring-amber-400'
                                        : 'border-stone-200 bg-white'"
                                >
                                    <input
                                        type="checkbox"
                                        :value="o.id"
                                        :checked="form.opcoes.includes(o.id)"
                                        @change="toggleOpcao(o.id)"
                                        class="mt-0.5 rounded accent-amber-600"
                                    />
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-stone-800">{{ o.nome }}</div>
                                        <div v-if="o.descricao" class="mt-0.5 text-xs text-stone-500">{{ o.descricao }}</div>
                                        <div v-if="o.preco_extra > 0" class="mt-0.5 text-xs font-bold text-amber-700">+{{ Number(o.preco_extra).toFixed(2) }}€</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Notas -->
                        <div class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
                            <h2 class="mb-3 font-black text-stone-800">Informações adicionais</h2>
                            <textarea
                                v-model="form.notas"
                                rows="4"
                                class="w-full rounded-lg border-stone-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                                placeholder="Ex: Número de pessoas previsto, tipo de evento, horário aproximado, necessidades especiais..."
                            ></textarea>
                        </div>

                        <!-- Botão enviar -->
                        <button
                            type="submit"
                            :disabled="form.processing || temConflito"
                            class="w-full rounded-xl bg-amber-600 py-3.5 text-base font-black text-white shadow transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <span v-if="form.processing">A enviar...</span>
                            <span v-else-if="temConflito">Datas indisponíveis — escolha outras datas</span>
                            <span v-else>Enviar pré-reserva</span>
                        </button>

                        <p class="text-center text-xs text-stone-400">
                            A pré-reserva não é vinculativa. Entraremos em contacto para confirmar e acertar os detalhes.
                        </p>
                    </form>
                </div>

                <!-- Calendário de disponibilidade -->
                <div class="order-1 lg:order-2">
                    <div class="sticky top-24 rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
                        <h2 class="mb-1 font-black text-stone-800">Disponibilidade</h2>
                        <p class="mb-4 text-xs text-stone-500">Dias a vermelho já estão ocupados.</p>

                        <div class="space-y-5">
                            <div v-for="({ ano, mes }) in mesesExibir()" :key="`${ano}-${mes}`">
                                <div class="mb-2 text-center text-xs font-black uppercase tracking-wide text-stone-600">
                                    {{ mesesNomes[mes] }} {{ ano }}
                                </div>
                                <!-- Cabeçalho dias da semana -->
                                <div class="grid grid-cols-7 text-center">
                                    <div v-for="d in ['D','S','T','Q','Q','S','S']" :key="d" class="py-0.5 text-[10px] font-bold text-stone-400">{{ d }}</div>
                                </div>
                                <!-- Dias -->
                                <div class="grid grid-cols-7 text-center">
                                    <div
                                        v-for="(dia, i) in diasDoMes(ano, mes)"
                                        :key="i"
                                        class="aspect-square flex items-center justify-center rounded-full text-xs"
                                        :class="dia ? [
                                            isPast(toDateStr(ano, mes, dia)) ? 'text-stone-300 cursor-default'
                                            : isDiaBusy(toDateStr(ano, mes, dia)) ? 'bg-red-100 text-red-600 font-bold cursor-not-allowed'
                                            : estaNoIntervalo(toDateStr(ano, mes, dia)) ? 'bg-amber-500 text-white font-black cursor-pointer'
                                            : 'text-stone-700 hover:bg-amber-50 cursor-pointer font-medium'
                                        ] : ''"
                                        @click="dia && clicarDia(toDateStr(ano, mes, dia))"
                                    >
                                        {{ dia }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Legenda -->
                        <div class="mt-4 flex flex-wrap gap-3 text-xs text-stone-500">
                            <div class="flex items-center gap-1.5">
                                <div class="h-3 w-3 rounded-full bg-amber-500"></div>
                                <span>Selecionado</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="h-3 w-3 rounded-full bg-red-100 border border-red-300"></div>
                                <span>Ocupado</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <div class="h-3 w-3 rounded-full bg-white border border-stone-200"></div>
                                <span>Disponível</span>
                            </div>
                        </div>
                        <p class="mt-3 text-[11px] text-stone-400">💡 Clica num dia disponível para preencher as datas automaticamente.</p>
                    </div>
                </div>

            </div>
        </main>
    </PublicShell>
</template>
