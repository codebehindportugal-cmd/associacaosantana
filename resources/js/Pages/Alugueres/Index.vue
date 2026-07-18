<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    alugueres: Array,
    proximos:  Array,
    opcoes:    Array,
    ano:       Number,
    mes:       Number,
});

// ── Calendário ────────────────────────────────────────────────────────────────
const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const mesesNomes = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

const diasDoMes = computed(() => {
    const primeiro = new Date(props.ano, props.mes - 1, 1);
    const ultimo   = new Date(props.ano, props.mes, 0);
    const dias = [];
    // Preencher com nulls até ao primeiro dia da semana
    for (let i = 0; i < primeiro.getDay(); i++) dias.push(null);
    for (let d = 1; d <= ultimo.getDate(); d++) dias.push(d);
    return dias;
});

function dateStr(dia) {
    return `${props.ano}-${String(props.mes).padStart(2,'0')}-${String(dia).padStart(2,'0')}`;
}

function alugueresNoDia(dia) {
    if (!dia) return [];
    const d = dateStr(dia);
    return props.alugueres.filter(a => a.data_inicio <= d && a.data_fim >= d);
}

function navMes(delta) {
    let m = props.mes + delta;
    let a = props.ano;
    if (m < 1)  { m = 12; a--; }
    if (m > 12) { m = 1;  a++; }
    router.get(route('alugueres.index'), { ano: a, mes: m }, { preserveScroll: true });
}

// ── Modal criar/editar ────────────────────────────────────────────────────────
const modal   = ref(false);
const editing = ref(null); // null = criar novo

const form = useForm({
    nome_cliente:     '',
    entidade:         '',
    telefone:         '',
    email:            '',
    data_inicio:      '',
    data_fim:         '',
    notas:            '',
    estado:           'pendente',
    caucao:           '',
    caucao_devolvida: false,
    preco_total:      '',
    pago:             false,
    metodo_pagamento: '',
    opcoes:           [],
});

function abrirCriar(diaClicado = null) {
    editing.value = null;
    form.reset();
    form.estado = 'pendente';
    form.opcoes = [];
    if (diaClicado) {
        const d = dateStr(diaClicado);
        form.data_inicio = d;
        form.data_fim    = d;
    }
    modal.value = true;
}

function abrirEditar(a) {
    editing.value = a.id;
    form.nome_cliente     = a.nome_cliente;
    form.entidade         = a.entidade ?? '';
    form.telefone         = a.telefone ?? '';
    form.email            = a.email ?? '';
    form.data_inicio      = a.data_inicio;
    form.data_fim         = a.data_fim;
    form.notas            = a.notas ?? '';
    form.estado           = a.estado;
    form.caucao           = a.caucao ?? '';
    form.caucao_devolvida = a.caucao_devolvida;
    form.preco_total      = a.preco_total ?? '';
    form.pago             = a.pago;
    form.metodo_pagamento = a.metodo_pagamento ?? '';
    form.opcoes           = [...(a.opcoes_ids ?? [])];
    form.clearErrors();
    modal.value = true;
}

function fecharModal() {
    modal.value = false;
    editing.value = null;
}

function guardar() {
    if (editing.value) {
        form.patch(route('alugueres.update', editing.value), {
            onSuccess: fecharModal,
        });
    } else {
        form.post(route('alugueres.store'), {
            onSuccess: fecharModal,
        });
    }
}

function eliminar() {
    if (!editing.value) return;
    if (!confirm('Eliminar este aluguer?')) return;
    router.delete(route('alugueres.destroy', editing.value), {
        onSuccess: fecharModal,
    });
}

function toggleOpcao(id) {
    const idx = form.opcoes.indexOf(id);
    if (idx === -1) form.opcoes.push(id);
    else form.opcoes.splice(idx, 1);
}

// ── Detalhe do dia (expandir) ─────────────────────────────────────────────────
const diaExpandido = ref(null);
function toggleDia(dia) {
    diaExpandido.value = diaExpandido.value === dia ? null : dia;
}

// ── Cores por estado ──────────────────────────────────────────────────────────
const estadoCor = {
    pendente:   'bg-amber-100 text-amber-800 border-amber-300',
    confirmado: 'bg-emerald-100 text-emerald-800 border-emerald-300',
    cancelado:  'bg-red-100 text-red-700 border-red-300',
    concluido:  'bg-slate-100 text-slate-600 border-slate-300',
};
const estadoLabel = {
    pendente:   'Pendente',
    confirmado: 'Confirmado',
    cancelado:  'Cancelado',
    concluido:  'Concluído',
};

function formatarData(d) {
    if (!d) return '';
    const [a, m, dia] = d.split('-');
    return `${dia}/${m}/${a}`;
}

const hoje = new Date().toISOString().slice(0, 10);
</script>

<template>
    <AppLayout>
        <!-- Cabeçalho -->
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Alugueres do Salão</h1>
                <p class="mt-1 text-sm text-slate-500">Calendário de reservas do espaço.</p>
            </div>
            <div class="flex gap-2">
                <a :href="route('alugueres.opcoes')" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50">
                    ⚙️ Opções
                </a>
                <button @click="abrirCriar()" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white">
                    + Novo Aluguer
                </button>
            </div>
        </div>

        <!-- Calendário -->
        <div class="mb-6 rounded-xl bg-white shadow-sm">
            <!-- Nav mês -->
            <div class="flex items-center justify-between border-b px-5 py-3">
                <button @click="navMes(-1)" class="rounded-md p-2 hover:bg-slate-100">‹</button>
                <h2 class="text-lg font-black">{{ mesesNomes[mes - 1] }} {{ ano }}</h2>
                <button @click="navMes(1)" class="rounded-md p-2 hover:bg-slate-100">›</button>
            </div>

            <!-- Dias da semana -->
            <div class="grid grid-cols-7 border-b bg-slate-50 text-center text-xs font-bold text-slate-500">
                <div v-for="d in diasSemana" :key="d" class="py-2">{{ d }}</div>
            </div>

            <!-- Grid de dias -->
            <div class="grid grid-cols-7">
                <div
                    v-for="(dia, i) in diasDoMes"
                    :key="i"
                    class="min-h-[80px] border-b border-r p-1 last:border-r-0"
                    :class="dia ? 'cursor-pointer hover:bg-slate-50' : 'bg-slate-50/50'"
                    @click="dia && (alugueresNoDia(dia).length ? toggleDia(dia) : abrirCriar(dia))"
                >
                    <template v-if="dia">
                        <div class="mb-1 flex items-center gap-1">
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold"
                                :class="dateStr(dia) === hoje ? 'bg-slate-900 text-white' : 'text-slate-700'"
                            >{{ dia }}</span>
                            <span v-if="alugueresNoDia(dia).length" class="text-[10px] font-bold text-slate-400">
                                {{ alugueresNoDia(dia).length }}x
                            </span>
                        </div>
                        <div v-for="a in alugueresNoDia(dia)" :key="a.id" class="mb-0.5">
                            <div
                                class="truncate rounded px-1 py-0.5 text-[11px] font-bold border"
                                :class="estadoCor[a.estado]"
                            >{{ a.nome_cliente }}</div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Detalhe do dia expandido -->
        <div v-if="diaExpandido" class="mb-6 rounded-xl border-2 border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-black text-slate-800">Dia {{ diaExpandido }} de {{ mesesNomes[mes - 1] }}</h3>
                <button @click="diaExpandido = null" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="a in alugueresNoDia(diaExpandido)"
                    :key="a.id"
                    class="cursor-pointer rounded-lg border p-3 hover:bg-slate-50"
                    :class="estadoCor[a.estado]"
                    @click="abrirEditar(a)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-black">{{ a.nome_cliente }}</div>
                            <div v-if="a.entidade" class="text-xs">{{ a.entidade }}</div>
                        </div>
                        <span class="rounded-full border px-2 py-0.5 text-xs font-bold" :class="estadoCor[a.estado]">
                            {{ estadoLabel[a.estado] }}
                        </span>
                    </div>
                    <div class="mt-1 text-xs">
                        {{ formatarData(a.data_inicio) }} → {{ formatarData(a.data_fim) }} · {{ a.numero_dias }} {{ a.numero_dias === 1 ? 'dia' : 'dias' }}
                    </div>
                    <div v-if="a.opcoes.length" class="mt-1 flex flex-wrap gap-1">
                        <span v-for="o in a.opcoes" :key="o.id" class="rounded bg-slate-800/10 px-1.5 py-0.5 text-[10px] font-semibold">{{ o.nome }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Próximos alugueres -->
        <div v-if="proximos.length" class="rounded-xl bg-white shadow-sm">
            <div class="border-b px-5 py-3">
                <h3 class="font-black text-slate-800">Próximos alugueres</h3>
            </div>
            <div class="divide-y">
                <div
                    v-for="a in proximos"
                    :key="a.id"
                    class="flex cursor-pointer flex-wrap items-center gap-3 px-5 py-3 hover:bg-slate-50"
                    @click="abrirEditar(a)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="font-bold">{{ a.nome_cliente }}<span v-if="a.entidade" class="ml-2 font-normal text-slate-500">· {{ a.entidade }}</span></div>
                        <div class="text-sm text-slate-500">
                            {{ formatarData(a.data_inicio) }} → {{ formatarData(a.data_fim) }} · {{ a.numero_dias }} {{ a.numero_dias === 1 ? 'dia' : 'dias' }}
                        </div>
                        <div v-if="a.opcoes.length" class="mt-1 flex flex-wrap gap-1">
                            <span v-for="o in a.opcoes" :key="o.id" class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ o.nome }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div v-if="a.preco_total" class="text-right">
                            <div class="text-sm font-black">{{ Number(a.preco_total).toFixed(2) }}€</div>
                            <div class="text-xs" :class="a.pago ? 'text-emerald-600 font-bold' : 'text-amber-600'">{{ a.pago ? 'Pago' : 'Por pagar' }}</div>
                        </div>
                        <span class="rounded-full border px-2 py-1 text-xs font-bold" :class="estadoCor[a.estado]">{{ estadoLabel[a.estado] }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="rounded-xl bg-white p-10 text-center text-slate-400 shadow-sm">
            Sem alugueres futuros registados.
        </div>

        <!-- Modal criar/editar ───────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="modal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 p-4 py-10">
                <div class="w-full max-w-2xl rounded-xl bg-white shadow-2xl">
                    <!-- Header modal -->
                    <div class="flex items-center justify-between border-b px-6 py-4">
                        <h2 class="text-lg font-black text-slate-900">{{ editing ? 'Editar Aluguer' : 'Novo Aluguer' }}</h2>
                        <button @click="fecharModal" class="text-slate-400 hover:text-slate-600">✕</button>
                    </div>

                    <form @submit.prevent="guardar" class="divide-y">
                        <!-- Dados do cliente -->
                        <div class="grid gap-4 px-6 py-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-bold text-slate-700">Nome do cliente *</label>
                                <input v-model="form.nome_cliente" type="text" class="w-full rounded-md border-slate-300 text-sm text-slate-900" required />
                                <p v-if="form.errors.nome_cliente" class="mt-1 text-xs text-red-600">{{ form.errors.nome_cliente }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-bold text-slate-700">Entidade / Organização</label>
                                <input v-model="form.entidade" type="text" class="w-full rounded-md border-slate-300 text-sm text-slate-900" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-bold text-slate-700">Telefone</label>
                                <input v-model="form.telefone" type="tel" class="w-full rounded-md border-slate-300 text-sm text-slate-900" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-bold text-slate-700">Email</label>
                                <input v-model="form.email" type="email" class="w-full rounded-md border-slate-300 text-sm text-slate-900" />
                            </div>
                        </div>

                        <!-- Datas e estado -->
                        <div class="grid gap-4 px-6 py-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-bold text-slate-700">Data início *</label>
                                <input v-model="form.data_inicio" type="date" class="w-full rounded-md border-slate-300 text-sm text-slate-900" required />
                                <p v-if="form.errors.data_inicio" class="mt-1 text-xs text-red-600">{{ form.errors.data_inicio }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-bold text-slate-700">Data fim *</label>
                                <input v-model="form.data_fim" type="date" class="w-full rounded-md border-slate-300 text-sm text-slate-900" required :min="form.data_inicio" />
                                <p v-if="form.errors.data_fim" class="mt-1 text-xs text-red-600">{{ form.errors.data_fim }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-bold text-slate-700">Estado</label>
                                <select v-model="form.estado" class="w-full rounded-md border-slate-300 text-sm text-slate-900">
                                    <option value="pendente">Pendente</option>
                                    <option value="confirmado">Confirmado</option>
                                    <option value="cancelado">Cancelado</option>
                                    <option value="concluido">Concluído</option>
                                </select>
                            </div>
                        </div>

                        <!-- Opções do salão -->
                        <div v-if="opcoes.length" class="px-6 py-4">
                            <label class="mb-2 block text-sm font-bold text-slate-700">Opções incluídas</label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="o in opcoes.filter(x => x.ativo)"
                                    :key="o.id"
                                    class="flex cursor-pointer items-start gap-2 rounded-lg border p-2.5 hover:bg-slate-50"
                                    :class="form.opcoes.includes(o.id) ? 'border-slate-900 bg-slate-50' : 'border-slate-200'"
                                >
                                    <input
                                        type="checkbox"
                                        :value="o.id"
                                        :checked="form.opcoes.includes(o.id)"
                                        @change="toggleOpcao(o.id)"
                                        class="mt-0.5 rounded"
                                    />
                                    <div>
                                        <div class="text-sm font-semibold text-slate-900">{{ o.nome }}</div>
                                        <div v-if="o.descricao" class="text-xs text-slate-500">{{ o.descricao }}</div>
                                        <div v-if="o.preco_extra > 0" class="mt-0.5 text-xs font-bold text-emerald-700">+{{ Number(o.preco_extra).toFixed(2) }}€</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Financeiro -->
                        <div class="grid gap-4 px-6 py-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-bold text-slate-700">Preço total (€)</label>
                                <input v-model="form.preco_total" type="number" step="0.01" min="0" class="w-full rounded-md border-slate-300 text-sm text-slate-900" placeholder="0.00" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-bold text-slate-700">Método de pagamento</label>
                                <select v-model="form.metodo_pagamento" class="w-full rounded-md border-slate-300 text-sm text-slate-900">
                                    <option value="">— Selecionar —</option>
                                    <option value="dinheiro">Dinheiro</option>
                                    <option value="transferencia">Transferência</option>
                                    <option value="mbway">MB Way</option>
                                    <option value="multibanco">Multibanco</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>
                            <div class="flex items-center gap-2">
                                <input v-model="form.pago" type="checkbox" id="pago" class="rounded" />
                                <label for="pago" class="text-sm font-semibold text-slate-800 cursor-pointer">Pagamento recebido</label>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-bold text-slate-700">Caução (€)</label>
                                <input v-model="form.caucao" type="number" step="0.01" min="0" class="w-full rounded-md border-slate-300 text-sm text-slate-900" placeholder="0.00" />
                            </div>
                            <div class="flex items-center gap-2">
                                <input v-model="form.caucao_devolvida" type="checkbox" id="caucao_dev" class="rounded" />
                                <label for="caucao_dev" class="text-sm font-semibold text-slate-800 cursor-pointer">Caução devolvida</label>
                            </div>
                        </div>

                        <!-- Notas -->
                        <div class="px-6 py-4">
                            <label class="mb-1 block text-sm font-bold text-slate-700">Notas / Observações</label>
                            <textarea v-model="form.notas" rows="3" class="w-full rounded-md border-slate-300 text-sm text-slate-900" placeholder="Informações adicionais..."></textarea>
                        </div>

                        <!-- Botões -->
                        <div class="flex items-center justify-between px-6 py-4">
                            <button
                                v-if="editing"
                                type="button"
                                @click="eliminar"
                                class="rounded-md border border-red-300 px-3 py-2 text-sm font-bold text-red-600 hover:bg-red-50"
                            >
                                Eliminar
                            </button>
                            <div v-else></div>
                            <div class="flex gap-2">
                                <button type="button" @click="fecharModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="rounded-md bg-slate-900 px-5 py-2 text-sm font-bold text-white disabled:opacity-60">
                                    {{ editing ? 'Guardar' : 'Criar' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
