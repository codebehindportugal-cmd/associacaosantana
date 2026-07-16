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
    { tipo: 'restaurante', icon: '🍽️', label: 'Restaurante' },
    { tipo: 'reservas',    icon: '📋', label: 'Reservas'    },
    { tipo: 'bar',         icon: '🍺', label: 'Bares'       },
    { tipo: 'cafe',        icon: '☕', label: 'Café'        },
    { tipo: 'cotas',       icon: '💳', label: 'Cotas'       },
];

const ecras = [
    { href: route('ecra-reservas'),    icon: '📺', label: 'Ecrã Reservas',       desc: 'Ecrã de chamadas'         },
    { href: route('patrocinios.ecra'), icon: '🏆', label: 'Ecrã Patrocinadores', desc: 'Painel de patrocinadores' },
    { href: route('precario'),         icon: '📃', label: 'Preçário',             desc: 'Lista de preços'          },
    { href: route('secao.sala', props.salaEcraCodigo || 'sala'), icon: '🪑', label: 'Ecrã Sala', desc: 'Mapa das mesas' },
];

const secoes = [
    { href: route('secao.bebidas'),         label: 'Bebidas'         },
    { href: route('secao.frango'),          label: 'Frango'          },
    { href: route('secao.comida'),          label: 'Comida'          },
    { href: route('secao.cozinha'),         label: 'Cozinha'         },
    { href: route('secao.sobremesas'),      label: 'Sobremesas'      },
    { href: route('secao.acompanhamentos'), label: 'Acompanhamentos' },
    { href: route('secao.servico'),         label: 'Serviço'         },
    { href: route('secao.bar'),             label: 'Bar'             },
];

const tituloTipo = (tipo) => ({ bar: 'Bar', cafe: 'Café', restaurante: 'Restaurante', reservas: 'Reservas', cotas: 'Cotas' }[tipo] || tipo);
const terminal = computed(() => (props.terminais ?? []).find((item) => item.id === escolhido.value));
const escolher = (item) => { escolhido.value = item.id; form.terminal_id = item.id; form.pin = ''; };
const entrar = () => form.post(route('pos.login.store'));
</script>

<template>
    <div class="pos-hub min-h-screen text-white">
        <!-- Header -->
        <header class="hub-header flex items-center justify-between border-b border-white/10 px-6 py-4">
            <div class="flex items-center gap-3">
                <img src="/images/santana-logo.png" alt="ARDC Santana" class="h-10 w-10 rounded-full border border-white/20 object-contain p-0.5">
                <div>
                    <div class="text-base font-black tracking-wide text-white">ARDC SANTANA</div>
                    <div class="text-xs font-bold uppercase tracking-widest text-white/50">Sistema de Gestão</div>
                </div>
            </div>
            <div class="text-xs font-bold text-white/30">POS</div>
        </header>

        <div class="mx-auto max-w-5xl space-y-8 px-5 py-8">

            <!-- Modo Comissão -->
            <section v-if="comissao" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-black uppercase tracking-wide text-amber-400">Modo Comissão</div>
                        <div class="text-lg font-black">{{ comissaoNome }}</div>
                        <div class="text-xs text-white/50">Podes entrar em qualquer terminal sem PIN — escolhe abaixo.</div>
                    </div>
                    <button type="button" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-bold hover:bg-white/20" @click="sairComissao">Sair do modo comissão</button>
                </div>
            </section>
            <section v-else>
                <button type="button" class="rounded-lg border border-white/15 bg-white/5 px-4 py-2 text-sm font-bold text-white/70 hover:bg-white/10" @click="mostrarComissao = !mostrarComissao">
                    👔 Sou da comissão
                </button>
                <form v-if="mostrarComissao" class="mt-3 grid max-w-md gap-3 rounded-xl bg-white/5 p-5" @submit.prevent="entrarComissao">
                    <input v-model="comissaoForm.nome" type="text" class="w-full rounded-lg border-white/10 bg-white/5 p-3 text-center text-lg font-black text-white placeholder-white/30" placeholder="O teu nome">
                    <input v-model="comissaoForm.pin" type="password" inputmode="numeric" autocomplete="off" class="w-full rounded-lg border-white/10 bg-white/5 p-3 text-center text-2xl font-black text-white placeholder-white/30" placeholder="PIN da comissão">
                    <div v-if="comissaoForm.errors.nome" class="rounded-lg bg-red-600/80 p-2 text-center text-sm font-bold">{{ comissaoForm.errors.nome }}</div>
                    <div v-if="comissaoForm.errors.pin" class="rounded-lg bg-red-600/80 p-2 text-center text-sm font-bold">{{ comissaoForm.errors.pin }}</div>
                    <div v-if="comissaoForm.errors.comissao_pin" class="rounded-lg bg-red-600/80 p-2 text-center text-sm font-bold">{{ comissaoForm.errors.comissao_pin }}</div>
                    <button class="rounded-xl bg-amber-600 p-3 font-black disabled:opacity-50" :disabled="comissaoForm.processing">VALIDAR</button>
                </form>
            </section>

            <!-- Comissão: todos os terminais agrupados por tipo -->
            <section v-if="comissao">
                <h2 class="section-label">Todos os terminais — 1 clique para entrar</h2>
                <div v-for="(grupo, tipo) in terminaisPorTipo" :key="tipo" class="mb-5">
                    <div class="mb-2 text-xs font-black uppercase tracking-widest text-white/40">{{ tituloTipo(tipo) }}</div>
                    <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                        <button
                            v-for="item in grupo"
                            :key="item.id"
                            type="button"
                            class="hub-card rounded-xl p-5 text-left transition"
                            :class="escolhido === item.id ? 'hub-card-active' : ''"
                            @click="escolher(item)"
                        >
                            <div class="text-xl font-black">{{ item.nome }}</div>
                            <div class="mt-1 text-sm font-bold text-white/60">{{ item.localizacao }}</div>
                            <div v-if="item.ultimo_operador" class="mt-2 text-xs text-white/40">Último: {{ item.ultimo_operador }}</div>
                        </button>
                    </div>
                </div>
                <form v-if="terminal" class="mx-auto mt-2 max-w-sm rounded-xl bg-white/5 p-6" @submit.prevent="entrar">
                    <h3 class="mb-4 text-center text-xl font-black">{{ terminal.nome }}</h3>
                    <input v-model="form.operador_nome" type="text" autocomplete="name" class="w-full rounded-lg border-white/10 bg-white/5 p-4 text-center text-xl font-black text-white placeholder-white/30" placeholder="Nome de quem atende">
                    <div v-if="form.errors.operador_nome" class="mt-3 rounded-lg bg-red-600/80 p-3 text-center font-bold">{{ form.errors.operador_nome }}</div>
                    <div v-if="form.errors.pin" class="mt-3 rounded-lg bg-red-600/80 p-3 text-center font-bold">{{ form.errors.pin }}</div>
                    <button class="mt-4 w-full rounded-xl bg-emerald-600 p-4 text-lg font-black disabled:opacity-50" :disabled="form.processing">ENTRAR SEM PIN</button>
                </form>
            </section>

            <!-- POS Terminais -->
            <section v-if="!comissao">
                <h2 class="section-label">Terminais POS</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5">
                    <a
                        v-for="screen in posScreens"
                        :key="screen.tipo"
                        :href="route('pos.login', { tipo: screen.tipo })"
                        class="hub-card group flex flex-col items-center gap-2 rounded-xl p-5 text-center transition"
                        :class="tipoSelecionado === screen.tipo ? 'hub-card-active' : ''"
                    >
                        <span class="text-3xl">{{ screen.icon }}</span>
                        <span class="text-sm font-black uppercase tracking-wide">{{ screen.label }}</span>
                    </a>
                </div>
            </section>

            <!-- Selecionar Terminal + PIN -->
            <section v-if="!comissao && terminais && terminais.length">
                <h2 class="section-label">Selecionar Terminal — {{ tituloTipo(tipoSelecionado) }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                    <button
                        v-for="item in terminais"
                        :key="item.id"
                        type="button"
                        class="hub-card rounded-xl p-5 text-left transition"
                        :class="escolhido === item.id ? 'hub-card-active' : ''"
                        @click="escolher(item)"
                    >
                        <div class="text-xl font-black">{{ item.nome }}</div>
                        <div class="mt-1 text-sm font-bold text-white/60">{{ item.localizacao }}</div>
                    </button>
                </div>
                <form v-if="terminal" class="mx-auto mt-5 max-w-sm rounded-xl bg-white/5 p-6" @submit.prevent="entrar">
                    <h3 class="mb-4 text-center text-xl font-black">{{ terminal.nome }}</h3>
                    <input v-model="form.operador_nome" type="text" autocomplete="name" class="mb-3 w-full rounded-lg border-white/10 bg-white/5 p-4 text-center text-xl font-black text-white placeholder-white/30" placeholder="Nome de quem atende">
                    <input v-model="form.pin" type="password" inputmode="numeric" autocomplete="off" autofocus class="w-full rounded-lg border-white/10 bg-white/5 p-4 text-center text-3xl font-black text-white placeholder-white/30" placeholder="PIN">
                    <div v-if="form.errors.operador_nome" class="mt-3 rounded-lg bg-red-600/80 p-3 text-center font-bold">{{ form.errors.operador_nome }}</div>
                    <div v-if="form.errors.pin" class="mt-3 rounded-lg bg-red-600/80 p-3 text-center font-bold">{{ form.errors.pin }}</div>
                    <button class="mt-4 w-full rounded-xl bg-emerald-600 p-4 text-lg font-black disabled:opacity-50" :disabled="form.processing">ENTRAR</button>
                </form>
            </section>

            <!-- Ecrãs -->
            <section>
                <h2 class="section-label">Ecrãs</h2>
                <div class="grid gap-3 sm:grid-cols-3">
                    <a
                        v-for="ecra in ecras"
                        :key="ecra.href"
                        :href="ecra.href"
                        class="hub-card flex items-center gap-4 rounded-xl p-4 transition"
                    >
                        <span class="text-2xl">{{ ecra.icon }}</span>
                        <div>
                            <div class="font-black">{{ ecra.label }}</div>
                            <div class="text-xs text-white/50">{{ ecra.desc }}</div>
                        </div>
                    </a>
                </div>
            </section>

            <!-- Secções -->
            <section>
                <h2 class="section-label">Secções</h2>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <a
                        v-for="sec in secoes"
                        :key="sec.href"
                        :href="sec.href"
                        class="hub-card rounded-xl px-4 py-3 text-center text-sm font-black uppercase tracking-wide transition"
                    >
                        {{ sec.label }}
                    </a>
                </div>
            </section>

        </div>
    </div>
</template>

<style scoped>
.pos-hub {
    background: #111827;
}

.hub-header {
    background: rgba(255, 255, 255, 0.03);
}

.section-label {
    margin-bottom: 0.75rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.4);
}

.hub-card {
    background: rgba(255, 255, 255, 0.05);
    color: white;
    cursor: pointer;
    text-decoration: none;
}

.hub-card:hover {
    background: rgba(255, 255, 255, 0.1);
}

.hub-card-active {
    background: rgba(16, 185, 129, 0.2);
    outline: 2px solid rgb(16, 185, 129);
    outline-offset: 0;
}
</style>
