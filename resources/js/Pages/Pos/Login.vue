<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    terminais: Array,
    tipoSelecionado: String,
    comissao: Boolean,
    comissaoNome: String,
    salaEcraCodigo: String,
});
const escolhido = ref(null);
const form = useForm({ terminal_id: '', operador_nome: props.comissao ? (props.comissaoNome || '') : '', pin: '' });

// Modo comissão
const mostrarComissao = ref(false);
const comissaoForm = useForm({ nome: '', pin: '' });

const entrarComissao = () => {
    comissaoForm.post(route('pos.login.comissao'), {
        preserveScroll: true,
        onSuccess: () => {
            mostrarComissao.value = false;
            comissaoForm.reset();
        },
    });
};

const sairComissao = () => router.post(route('pos.login.comissao.sair'), {}, { preserveScroll: true });

const terminaisPorTipo = computed(() => (props.terminais ?? []).reduce((acc, t) => {
    (acc[t.tipo] = acc[t.tipo] || []).push(t);
    return acc;
}, {}));

const posScreens = [
    { tipo: 'restaurante', icon: 'restaurante', label: 'Restaurante' },
    { tipo: 'reservas',    icon: 'reservas',    label: 'Reservas'    },
    { tipo: 'bar',         icon: 'bar',         label: 'Bares'       },
    { tipo: 'cafe',        icon: 'cafe',        label: 'Café'        },
    { tipo: 'cotas',       icon: 'cotas',       label: 'Cotas'       },
];

const ecras = [
    { href: route('ecra-reservas'),    label: 'Ecrã Reservas',       desc: 'Ecrã de chamadas'         },
    { href: route('patrocinios.ecra'), label: 'Ecrã Patrocinadores', desc: 'Painel de patrocinadores' },
    { href: route('precario'),         label: 'Preçário',             desc: 'Lista de preços'          },
    { href: route('secao.sala', props.salaEcraCodigo || 'sala'), label: 'Ecrã Sala', desc: 'Mapa das mesas' },
];

const secoes = [
    { href: route('secao.bebidas'),         label: 'Bebidas',         cor: 'text-secao-bar' },
    { href: route('secao.frango'),          label: 'Frango',          cor: 'text-secao-grelhados' },
    { href: route('secao.comida'),          label: 'Comida',          cor: 'text-secao-cozinha' },
    { href: route('secao.cozinha'),         label: 'Cozinha',         cor: 'text-secao-cozinha' },
    { href: route('secao.sobremesas'),      label: 'Sobremesas',      cor: 'text-secao-sobremesas' },
    { href: route('secao.acompanhamentos'), label: 'Acompanhamentos', cor: 'text-secao-acompanhamentos' },
    { href: route('secao.servico'),         label: 'Serviço',         cor: 'text-secao-servico' },
    { href: route('secao.bar'),             label: 'Bar',             cor: 'text-secao-bar' },
];

const tituloTipo = (tipo) => ({ bar: 'Bar', cafe: 'Café', restaurante: 'Restaurante', reservas: 'Reservas', cotas: 'Cotas' }[tipo] || tipo);
const terminal = computed(() => (props.terminais ?? []).find((item) => item.id === escolhido.value));
const outroNome = ref(false);

const escolher = (item) => {
    escolhido.value = item.id;
    form.terminal_id = item.id;
    form.pin = '';

    // Modo comissão: já se identificou no início, entra logo com 1 clique
    if (props.comissao && !outroNome.value) entrar();
};
const entrar = () => form.post(route('pos.login.store'));

// ---------------------------------------------------------------------------
// Teclado numérico do PIN (só UI: escreve no mesmo campo do formulário)
// ---------------------------------------------------------------------------
const teclasPin = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'clr', '0', 'del'];
const premirTecla = (alvo, tecla) => {
    const atual = String(alvo.pin ?? '');
    if (tecla === 'del') alvo.pin = atual.slice(0, -1);
    else if (tecla === 'clr') alvo.pin = '';
    else if (atual.length < 8) alvo.pin = atual + tecla;
};
// O painel da direita mostra o login da comissão quando o pedem; senão, o PIN do terminal
const painelComissao = computed(() => !props.comissao && mostrarComissao.value);

</script>

<template>
    <div class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums">
        <header class="flex h-16 shrink-0 items-center justify-between gap-3 bg-escuro px-4 text-white sm:px-6">
            <div class="flex items-center gap-3.5">
                <img src="/images/santana-logo.png" alt="ARDC Santana" class="h-10 w-10 rounded-full bg-white object-contain p-0.5">
                <div class="leading-tight">
                    <span class="block text-lg font-extrabold">ARDC Santana</span>
                    <span class="block text-xs uppercase tracking-wider text-escuro-inativo">Sistema de Gestão · POS</span>
                </div>
            </div>
            <button
                v-if="comissao"
                type="button"
                class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-bold text-white"
                @click="sairComissao"
            >Sair do modo comissão</button>
            <button
                v-else
                type="button"
                class="flex h-11 items-center gap-2 rounded-[10px] px-4 text-[15px] font-bold text-white"
                :class="mostrarComissao ? 'bg-laranja' : 'bg-escuro-2'"
                :aria-pressed="mostrarComissao"
                @click="mostrarComissao = !mostrarComissao"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" /></svg>
                {{ mostrarComissao ? 'Voltar ao PIN do terminal' : 'Sou da comissão' }}
            </button>
        </header>

        <div class="grid flex-1 gap-5 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_420px]">
            <section class="flex min-w-0 flex-col gap-6">
                <!-- Modo comissão ativo -->
                <div v-if="comissao" class="rounded-[14px] border-2 border-laranja bg-laranja-claro p-4">
                    <span class="block text-sm font-bold uppercase tracking-wider text-laranja-texto">Modo comissão</span>
                    <span class="block text-xl font-extrabold">{{ comissaoNome }}</span>
                    <span class="block text-sm text-suave">Entras em qualquer terminal com 1 toque, sem PIN e sem voltar a escrever o nome.</span>
                </div>

                <!-- Passo 1: tipo de POS -->
                <div v-if="!comissao">
                    <h2 class="mb-3 flex items-center gap-2.5 text-[15px] font-bold text-suave"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-escuro text-xs text-white">1</span>Que POS vais usar?</h2>
                    <div role="group" aria-label="Tipo de terminal" class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
                        <a
                            v-for="screen in posScreens"
                            :key="screen.tipo"
                            :href="route('pos.login', { tipo: screen.tipo })"
                            class="flex min-h-[92px] flex-col items-center justify-center gap-2 rounded-[14px] text-lg font-bold"
                            :class="tipoSelecionado === screen.tipo ? 'border-2 border-verde bg-verde text-white hover:text-white' : 'border border-linha bg-white text-tinta hover:text-tinta'"
                            :aria-current="tipoSelecionado === screen.tipo ? 'true' : undefined"
                        >
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <template v-if="screen.icon === 'restaurante'"><path d="M7 2v20M4 2v6a3 3 0 0 0 6 0V2" /><path d="M18 22V2c-2 1.5-3 4-3 7s1 4 3 4" /></template>
                                <template v-else-if="screen.icon === 'reservas'"><rect x="5" y="4" width="14" height="18" rx="2" /><path d="M9 2h6v4H9zM9 11h6M9 15h6" /></template>
                                <template v-else-if="screen.icon === 'bar'"><path d="M6 6h9v14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2z" /><path d="M15 9h2a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-2M9 10v8M12 10v8" /></template>
                                <template v-else-if="screen.icon === 'cafe'"><path d="M4 9h12v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z" /><path d="M16 11h2a2 2 0 0 1 0 4h-2M8 2v3M12 2v3" /></template>
                                <template v-else><rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20M6 15h4" /></template>
                            </svg>
                            {{ screen.label }}
                        </a>
                    </div>
                </div>

                <!-- Passo 2: terminal -->
                <div v-if="!comissao && terminais && terminais.length">
                    <h2 class="mb-3 flex items-center gap-2.5 text-[15px] font-bold text-suave"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-escuro text-xs text-white">2</span>Escolhe o terminal — {{ tituloTipo(tipoSelecionado) }}</h2>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <button
                            v-for="item in terminais"
                            :key="item.id"
                            type="button"
                            class="flex min-h-[92px] flex-col justify-center rounded-[14px] bg-white px-4 py-3 text-left"
                            :class="escolhido === item.id ? 'border-[3px] border-verde bg-verde-claro' : 'border border-linha'"
                            :aria-pressed="escolhido === item.id"
                            @click="escolher(item)"
                        >
                            <span class="text-xs font-bold uppercase tracking-wider text-suave">{{ tituloTipo(item.tipo) }}</span>
                            <span class="text-xl font-extrabold">{{ item.nome }}</span>
                            <span class="text-sm text-suave">{{ item.localizacao }}<template v-if="item.ultimo_operador"> · Último: {{ item.ultimo_operador }}</template></span>
                        </button>
                    </div>
                </div>

                <!-- Modo comissão: todos os terminais por tipo -->
                <div v-if="comissao">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-[15px] font-bold text-suave">Todos os terminais — 1 toque para entrar</h2>
                        <label class="flex min-h-11 items-center gap-2 text-sm font-semibold text-suave">
                            <input v-model="outroNome" type="checkbox" class="h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde">
                            Entrar com outro nome
                        </label>
                    </div>
                    <div v-for="(grupo, tipo) in terminaisPorTipo" :key="tipo" class="mb-5">
                        <div class="mb-2 text-xs font-bold uppercase tracking-wider text-suave">{{ tituloTipo(tipo) }}</div>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <button
                                v-for="item in grupo"
                                :key="item.id"
                                type="button"
                                class="flex min-h-[92px] flex-col justify-center rounded-[14px] bg-white px-4 py-3 text-left"
                                :class="escolhido === item.id ? 'border-[3px] border-verde bg-verde-claro' : 'border border-linha'"
                                :aria-pressed="escolhido === item.id"
                                @click="escolher(item)"
                            >
                                <span class="text-xl font-extrabold">{{ item.nome }}</span>
                                <span class="text-sm text-suave">{{ item.localizacao }}</span>
                                <span v-if="item.ultimo_operador" class="text-xs text-suave-2">Último: {{ item.ultimo_operador }}</span>
                            </button>
                        </div>
                    </div>
                    <p v-if="form.processing && !outroNome" class="text-center text-sm font-semibold text-suave">A entrar em {{ terminal?.nome }}…</p>
                    <div v-if="!outroNome && form.errors.operador_nome" role="alert" class="rounded-[10px] bg-perigo-claro p-3 text-center font-semibold text-perigo-texto">{{ form.errors.operador_nome }}</div>
                </div>

                <div class="flex-1"></div>

                <!-- Ecrãs e secções -->
                <div class="grid gap-5 border-t border-linha pt-4 md:grid-cols-2">
                    <div>
                        <span class="mb-2 block text-xs font-bold uppercase tracking-wider text-suave">Ecrãs</span>
                        <div class="flex flex-wrap gap-2">
                            <a v-for="ecra in ecras" :key="ecra.href" :href="ecra.href" :title="ecra.desc" class="flex h-11 items-center rounded-[10px] border border-linha-forte bg-white px-3.5 text-sm font-semibold text-tinta hover:text-tinta">{{ ecra.label }}</a>
                        </div>
                    </div>
                    <div>
                        <span class="mb-2 block text-xs font-bold uppercase tracking-wider text-suave">Secções</span>
                        <div class="flex flex-wrap gap-2">
                            <a v-for="sec in secoes" :key="sec.href" :href="sec.href" class="flex h-11 items-center rounded-[10px] border border-linha-forte bg-white px-3.5 text-sm font-semibold" :class="sec.cor">{{ sec.label }}</a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Painel de entrada -->
            <aside v-if="painelComissao || (!comissao && terminais && terminais.length) || (comissao && terminal && outroNome)" aria-label="Entrar" class="flex flex-col gap-3 rounded-[14px] border border-linha bg-white p-5">
                <!-- Login da comissão -->
                <form v-if="painelComissao" class="flex flex-1 flex-col gap-3" @submit.prevent="entrarComissao">
                    <div>
                        <span class="block text-lg font-extrabold">Sou da comissão</span>
                        <span class="block text-sm text-suave">Depois de validar, entras em qualquer terminal com 1 toque, sem PIN e sem voltar a escrever o nome.</span>
                    </div>
                    <label class="block text-sm font-semibold text-suave">O teu nome
                        <input v-model="comissaoForm.nome" type="text" autocomplete="name" class="mt-1 h-14 w-full rounded-[10px] border-linha-forte bg-fundo text-lg font-bold text-tinta focus:border-verde focus:ring-verde" placeholder="O teu nome">
                    </label>
                    <label class="block text-sm font-semibold text-suave">PIN da comissão
                        <input v-model="comissaoForm.pin" type="password" inputmode="numeric" autocomplete="off" class="mt-1 h-14 w-full rounded-[10px] border-2 bg-fundo text-center text-3xl font-bold tracking-[0.5em] text-tinta focus:border-verde focus:ring-verde" :class="comissaoForm.errors.pin || comissaoForm.errors.comissao_pin ? 'border-perigo' : 'border-linha-forte'" placeholder="••••">
                    </label>
                    <div v-for="erro in [comissaoForm.errors.nome, comissaoForm.errors.pin, comissaoForm.errors.comissao_pin].filter(Boolean)" :key="erro" role="alert" class="rounded-[10px] bg-perigo-claro p-2 text-center text-sm font-semibold text-perigo-texto">{{ erro }}</div>
                    <div class="grid grid-cols-3 gap-2">
                        <button v-for="t in teclasPin" :key="t" type="button" class="h-16 rounded-[10px] border border-linha-forte font-bold" :class="['del', 'clr'].includes(t) ? 'bg-fundo text-[17px]' : 'bg-white text-[26px]'" :aria-label="t === 'del' ? 'Apagar' : (t === 'clr' ? 'Limpar PIN' : t)" @click="premirTecla(comissaoForm, t)">{{ t === 'del' ? '←' : (t === 'clr' ? 'Limpar' : t) }}</button>
                    </div>
                    <div class="flex-1"></div>
                    <button class="h-[72px] rounded-[14px] bg-verde text-xl font-bold text-white hover:bg-verde-escuro disabled:opacity-45" :disabled="comissaoForm.processing">Validar</button>
                </form>

                <!-- Comissão: entrar com outro nome (sem PIN) -->
                <form v-else-if="comissao" class="flex flex-1 flex-col gap-3" @submit.prevent="entrar">
                    <div>
                        <span class="block text-xs font-bold uppercase tracking-wider text-suave">Entrar em</span>
                        <span class="block text-2xl font-extrabold">{{ terminal.nome }}</span>
                    </div>
                    <label class="block text-sm font-semibold text-suave">Nome de quem atende
                        <input v-model="form.operador_nome" type="text" autocomplete="name" class="mt-1 h-14 w-full rounded-[10px] border-linha-forte bg-fundo text-lg font-bold text-tinta focus:border-verde focus:ring-verde" placeholder="Nome de quem atende">
                    </label>
                    <div v-for="erro in [form.errors.operador_nome, form.errors.pin].filter(Boolean)" :key="erro" role="alert" class="rounded-[10px] bg-perigo-claro p-3 text-center font-semibold text-perigo-texto">{{ erro }}</div>
                    <div class="flex-1"></div>
                    <button class="h-[72px] rounded-[14px] bg-verde text-xl font-bold text-white hover:bg-verde-escuro disabled:opacity-45" :disabled="form.processing">Entrar sem PIN</button>
                </form>

                <!-- PIN do terminal -->
                <form v-else class="flex flex-1 flex-col gap-3" @submit.prevent="entrar">
                    <div>
                        <span class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-suave"><span class="flex h-5 w-5 items-center justify-center rounded-full bg-escuro text-[11px] text-white">3</span>Entrar em</span>
                        <span class="block text-2xl font-extrabold">{{ terminal ? terminal.nome : 'Escolhe um terminal' }}</span>
                    </div>
                    <label class="block text-sm font-semibold text-suave">Nome de quem atende
                        <input v-model="form.operador_nome" type="text" autocomplete="name" class="mt-1 h-14 w-full rounded-[10px] border-linha-forte bg-fundo text-lg font-bold text-tinta focus:border-verde focus:ring-verde" placeholder="Nome de quem atende">
                    </label>
                    <label class="block text-sm font-semibold text-suave">PIN do terminal
                        <input v-model="form.pin" type="password" inputmode="numeric" autocomplete="off" autofocus class="mt-1 h-14 w-full rounded-[10px] border-2 bg-fundo text-center text-3xl font-bold tracking-[0.5em] text-tinta focus:border-verde focus:ring-verde" :class="form.errors.pin ? 'border-perigo' : 'border-linha-forte'" placeholder="••••">
                    </label>
                    <div v-for="erro in [form.errors.operador_nome, form.errors.pin].filter(Boolean)" :key="erro" role="alert" class="rounded-[10px] bg-perigo-claro p-2 text-center text-sm font-semibold text-perigo-texto">{{ erro }}</div>
                    <div class="grid grid-cols-3 gap-2">
                        <button v-for="t in teclasPin" :key="t" type="button" class="h-16 rounded-[10px] border border-linha-forte font-bold" :class="['del', 'clr'].includes(t) ? 'bg-fundo text-[17px]' : 'bg-white text-[26px]'" :aria-label="t === 'del' ? 'Apagar' : (t === 'clr' ? 'Limpar PIN' : t)" @click="premirTecla(form, t)">{{ t === 'del' ? '←' : (t === 'clr' ? 'Limpar' : t) }}</button>
                    </div>
                    <div class="flex-1"></div>
                    <button class="h-[72px] rounded-[14px] bg-verde text-xl font-bold text-white hover:bg-verde-escuro disabled:opacity-45" :disabled="!terminal || form.processing">Entrar</button>
                </form>
            </aside>
        </div>
    </div>
</template>
