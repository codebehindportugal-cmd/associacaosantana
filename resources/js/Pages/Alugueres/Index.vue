<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
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
// Semana começa à segunda-feira (como no calendário português)
const diasSemana = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
const mesesNomes = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];

const diasDoMes = computed(() => {
    const primeiro = new Date(props.ano, props.mes - 1, 1);
    const ultimo   = new Date(props.ano, props.mes, 0);
    const dias = [];
    // Preencher com nulls até ao primeiro dia da semana
    for (let i = 0; i < (primeiro.getDay() + 6) % 7; i++) dias.push(null);
    for (let d = 1; d <= ultimo.getDate(); d++) dias.push(d);
    while (dias.length % 7) dias.push(null);
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
    pendente:   'bg-laranja-claro text-laranja-texto border-laranja',
    confirmado: 'bg-verde-claro text-verde-escuro border-verde',
    cancelado:  'bg-perigo-claro text-perigo-texto border-perigo line-through',
    concluido:  'bg-fundo text-suave border-suave-2',
};
const estadoTexto = {
    pendente:   'text-laranja-texto',
    confirmado: 'text-verde-escuro',
    cancelado:  'text-perigo-texto',
    concluido:  'text-suave',
};
const estadoPill = {
    pendente:   'bg-laranja-claro text-laranja-texto',
    confirmado: 'bg-verde-claro text-verde-escuro',
    cancelado:  'bg-perigo-claro text-perigo-texto',
    concluido:  'bg-fundo text-suave',
};
const estadoBorda = {
    pendente:   'border-l-laranja',
    confirmado: 'border-l-verde',
    cancelado:  'border-l-perigo',
    concluido:  'border-l-suave-2',
};
const estadoPonto = {
    pendente:   'bg-laranja',
    confirmado: 'bg-verde',
    cancelado:  'bg-perigo',
    concluido:  'bg-suave-2',
};
const legenda = [
    ['Pendente', 'bg-laranja'],
    ['Confirmado', 'bg-verde'],
    ['Cancelado', 'bg-perigo'],
    ['Concluído', 'bg-suave-2'],
];
const euros = (v) => Number(v).toLocaleString('pt-PT', { useGrouping: 'always', style: 'currency', currency: 'EUR' });
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
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <!-- Cabeçalho -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-[30px] font-extrabold leading-tight">Alugueres do Salão</h1>
                    <p class="text-[15px] text-suave">Calendário de reservas do espaço. Toca num dia livre para criar um aluguer.</p>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <a :href="route('alugueres.opcoes')" class="btn-sec h-12">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h10M18 7h2M4 17h4M12 17h8" /><circle cx="16" cy="7" r="2" /><circle cx="10" cy="17" r="2" /></svg>
                        Opções do salão
                    </a>
                    <button type="button" class="btn-pri h-12" @click="abrirCriar()">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        Novo aluguer
                    </button>
                </div>
            </div>

            <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                <!-- Calendário -->
                <section class="overflow-hidden rounded-[14px] border border-linha bg-white">
                    <div class="flex items-center justify-between gap-2 border-b border-linha-fraca p-3 sm:p-4">
                        <button type="button" class="btn-icone" aria-label="Mês anterior" @click="navMes(-1)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                        </button>
                        <h2 class="text-xl font-extrabold">{{ mesesNomes[mes - 1] }} {{ ano }}</h2>
                        <button type="button" class="btn-icone" aria-label="Mês seguinte" @click="navMes(1)">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 border-b border-linha-fraca bg-fundo text-center text-[13px] font-bold text-suave">
                        <div v-for="d in diasSemana" :key="d" class="py-2">{{ d }}</div>
                    </div>

                    <div class="grid grid-cols-7">
                        <div
                            v-for="(dia, i) in diasDoMes"
                            :key="i"
                            class="min-h-[64px] min-w-0 border-b border-r border-linha-fraca p-1 sm:min-h-[92px] sm:p-1.5 [&:nth-child(7n)]:border-r-0"
                            :class="[
                                dia ? 'cursor-pointer hover:bg-fundo' : 'bg-fundo/60',
                                dia && diaExpandido === dia ? 'bg-verde-claro outline outline-2 -outline-offset-2 outline-verde' : '',
                            ]"
                            :role="dia ? 'button' : undefined"
                            :tabindex="dia ? 0 : undefined"
                            :aria-label="dia ? `${dia} de ${mesesNomes[mes - 1]}: ${alugueresNoDia(dia).length ? alugueresNoDia(dia).length + ' aluguer(es)' : 'livre, criar aluguer'}` : undefined"
                            @click="dia && (alugueresNoDia(dia).length ? toggleDia(dia) : abrirCriar(dia))"
                            @keydown.enter="dia && (alugueresNoDia(dia).length ? toggleDia(dia) : abrirCriar(dia))"
                        >
                            <template v-if="dia">
                                <div class="mb-1 flex items-center gap-1 px-0.5">
                                    <span
                                        class="flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold"
                                        :class="dateStr(dia) === hoje ? 'bg-tinta text-white' : 'text-tinta'"
                                    >{{ dia }}</span>
                                </div>
                                <div v-for="a in alugueresNoDia(dia)" :key="a.id" class="mb-0.5">
                                    <div class="hidden truncate rounded-md border-l-[3px] px-1.5 py-0.5 text-xs font-bold sm:block" :class="estadoCor[a.estado]">{{ a.nome_cliente }}</div>
                                    <div class="h-1.5 rounded-full sm:hidden" :class="estadoPonto[a.estado]" :title="a.nome_cliente"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 px-4 py-3 text-[13px] font-semibold text-suave">
                        <span v-for="[nome, cor] in legenda" :key="nome" class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm" :class="cor"></span>{{ nome }}</span>
                    </div>
                </section>

                <div class="flex flex-col gap-5">
                    <!-- Detalhe do dia expandido -->
                    <section v-if="diaExpandido" class="flex flex-col gap-3 rounded-[14px] border border-linha bg-white p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-lg font-extrabold">Dia {{ diaExpandido }} de {{ mesesNomes[mes - 1] }}</h3>
                            <div class="flex gap-1.5">
                                <button type="button" class="btn-sec h-11 px-3 text-sm text-verde" @click="abrirCriar(diaExpandido)">+ Neste dia</button>
                                <button type="button" class="btn-icone" aria-label="Fechar detalhe do dia" @click="diaExpandido = null">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                                </button>
                            </div>
                        </div>
                        <button
                            v-for="a in alugueresNoDia(diaExpandido)"
                            :key="a.id"
                            type="button"
                            class="flex flex-col gap-0.5 rounded-[10px] border border-l-4 border-linha p-3 text-left hover:bg-fundo"
                            :class="estadoBorda[a.estado]"
                            @click="abrirEditar(a)"
                        >
                            <div class="flex w-full items-start justify-between gap-2">
                                <span class="font-extrabold">{{ a.nome_cliente }}</span>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-extrabold" :class="estadoPill[a.estado]">{{ estadoLabel[a.estado] }}</span>
                            </div>
                            <span v-if="a.entidade" class="text-[13px] text-suave">{{ a.entidade }}</span>
                            <span class="text-[13px] font-semibold">{{ formatarData(a.data_inicio) }} → {{ formatarData(a.data_fim) }} · {{ a.numero_dias }} {{ a.numero_dias === 1 ? 'dia' : 'dias' }}</span>
                            <span v-if="a.opcoes.length" class="text-xs text-suave-2">{{ a.opcoes.map(o => o.nome).join(' · ') }}</span>
                        </button>
                    </section>

                    <!-- Próximos alugueres -->
                    <section v-if="proximos.length" class="overflow-hidden rounded-[14px] border border-linha bg-white">
                        <h3 class="border-b border-linha-fraca px-4 py-3.5 text-lg font-extrabold">Próximos alugueres</h3>
                        <ul class="divide-y divide-linha-fraca">
                            <li v-for="a in proximos" :key="a.id">
                                <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-fundo" @click="abrirEditar(a)">
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-bold">{{ a.nome_cliente }}<span v-if="a.entidade" class="font-normal text-suave-2"> · {{ a.entidade }}</span></div>
                                        <div class="text-[13px] text-suave">{{ formatarData(a.data_inicio) }} → {{ formatarData(a.data_fim) }} · {{ a.numero_dias }} {{ a.numero_dias === 1 ? 'dia' : 'dias' }}</div>
                                        <div class="text-xs font-bold" :class="estadoTexto[a.estado]">{{ estadoLabel[a.estado] }}</div>
                                        <div v-if="a.opcoes.length" class="mt-1 flex flex-wrap gap-1">
                                            <span v-for="o in a.opcoes" :key="o.id" class="rounded-md bg-fundo px-1.5 py-0.5 text-[11px] font-semibold text-suave">{{ o.nome }}</span>
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <template v-if="a.preco_total">
                                            <div class="font-extrabold">{{ euros(a.preco_total) }}</div>
                                            <div class="text-xs font-bold" :class="a.pago ? 'text-verde' : 'text-laranja-texto'">{{ a.pago ? 'Pago' : 'Por pagar' }}</div>
                                        </template>
                                        <span v-else class="text-suave-2">—</span>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </section>

                    <div v-else class="rounded-[14px] border border-linha bg-white p-8 text-center text-suave-2">
                        Sem alugueres futuros registados.
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal criar/editar -->
        <Teleport to="body">
            <div v-if="modal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-tinta/60 p-3 py-6 font-sans text-tinta sm:p-4 sm:py-10" @click.self="fecharModal">
                <div class="w-full max-w-2xl rounded-[14px] bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="t-modal-aluguer">
                    <div class="flex items-center justify-between border-b border-linha-fraca px-4 py-3 sm:px-6">
                        <h2 id="t-modal-aluguer" class="text-xl font-extrabold">{{ editing ? 'Editar aluguer' : 'Novo aluguer' }}</h2>
                        <button type="button" class="btn-icone" aria-label="Fechar" @click="fecharModal">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                        </button>
                    </div>

                    <form class="divide-y divide-linha-fraca" @submit.prevent="guardar">
                        <AvisoErros :errors="form.errors" :excluir="['caucao', 'caucao_devolvida', 'data_fim', 'data_inicio', 'email', 'entidade', 'estado', 'metodo_pagamento', 'nome_cliente', 'notas', 'pago', 'preco_total', 'telefone']" class="mx-4 mt-4 sm:mx-6" />
                        <fieldset class="grid gap-4 px-4 py-4 sm:grid-cols-2 sm:px-6">
                            <legend class="legenda">Cliente</legend>
                            <label class="rotulo sm:col-span-2">Nome do cliente *
                                <input v-model="form.nome_cliente" type="text" class="campo" required />
                                <span v-if="form.errors.nome_cliente" class="text-xs text-perigo">{{ form.errors.nome_cliente }}</span>
                            </label>
                            <label class="rotulo">Entidade / Organização<input :class="{ '!border-perigo': form.errors.entidade }" v-model="form.entidade" type="text" class="campo" /><span v-if="form.errors.entidade" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.entidade }}</span></label>
                            <label class="rotulo">Telefone<input :class="{ '!border-perigo': form.errors.telefone }" v-model="form.telefone" type="tel" class="campo" /><span v-if="form.errors.telefone" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.telefone }}</span></label>
                            <label class="rotulo sm:col-span-2">Email<input :class="{ '!border-perigo': form.errors.email }" v-model="form.email" type="email" class="campo" /><span v-if="form.errors.email" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.email }}</span></label>
                        </fieldset>

                        <fieldset class="grid gap-4 px-4 py-4 sm:grid-cols-3 sm:px-6">
                            <legend class="legenda">Datas e estado</legend>
                            <label class="rotulo">Data início *
                                <input v-model="form.data_inicio" type="date" class="campo" required />
                                <span v-if="form.errors.data_inicio" class="text-xs text-perigo">{{ form.errors.data_inicio }}</span>
                            </label>
                            <label class="rotulo">Data fim *
                                <input v-model="form.data_fim" type="date" class="campo" required :min="form.data_inicio" />
                                <span v-if="form.errors.data_fim" class="text-xs text-perigo">{{ form.errors.data_fim }}</span>
                            </label>
                            <label class="rotulo">Estado
                                <select :class="{ '!border-perigo': form.errors.estado }" v-model="form.estado" class="campo">
                                    <option value="pendente">Pendente</option>
                                    <option value="confirmado">Confirmado</option>
                                    <option value="cancelado">Cancelado</option>
                                    <option value="concluido">Concluído</option>
                                </select><span v-if="form.errors.estado" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.estado }}</span>
                            </label>
                        </fieldset>

                        <fieldset v-if="opcoes.length" class="px-4 py-4 sm:px-6">
                            <legend class="legenda">Opções incluídas</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="o in opcoes.filter(x => x.ativo)"
                                    :key="o.id"
                                    class="flex min-h-12 cursor-pointer items-start gap-3 rounded-[10px] border p-3 hover:bg-fundo"
                                    :class="form.opcoes.includes(o.id) ? 'border-verde bg-verde-claro' : 'border-linha-forte'"
                                >
                                    <input type="checkbox" :value="o.id" :checked="form.opcoes.includes(o.id)" class="chk mt-0.5" @change="toggleOpcao(o.id)" />
                                    <span>
                                        <span class="block text-[15px] font-semibold">{{ o.nome }}</span>
                                        <span v-if="o.descricao" class="block text-xs text-suave">{{ o.descricao }}</span>
                                        <span v-if="o.preco_extra > 0" class="mt-0.5 block text-xs font-bold text-verde">+{{ euros(o.preco_extra) }}</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="grid gap-4 px-4 py-4 sm:grid-cols-2 sm:px-6">
                            <legend class="legenda">Pagamento e caução</legend>
                            <label class="rotulo">Preço total (€)<input :class="{ '!border-perigo': form.errors.preco_total }" v-model="form.preco_total" type="number" step="0.01" min="0" class="campo" placeholder="0.00" /><span v-if="form.errors.preco_total" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.preco_total }}</span></label>
                            <label class="rotulo">Método de pagamento
                                <select :class="{ '!border-perigo': form.errors.metodo_pagamento }" v-model="form.metodo_pagamento" class="campo">
                                    <option value="">— Selecionar —</option>
                                    <option value="dinheiro">Dinheiro</option>
                                    <option value="transferencia">Transferência</option>
                                    <option value="mbway">MB Way</option>
                                    <option value="multibanco">Multibanco</option>
                                    <option value="cheque">Cheque</option>
                                </select><span v-if="form.errors.metodo_pagamento" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.metodo_pagamento }}</span>
                            </label>
                            <label class="caixa sm:col-span-2"><input :class="{ '!border-perigo': form.errors.pago }" v-model="form.pago" type="checkbox" class="chk" /><span v-if="form.errors.pago" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.pago }}</span>Pagamento recebido</label>
                            <label class="rotulo">Caução (€)<input :class="{ '!border-perigo': form.errors.caucao }" v-model="form.caucao" type="number" step="0.01" min="0" class="campo" placeholder="0.00" /><span v-if="form.errors.caucao" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.caucao }}</span></label>
                            <label class="caixa self-end"><input :class="{ '!border-perigo': form.errors.caucao_devolvida }" v-model="form.caucao_devolvida" type="checkbox" class="chk" /><span v-if="form.errors.caucao_devolvida" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.caucao_devolvida }}</span>Caução devolvida</label>
                        </fieldset>

                        <div class="px-4 py-4 sm:px-6">
                            <label class="rotulo">Notas / Observações
                                <textarea :class="{ '!border-perigo': form.errors.notas }" v-model="form.notas" rows="3" class="campo-area" placeholder="Informações adicionais..."></textarea><span v-if="form.errors.notas" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.notas }}</span>
                            </label>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-4 sm:px-6">
                            <button v-if="editing" type="button" class="btn-sec h-12 text-perigo hover:bg-perigo-claro" @click="eliminar">Eliminar</button>
                            <div v-else></div>
                            <div class="flex gap-2">
                                <button type="button" class="btn-sec h-12" @click="fecharModal">Cancelar</button>
                                <button type="submit" :disabled="form.processing" class="btn-pri h-12 px-6 disabled:opacity-60">{{ editing ? 'Guardar' : 'Criar' }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.btn-icone { @apply grid h-11 w-11 shrink-0 place-items-center rounded-[10px] border border-linha-forte bg-white text-tinta hover:bg-fundo; }
.legenda { @apply col-span-full float-left mb-1 w-full text-[13px] font-extrabold uppercase tracking-[0.06em] text-suave-2; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base text-tinta focus:border-verde focus:ring-verde; }
.campo-area { @apply w-full rounded-[10px] border border-linha-forte bg-white px-3.5 py-3 text-base text-tinta focus:border-verde focus:ring-verde; }
.caixa { @apply flex h-12 cursor-pointer items-center gap-3 rounded-[10px] border border-linha-forte bg-white px-3.5 text-[15px] font-bold text-tinta; }
.chk { @apply h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde; }
</style>
