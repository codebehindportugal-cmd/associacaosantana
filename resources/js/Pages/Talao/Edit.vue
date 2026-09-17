<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelos: Array,
    eventos: Array,
    categorias: Array,
    impressoras: Array,
});

// Largura util de uma impressora de 80mm em fonte normal
const LARGURA = 42;

const emUso = computed(() => props.modelos.find((modelo) => modelo.em_uso) || null);
const selecionadoId = ref(emUso.value?.id ?? props.modelos[0]?.id ?? null);
const selecionado = computed(() => props.modelos.find((m) => m.id === selecionadoId.value) || null);

const form = useForm({
    nome: '',
    evento_id: null,
    titulo: '',
    cabecalho: '',
    rodape: '',
    instrucoes_individual: '',
    rodape_em_pedidos: false,
    prepago_apenas_individuais: false,
    ativo: true,
});

const carregar = (modelo) => {
    if (!modelo) return;
    form.clearErrors();
    form.nome = modelo.nome ?? '';
    form.evento_id = modelo.evento_id ?? null;
    form.titulo = modelo.titulo ?? '';
    form.cabecalho = modelo.cabecalho ?? '';
    form.rodape = modelo.rodape ?? '';
    form.instrucoes_individual = modelo.instrucoes_individual ?? '';
    form.rodape_em_pedidos = Boolean(modelo.rodape_em_pedidos);
    form.prepago_apenas_individuais = Boolean(modelo.prepago_apenas_individuais);
    form.ativo = modelo.ativo !== false;
};

watch(selecionado, carregar, { immediate: true });
watch(() => props.modelos, () => carregar(selecionado.value), { deep: true });

const novoForm = useForm({ nome: '', evento_id: null });
const impressoraId = ref(props.impressoras?.[0]?.id ?? null);

const linhas = (texto) => String(texto || '')
    .split(/\r\n|\r|\n/)
    .map((linha) => linha.trim())
    .filter((linha) => linha !== '');

const linhasCabecalho = computed(() => linhas(form.cabecalho));
const linhasRodape = computed(() => linhas(form.rodape));
const linhasInstrucoes = computed(() => linhas(form.instrucoes_individual));

const demasiadoLongas = computed(() => [
    ...linhasCabecalho.value,
    ...linhasRodape.value,
    ...linhasInstrucoes.value,
].filter((linha) => linha.length > LARGURA));

// POST com _method: o servidor bloqueia PATCH/DELETE directos
const guardar = () => form
    .transform((dados) => ({ ...dados, _method: 'patch' }))
    .post(route('talao.update', selecionadoId.value), { preserveScroll: true });

const criar = () => novoForm.post(route('talao.store'), {
    preserveScroll: true,
    onSuccess: () => novoForm.reset(),
});

const usar = (modelo) => router.post(route('talao.usar', modelo.id), {}, { preserveScroll: true });

const apagar = (modelo) => {
    if (confirm('Apagar o modelo "' + modelo.nome + '"?')) {
        router.post(route('talao.destroy', modelo.id), { _method: 'delete' }, { preserveScroll: true });
    }
};

const imprimirTeste = () => router.post(
    route('talao.teste', selecionadoId.value),
    { impressora_id: impressoraId.value },
    { preserveScroll: true },
);

// ---------------------------------------------------------------------------
// Produtos que saem em talao individual por unidade (um por cliente/unidade)
// ---------------------------------------------------------------------------
const individuais = ref(
    props.categorias.flatMap((c) => c.produtos || []).filter((p) => p.talao_individual).map((p) => p.id),
);

const alternar = (produtoId) => {
    const indice = individuais.value.indexOf(produtoId);
    if (indice === -1) individuais.value.push(produtoId);
    else individuais.value.splice(indice, 1);
};

const guardarProdutos = () => router.post(
    route('talao.produtos'),
    { produtos: individuais.value },
    { preserveScroll: true },
);

const categoriasComProdutos = computed(() => props.categorias.filter((c) => (c.produtos || []).length));
</script>

<template>
    <AppLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-black">Talão</h1>
            <p class="mt-1 text-sm text-slate-500">
                Um modelo por evento. O que estiver em uso é o que sai em todos os talões.
            </p>
            <div class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="font-black text-slate-800">Restaurante e café</div>
                    <p class="mt-1 text-xs text-slate-600">
                        O funcionamento do costume, incluindo a festa anual: o pedido é encaminhado
                        para a impressora de cada secção — cozinha, frango, bebidas — e a conta sai no
                        balcão. Configura-se em Impressoras, por secção.
                    </p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <div class="font-black text-slate-800">Evento com pré-pagamento</div>
                    <p class="mt-1 text-xs text-slate-600">
                        O cliente paga tudo à cabeça e leva <strong>um talão por unidade</strong>, cada um
                        cortado, com a senha e a tasquinha onde levanta. Saem agrupados por secção e no
                        fim vem a conta. Tudo na mesma impressora, a do posto. Liga-se no visto abaixo.
                    </p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[280px_1fr_320px]">
            <!-- Modelos -->
            <section class="rounded-lg bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-black uppercase tracking-wide text-slate-500">Modelos</h2>

                <div v-if="!modelos.length" class="rounded-md bg-slate-50 p-4 text-center text-xs font-bold text-slate-500">
                    Ainda não há modelos.
                </div>

                <ul class="space-y-2">
                    <li v-for="modelo in modelos" :key="modelo.id">
                        <button
                            type="button"
                            class="w-full rounded-md border p-3 text-left transition"
                            :class="modelo.id === selecionadoId ? 'border-slate-900 bg-slate-50' : 'border-slate-200 hover:bg-slate-50'"
                            @click="selecionadoId = modelo.id"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <strong class="text-sm">{{ modelo.nome }}</strong>
                                <span v-if="modelo.em_uso" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-800">Em uso</span>
                            </div>
                            <div v-if="modelo.evento" class="mt-0.5 text-xs text-slate-500">{{ modelo.evento.titulo }}</div>
                            <div v-if="!modelo.ativo" class="mt-0.5 text-xs font-bold text-amber-700">Desligado</div>
                        </button>
                        <div v-if="modelo.id === selecionadoId" class="mt-1 flex gap-3 px-1 text-xs">
                            <button v-if="!modelo.em_uso" type="button" class="font-bold text-emerald-700" @click="usar(modelo)">Usar este</button>
                            <button v-if="!modelo.em_uso" type="button" class="font-bold text-red-700" @click="apagar(modelo)">Apagar</button>
                        </div>
                    </li>
                </ul>

                <form class="mt-4 space-y-2 border-t border-slate-100 pt-4" @submit.prevent="criar">
                    <div class="text-xs font-black uppercase tracking-wide text-slate-500">Novo modelo</div>
                    <input v-model="novoForm.nome" required class="w-full rounded-md border-slate-300 text-sm" placeholder="Nome (ex.: Carvalhal Fest)">
                    <select v-model="novoForm.evento_id" class="w-full rounded-md border-slate-300 text-sm">
                        <option :value="null">Sem evento associado</option>
                        <option v-for="evento in eventos" :key="evento.id" :value="evento.id">{{ evento.titulo }}</option>
                    </select>
                    <p class="text-xs text-slate-500">Se escolheres um evento, o título e as datas já vêm preenchidos.</p>
                    <button class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-bold text-white" :disabled="novoForm.processing">Criar</button>
                    <div v-if="novoForm.errors.nome" class="text-xs text-red-700">{{ novoForm.errors.nome }}</div>
                </form>
            </section>

            <!-- Editor -->
            <section class="rounded-lg bg-white p-5 shadow-sm">
                <div v-if="!selecionado" class="rounded-md bg-slate-50 p-6 text-center text-sm font-bold text-slate-500">
                    Cria um modelo para começar.
                </div>

                <form v-else class="space-y-4" @submit.prevent="guardar">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-bold text-slate-700">Nome do modelo</label>
                            <input v-model="form.nome" required maxlength="80" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700">Evento</label>
                            <select v-model="form.evento_id" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                                <option :value="null">Sem evento associado</option>
                                <option v-for="evento in eventos" :key="evento.id" :value="evento.id">{{ evento.titulo }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">Título (linha grande no topo)</label>
                        <input v-model="form.titulo" required maxlength="60" class="mt-1 w-full rounded-md border-slate-300 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">Cabeçalho</label>
                        <textarea v-model="form.cabecalho" rows="3" class="mt-1 w-full rounded-md border-slate-300 font-mono text-sm"></textarea>
                        <p class="mt-1 text-xs text-slate-500">Uma linha por linha impressa. Datas, local, edição.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">Rodapé</label>
                        <textarea v-model="form.rodape" rows="3" class="mt-1 w-full rounded-md border-slate-300 font-mono text-sm"></textarea>
                        <p class="mt-1 text-xs text-slate-500">Se ficar vazio, imprime "Este documento nao serve de fatura".</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700">Instruções no talão individual</label>
                        <textarea v-model="form.instrucoes_individual" rows="2" class="mt-1 w-full rounded-md border-slate-300 font-mono text-sm"></textarea>
                        <p class="mt-1 text-xs text-slate-500">
                            Impresso em cada talão que o cliente leva. Ex.: "Entregar no balcão" ou
                            "Apresentar na partida da caminhada".
                        </p>
                    </div>

                    <label class="flex items-start gap-2 text-sm">
                        <input v-model="form.rodape_em_pedidos" type="checkbox" class="mt-0.5 rounded border-slate-300">
                        <span>
                            <strong>Rodapé também nos pedidos da cozinha e bar</strong>
                            <span class="block text-xs text-slate-500">Normalmente não — gasta papel em talões que ninguém leva.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-2 rounded-md bg-amber-50 p-3 text-sm">
                        <input v-model="form.prepago_apenas_individuais" type="checkbox" class="mt-0.5 rounded border-slate-300">
                        <span>
                            <strong>Evento com pré-pagamento — um talão por unidade</strong>
                            <span class="block text-xs text-slate-600">
                                Cada unidade sai no seu talão, cortado, com a senha e a secção onde se
                                levanta. Saem agrupados por secção e a conta vem no fim.
                            </span>
                            <span class="mt-2 block rounded bg-white p-2 font-mono text-[11px] leading-snug text-slate-700">
                                SENHA #42 · 1x Imperial · BEBIDAS ✂<br>
                                SENHA #42 · 1x Imperial · BEBIDAS ✂<br>
                                SENHA #42 · 1x Sumo Ananás · BEBIDAS ✂<br>
                                SENHA #42 · 1x Frango · FRANGO ✂<br>
                                CONTA · total, recebido e troco ✂
                            </span>
                        </span>
                    </label>

                    <label class="flex items-start gap-2 text-sm">
                        <input v-model="form.ativo" type="checkbox" class="mt-0.5 rounded border-slate-300">
                        <span>
                            <strong>Modelo ligado</strong>
                            <span class="block text-xs text-slate-500">Se desligares, os talões voltam a "ARDC Santana" e à nota legal.</span>
                        </span>
                    </label>

                    <div v-if="demasiadoLongas.length" class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                        <strong>Linhas com mais de {{ LARGURA }} caracteres</strong> — vão partir no papel:
                        <ul class="mt-1 list-disc pl-5 text-xs">
                            <li v-for="linha in demasiadoLongas" :key="linha">{{ linha }}</li>
                        </ul>
                    </div>

                    <div v-if="Object.keys(form.errors).length" class="rounded-md bg-red-50 p-3 text-sm text-red-700">
                        <div v-for="(erro, campo) in form.errors" :key="campo"><strong>{{ campo }}:</strong> {{ erro }}</div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white" :disabled="form.processing">
                            {{ form.processing ? 'A guardar...' : 'Guardar' }}
                        </button>
                        <select v-if="impressoras.length" v-model="impressoraId" class="rounded-md border-slate-300 text-sm">
                            <option v-for="impressora in impressoras" :key="impressora.id" :value="impressora.id">{{ impressora.nome }}</option>
                        </select>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 disabled:opacity-40"
                            :disabled="!impressoras.length"
                            @click="imprimirTeste"
                        >
                            Imprimir teste
                        </button>
                        <span v-if="!impressoras.length" class="text-xs text-slate-500">Não há impressoras ativas.</span>
                    </div>
                </form>
            </section>

            <!-- Pre-visualizacao -->
            <section>
                <div class="sticky top-4 space-y-4">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <h2 class="mb-2 text-xs font-black uppercase tracking-wide text-slate-500">Conta</h2>
                        <div class="bg-slate-50 p-3 font-mono text-[11px] leading-snug text-slate-800">
                            <div class="text-center text-sm font-black">{{ form.ativo ? (form.titulo || 'ARDC Santana') : 'ARDC Santana' }}</div>
                            <div class="text-center">CONTA</div>
                            <div>&nbsp;</div>
                            <template v-if="form.ativo">
                                <div v-for="linha in linhasCabecalho" :key="'c' + linha" class="text-center">{{ linha }}</div>
                            </template>
                            <div>Tipo: restaurante</div>
                            <div class="text-center font-bold">MESA 12</div>
                            <div>Hora: 20:15</div>
                            <div>------------------------------</div>
                            <div>2x Frango assado&nbsp;&nbsp;20,00 EUR</div>
                            <div>------------------------------</div>
                            <div>Total: 20,00 EUR</div>
                            <div>&nbsp;</div>
                            <div v-for="linha in (form.ativo && linhasRodape.length ? linhasRodape : ['Este documento nao serve de fatura'])" :key="'r' + linha">{{ linha }}</div>
                        </div>
                    </div>

                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <h2 class="mb-2 text-xs font-black uppercase tracking-wide text-slate-500">Talão individual (cliente)</h2>
                        <div class="bg-slate-50 p-3 font-mono text-[11px] leading-snug text-slate-800">
                            <div class="text-center text-sm font-black">{{ form.ativo ? (form.titulo || 'ARDC Santana') : 'ARDC Santana' }}</div>
                            <div class="text-center">SENHA</div>
                            <div>&nbsp;</div>
                            <template v-if="form.ativo">
                                <div v-for="linha in linhasCabecalho" :key="'i' + linha" class="text-center">{{ linha }}</div>
                            </template>
                            <div>Ponto: Bar</div>
                            <div>Hora: 20:15</div>
                            <div class="text-center text-sm font-bold">SENHA #42</div>
                            <div>------------------------------</div>
                            <div class="text-center text-sm font-bold">1x Caminhada</div>
                            <div class="text-center">Talao 1 de 2</div>
                            <div>------------------------------</div>
                            <template v-if="form.ativo">
                                <div v-for="linha in linhasInstrucoes" :key="'n' + linha" class="text-center">{{ linha }}</div>
                            </template>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Sai um destes por unidade, para entregar ao cliente.</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Produtos com talao individual -->
        <section class="mt-6 rounded-lg bg-white p-5 shadow-sm">
            <div class="mb-4">
                <h2 class="text-lg font-black">Produtos com talão individual</h2>
                <p class="mt-1 text-sm text-slate-500">
                    <strong>Só se aplica fora do pré-pagamento</strong> — no restaurante e na festa anual.
                    Marca os produtos que saem um talão por unidade, com o número da senha, para entregar
                    ao cliente. Os restantes saem todos juntos num talão só.
                </p>
                <p v-if="form.prepago_apenas_individuais" class="mt-2 rounded-md bg-amber-50 p-3 text-xs text-amber-800">
                    O modelo em uso está em pré-pagamento, por isso esta lista está a ser ignorada:
                    ali os talões saem por secção, com tudo o que o cliente comprou.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="categoria in categoriasComProdutos" :key="categoria.id">
                    <div class="mb-2 text-xs font-black uppercase tracking-wide text-slate-500">
                        {{ categoria.nome }}
                        <span class="font-normal normal-case text-slate-400">· {{ categoria.secao }}</span>
                    </div>
                    <label
                        v-for="produto in categoria.produtos"
                        :key="produto.id"
                        class="flex items-center gap-2 border-t border-slate-100 py-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            class="rounded border-slate-300"
                            :checked="individuais.includes(produto.id)"
                            @change="alternar(produto.id)"
                        >
                        <span>{{ produto.nome }}</span>
                    </label>
                </div>
            </div>

            <button class="mt-5 rounded-md bg-slate-900 px-4 py-2 text-sm font-bold text-white" @click="guardarProdutos">
                Guardar talões individuais
            </button>
        </section>
    </AppLayout>
</template>
