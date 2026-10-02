<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import PublicShell from '@/Components/PublicShell.vue';
import { useRecaptcha } from '@/Composables/useRecaptcha';
import { computed } from 'vue';

const props = defineProps({
    opcoes:   Array,
    ocupadas: Array, // [{inicio, fim}, ...]
});

const page = usePage();
const enviado  = computed(() => !!page.props.flash?.success);
const msgSucc  = computed(() => page.props.flash?.success ?? '');

const { obterToken } = useRecaptcha();

const form = useForm({
    nome_cliente:     '',
    telefone:         '',
    email:            '',
    data_inicio:      '',
    data_fim:         '',
    notas:            '',
    opcoes:           [],
    recaptcha_token:  '',
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

async function submeter() {
    form.recaptcha_token = await obterToken('reserva_salao');
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
        <section class="bg-verde py-12 text-center text-white sm:py-16">
            <div class="mx-auto w-full max-w-2xl px-4 sm:px-6">
                <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-verde-escuro">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10l9-6 9 6" /><path d="M5 10v10h14V10" /><path d="M9 20v-6h6v6" /><path d="M3 20h18" /></svg>
                </span>
                <h1 class="m-0 text-[clamp(32px,4.4vw,48px)] font-extrabold leading-[1.1] tracking-[-0.01em]">Reservar o Salão</h1>
                <p class="m-0 mt-4 text-[17px] text-verde-claro2">
                    Preenche o formulário para solicitar uma pré-reserva do espaço da Associação de Santana.
                    Entraremos em contacto para confirmar a disponibilidade e acertar os detalhes.
                </p>
            </div>
        </section>

        <main class="mx-auto w-full max-w-[1120px] px-4 py-8 sm:px-6 sm:py-10">

            <!-- Sucesso -->
            <div v-if="enviado" class="mb-8 flex flex-col items-center rounded-[14px] border-2 border-verde bg-verde-claro p-6 text-center" role="status">
                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-verde text-white">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L19 7" /></svg>
                </span>
                <p class="m-0 mt-3 text-lg font-extrabold text-verde-escuro">{{ msgSucc }}</p>
                <p class="m-0 mt-1 text-[15px] text-verde-escuro">Guarda este número de contacto caso precises: <strong>ardcsantana@outlook.com</strong></p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px] lg:gap-8">

                <!-- Formulário -->
                <div class="order-2 min-w-0 lg:order-1">
                    <form class="space-y-5" @submit.prevent="submeter">

                        <!-- Dados de contacto -->
                        <div class="rounded-[14px] border border-linha bg-white p-5 sm:p-6">
                            <h2 class="m-0 mb-4 flex items-center gap-2.5 text-[21px] font-extrabold"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-escuro text-[13px] font-extrabold text-white">1</span>Os seus dados</h2>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="rotulo" for="pr-nome">Nome completo <span class="text-perigo">*</span></label>
                                    <input id="pr-nome" v-model="form.nome_cliente" type="text" class="campo" placeholder="Ex: João Silva" required />
                                    <p v-if="form.errors.nome_cliente" class="erro">{{ form.errors.nome_cliente }}</p>
                                </div>
                                <div>
                                    <label class="rotulo" for="pr-tel">Telefone <span class="text-perigo">*</span></label>
                                    <input id="pr-tel" v-model="form.telefone" type="tel" class="campo" placeholder="912 345 678" required />
                                    <p v-if="form.errors.telefone" class="erro">{{ form.errors.telefone }}</p>
                                </div>
                                <div>
                                    <label class="rotulo" for="pr-email">Email</label>
                                    <input id="pr-email" v-model="form.email" type="email" class="campo" placeholder="email@exemplo.com" />
                                    <p v-if="form.errors.email" class="erro">{{ form.errors.email }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Datas -->
                        <div class="rounded-[14px] border border-linha bg-white p-5 sm:p-6">
                            <h2 class="m-0 mb-4 flex items-center gap-2.5 text-[21px] font-extrabold"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-escuro text-[13px] font-extrabold text-white">2</span>Datas pretendidas</h2>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="rotulo" for="pr-ini">Data de início <span class="text-perigo">*</span></label>
                                    <input id="pr-ini" v-model="form.data_inicio" type="date" :min="hoje" class="campo" required />
                                    <p v-if="form.errors.data_inicio" class="erro">{{ form.errors.data_inicio }}</p>
                                </div>
                                <div>
                                    <label class="rotulo" for="pr-fim">Data de fim <span class="text-perigo">*</span></label>
                                    <input id="pr-fim" v-model="form.data_fim" type="date" :min="form.data_inicio || hoje" class="campo" required />
                                    <p v-if="form.errors.data_fim" class="erro">{{ form.errors.data_fim }}</p>
                                </div>
                            </div>

                            <!-- Resumo de dias -->
                            <div v-if="numeroDias" class="mt-4 flex items-center gap-2.5 rounded-[10px] bg-verde-claro px-4 py-3 text-[15px] font-bold text-verde-escuro">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                                {{ numeroDias }} {{ numeroDias === 1 ? 'dia selecionado' : 'dias selecionados' }}
                            </div>

                            <!-- Aviso de conflito -->
                            <div v-if="temConflito" class="mt-4 flex items-start gap-2.5 rounded-[10px] border border-[#F0C4BD] bg-perigo-claro px-4 py-3" role="alert">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mt-0.5 shrink-0 text-perigo"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg>
                                <div>
                                    <p class="m-0 text-[15px] font-bold text-perigo-texto">Estas datas já estão reservadas ou têm uma pré-reserva pendente.</p>
                                    <p class="m-0 mt-0.5 text-sm text-perigo-texto">Consulte o calendário ao lado e escolha outras datas.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Opções -->
                        <div v-if="opcoes && opcoes.length" class="rounded-[14px] border border-linha bg-white p-5 sm:p-6">
                            <h2 class="m-0 mb-1 flex items-center gap-2.5 text-[21px] font-extrabold"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-escuro text-[13px] font-extrabold text-white">3</span>Opções pretendidas</h2>
                            <p class="m-0 mb-4 text-[15px] text-suave">Selecione o que necessita. A associação confirmará a disponibilidade de cada item.</p>
                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <label
                                    v-for="o in opcoes"
                                    :key="o.id"
                                    class="flex min-h-[64px] cursor-pointer items-start gap-3 rounded-[10px] border-2 p-3.5 transition"
                                    :class="form.opcoes.includes(o.id)
                                        ? 'border-verde bg-verde-claro'
                                        : 'border-linha bg-white hover:border-linha-forte'"
                                >
                                    <input
                                        type="checkbox"
                                        :value="o.id"
                                        :checked="form.opcoes.includes(o.id)"
                                        class="mt-0.5 h-5 w-5 shrink-0 rounded border-linha-forte text-verde focus:ring-verde"
                                        @change="toggleOpcao(o.id)"
                                    />
                                    <div class="min-w-0">
                                        <div class="text-base font-bold">{{ o.nome }}</div>
                                        <div v-if="o.descricao" class="mt-0.5 text-sm text-suave">{{ o.descricao }}</div>
                                        <div v-if="o.preco_extra > 0" class="mt-0.5 text-sm font-bold tabular-nums text-laranja-texto">+{{ Number(o.preco_extra).toFixed(2) }}€</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Notas -->
                        <div class="rounded-[14px] border border-linha bg-white p-5 sm:p-6">
                            <h2 class="m-0 mb-4 flex items-center gap-2.5 text-[21px] font-extrabold"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-escuro text-[13px] font-extrabold text-white">{{ opcoes && opcoes.length ? 4 : 3 }}</span>Informações adicionais</h2>
                            <textarea :class="{ '!border-perigo': form.errors.notas }"
                                v-model="form.notas"
                                rows="4"
                                class="campo campo-area"
                                aria-label="Informações adicionais"
                                placeholder="Ex: Número de pessoas previsto, tipo de evento, horário aproximado, necessidades especiais..."
                            ></textarea><span v-if="form.errors.notas" class="block text-[13px] font-semibold text-perigo-texto">{{ form.errors.notas }}</span>
                        </div>

                        <AvisoErros :errors="form.errors" :excluir="['data_fim', 'data_inicio', 'email', 'nome_cliente', 'notas', 'telefone']" class="text-[15px]" />
                        <!-- Botão enviar -->
                        <button
                            type="submit"
                            :disabled="form.processing || temConflito"
                            class="min-h-[60px] w-full rounded-[10px] px-4 py-3 text-lg font-extrabold text-white transition disabled:cursor-not-allowed"
                            :class="temConflito ? 'bg-perigo opacity-80' : 'bg-verde hover:bg-verde-escuro disabled:opacity-50'"
                        >
                            <span v-if="form.processing">A enviar...</span>
                            <span v-else-if="temConflito">Datas indisponíveis — escolha outras datas</span>
                            <span v-else>Enviar pré-reserva</span>
                        </button>

                        <p class="m-0 text-center text-sm text-suave">
                            A pré-reserva não é vinculativa. Entraremos em contacto para confirmar e acertar os detalhes.
                        </p>
                    </form>
                </div>

                <!-- Calendário de disponibilidade -->
                <div class="order-1 min-w-0 lg:order-2">
                    <div class="rounded-[14px] border border-linha bg-white p-5 lg:sticky lg:top-24">
                        <h2 class="m-0 text-[21px] font-extrabold">Disponibilidade</h2>
                        <p class="m-0 mt-0.5 text-sm text-suave">Dias a vermelho já estão ocupados.</p>

                        <!-- Legenda -->
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-[13px] font-semibold text-suave">
                            <div class="flex items-center gap-1.5">
                                <span class="h-3.5 w-3.5 rounded-full bg-verde"></span>
                                <span>Selecionado</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="h-3.5 w-3.5 rounded-full border border-[#F0C4BD] bg-perigo-claro"></span>
                                <span>Ocupado</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="h-3.5 w-3.5 rounded-full border border-linha-forte bg-white"></span>
                                <span>Disponível</span>
                            </div>
                        </div>

                        <div class="mt-4 space-y-5">
                            <div v-for="({ ano, mes }) in mesesExibir()" :key="`${ano}-${mes}`">
                                <div class="mb-1.5 text-center text-[13px] font-extrabold uppercase tracking-[0.1em] text-suave">
                                    {{ mesesNomes[mes] }} {{ ano }}
                                </div>
                                <!-- Cabeçalho dias da semana -->
                                <div class="grid grid-cols-7 text-center">
                                    <div v-for="(d, k) in ['D','S','T','Q','Q','S','S']" :key="k" class="py-1 text-[11px] font-bold text-suave-2">{{ d }}</div>
                                </div>
                                <!-- Dias -->
                                <div class="grid grid-cols-7 gap-y-1 text-center">
                                    <div
                                        v-for="(dia, i) in diasDoMes(ano, mes)"
                                        :key="i"
                                        class="mx-auto flex aspect-square w-full max-w-[44px] select-none items-center justify-center rounded-full text-sm tabular-nums transition"
                                        :class="dia ? [
                                            isPast(toDateStr(ano, mes, dia)) ? 'text-linha-forte cursor-default'
                                            : isDiaBusy(toDateStr(ano, mes, dia)) ? 'bg-perigo-claro text-perigo-texto font-bold cursor-not-allowed'
                                            : estaNoIntervalo(toDateStr(ano, mes, dia)) ? 'bg-verde text-white font-extrabold cursor-pointer'
                                            : 'text-tinta hover:bg-verde-claro cursor-pointer font-semibold'
                                        ] : ''"
                                        :title="dia && isDiaBusy(toDateStr(ano, mes, dia)) ? 'Ocupado' : undefined"
                                        @click="dia && clicarDia(toDateStr(ano, mes, dia))"
                                    >
                                        {{ dia }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="m-0 mt-4 flex items-start gap-2 border-t border-linha-fraca pt-3 text-[13px] text-suave">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mt-px shrink-0 text-verde"><path d="M9 18h6M10 22h4" /><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z" /></svg>
                            Clica num dia disponível para preencher as datas automaticamente.
                        </p>
                    </div>
                </div>

            </div>
        </main>
    </PublicShell>
</template>

<style scoped>
.rotulo { display: block; margin-bottom: 6px; font-size: 15px; font-weight: 700; color: #16201C; }
.campo {
    width: 100%;
    height: 50px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid #D5D9D3;
    background: #FFFFFF;
    color: #16201C;
    font-size: 16px;
}
.campo-area { height: auto; padding: 12px 14px; resize: vertical; }
.campo:focus { border-color: #0F6B4F; box-shadow: 0 0 0 3px rgb(15 107 79 / 0.15); outline: none; }
.erro { margin: 4px 0 0; font-size: 14px; font-weight: 600; color: #A3241A; }
</style>
