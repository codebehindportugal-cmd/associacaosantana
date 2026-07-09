<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({ terminais: Array, tipoSelecionado: String });
const escolhido = ref(null);
const form = useForm({ terminal_id: '', operador_nome: '', pin: '' });

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
    { href: route('precario'),         icon: '📃', label: 'Precário',             desc: 'Lista de preços'          },
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

            <!-- POS Terminais -->
            <section>
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
            <section v-if="terminais && terminais.length">
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
