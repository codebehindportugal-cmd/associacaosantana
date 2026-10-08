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
const novoAberto = ref(false);
const previsaoTab = ref('conta');

// Etiqueta de estado de cada modelo (sempre com texto)
const estadoModelo = (modelo) => {
    if (modelo.em_uso) return { texto: 'Em uso', cls: 'bg-verde-claro text-verde-escuro' };
    if (!modelo.ativo) return { texto: 'Desligado', cls: 'bg-laranja-claro text-laranja-texto' };
    return { texto: 'Pronto', cls: 'bg-fundo text-suave' };
};

// Cor por secção do produto (sempre com o nome escrito ao lado)
const corSecao = (secao) => ({
    frango: 'text-secao-grelhados',
    grelhados: 'text-secao-grelhados',
    comida: 'text-secao-cozinha',
    cozinha: 'text-secao-cozinha',
    bebidas: 'text-secao-bar',
    bar: 'text-secao-bar',
    cafe: 'text-secao-bar',
    sobremesas: 'text-secao-sobremesas',
    acompanhamentos: 'text-secao-acompanhamentos',
}[secao] ?? 'text-secao-servico');
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
    onSuccess: () => { novoForm.reset(); novoAberto.value = false; },
});

// Erros das ações rápidas (usar/apagar modelo, imprimir teste, talões individuais)
const erroAcao = ref({ onde: '', msg: '' });
const opcoesAcao = (onde) => {
    erroAcao.value = { onde: '', msg: '' };
    return {
        preserveScroll: true,
        onError: (erros) => { erroAcao.value = { onde, msg: Object.values(erros).join(' ') }; },
    };
};

const usar = (modelo) => router.post(route('talao.usar', modelo.id), {}, opcoesAcao('modelos'));

const apagar = (modelo) => {
    if (confirm('Apagar o modelo "' + modelo.nome + '"?')) {
        router.post(route('talao.destroy', modelo.id), { _method: 'delete' }, opcoesAcao('modelos'));
    }
};

const imprimirTeste = () => router.post(
    route('talao.teste', selecionadoId.value),
    { impressora_id: impressoraId.value },
    opcoesAcao('teste'),
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
    opcoesAcao('produtos'),
);

const categoriasComProdutos = computed(() => props.categorias.filter((c) => (c.produtos || []).length));
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta">
            <div class="flex flex-col gap-1">
                <h1 class="text-[30px] font-extrabold leading-tight">Talão</h1>
                <p class="text-[15px] text-suave">Um modelo por evento. O que estiver em uso é o que sai em todos os talões.</p>
            </div>

            <!-- Modelos -->
            <section class="flex flex-col gap-2.5" aria-labelledby="t-modelos">
                <h2 id="t-modelos" class="text-[13px] font-extrabold uppercase tracking-[0.06em] text-suave-2">Modelos</h2>
                <div v-if="erroAcao.onde === 'modelos' && erroAcao.msg" role="alert" class="rounded-[10px] bg-perigo-claro p-3 font-semibold text-perigo-texto">{{ erroAcao.msg }}</div>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div v-for="modelo in modelos" :key="modelo.id" class="flex flex-col">
                        <button
                            type="button"
                            class="flex min-h-[84px] w-full flex-col gap-0.5 rounded-[14px] border-2 bg-white p-4 text-left transition"
                            :class="modelo.id === selecionadoId ? 'border-verde bg-verde-claro/40' : 'border-linha hover:border-linha-forte'"
                            :aria-pressed="modelo.id === selecionadoId"
                            @click="selecionadoId = modelo.id"
                        >
                            <span class="flex w-full items-start justify-between gap-2">
                                <strong class="text-[16px] font-extrabold leading-tight">{{ modelo.nome }}</strong>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-extrabold uppercase tracking-wide" :class="estadoModelo(modelo).cls">{{ estadoModelo(modelo).texto }}</span>
                            </span>
                            <span class="text-[13px] text-suave">{{ modelo.evento ? modelo.evento.titulo : 'Sem evento associado' }}</span>
                        </button>
                        <div v-if="modelo.id === selecionadoId && !modelo.em_uso" class="mt-1.5 flex gap-2">
                            <button type="button" class="btn-sec h-11 flex-1 text-sm text-verde" @click="usar(modelo)">Usar este</button>
                            <button type="button" class="btn-sec h-11 text-sm text-perigo hover:bg-perigo-claro" @click="apagar(modelo)">Apagar</button>
                        </div>
                    </div>

                    <div class="flex flex-col">
                        <button v-if="!novoAberto" type="button" class="flex min-h-[84px] items-center gap-2.5 rounded-[14px] border-2 border-dashed border-linha-forte px-4 text-[16px] font-extrabold text-verde hover:bg-white" @click="novoAberto = true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            Novo modelo
                        </button>
                        <form v-else class="flex flex-col gap-2 rounded-[14px] border-2 border-verde bg-white p-3" @submit.prevent="criar">
                            <label class="rotulo">Nome do novo modelo<input v-model="novoForm.nome" required class="campo" placeholder="Nome (ex.: Carvalhal Fest)"></label>
                            <label class="rotulo">Evento
                                <select :class="{ '!border-perigo': novoForm.errors.evento_id }" v-model="novoForm.evento_id" class="campo">
                                    <option :value="null">Sem evento associado</option>
                                    <option v-for="evento in eventos" :key="evento.id" :value="evento.id">{{ evento.titulo }}</option>
                                </select><span v-if="novoForm.errors.evento_id" class="block text-[13px] font-semibold text-perigo-texto">{{ novoForm.errors.evento_id }}</span>
                            </label>
                            <p class="text-xs text-suave">Se escolheres um evento, o título e as datas já vêm preenchidos.</p>
                            <div v-if="novoForm.errors.nome" class="text-xs font-semibold text-perigo">{{ novoForm.errors.nome }}</div>
                            <div class="flex gap-2">
                                <button class="btn-pri h-11 flex-1" :disabled="novoForm.processing">Criar</button>
                                <button type="button" class="btn-sec h-11" @click="novoAberto = false">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div v-if="!modelos.length" class="rounded-[10px] bg-white p-4 text-center text-sm font-bold text-suave-2">Ainda não há modelos.</div>
            </section>

            <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
                <!-- Editor -->
                <section class="cartao p-4 sm:p-5">
                    <div v-if="!selecionado" class="rounded-[10px] bg-fundo p-6 text-center text-sm font-bold text-suave-2">
                        Cria um modelo para começar.
                    </div>

                    <form v-else class="flex flex-col gap-4" @submit.prevent="guardar">
                        <h2 class="text-xl font-extrabold">A editar: {{ selecionado.nome }}</h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="rotulo">Nome do modelo<input v-model="form.nome" required maxlength="80" class="campo"></label>
                            <label class="rotulo">Evento
                                <select v-model="form.evento_id" class="campo">
                                    <option :value="null">Sem evento associado</option>
                                    <option v-for="evento in eventos" :key="evento.id" :value="evento.id">{{ evento.titulo }}</option>
                                </select>
                            </label>
                        </div>

                        <label class="rotulo">Título (linha grande no topo)
                            <input v-model="form.titulo" required maxlength="60" class="campo font-bold">
                        </label>

                        <label class="rotulo">Cabeçalho
                            <textarea v-model="form.cabecalho" rows="3" class="campo-area font-mono text-[15px]"></textarea>
                            <span class="ajuda">Uma linha por linha impressa. Datas, local, edição.</span>
                        </label>

                        <label class="rotulo">Rodapé
                            <textarea v-model="form.rodape" rows="3" class="campo-area font-mono text-[15px]"></textarea>
                            <span class="ajuda">Se ficar vazio, imprime "Este documento nao serve de fatura".</span>
                        </label>

                        <label class="rotulo">Instruções no talão individual
                            <textarea v-model="form.instrucoes_individual" rows="2" class="campo-area font-mono text-[15px]"></textarea>
                            <span class="ajuda">Impresso em cada talão que o cliente leva. Ex.: "Entregar no balcão" ou "Apresentar na partida da caminhada".</span>
                        </label>

                        <div v-if="demasiadoLongas.length" class="flex items-start gap-2.5 rounded-[10px] bg-laranja-claro p-3 text-sm text-laranja-texto">
                            <svg class="mt-0.5 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l10 18H2z" /><path d="M12 10v4M12 17.5v.5" /></svg>
                            <div>
                                <strong class="font-extrabold">Linhas com mais de {{ LARGURA }} caracteres</strong> — vão partir no papel:
                                <ul class="mt-1 list-disc pl-5 font-mono text-xs">
                                    <li v-for="linha in demasiadoLongas" :key="linha">{{ linha }}</li>
                                </ul>
                            </div>
                        </div>

                        <h3 class="mt-1 text-[13px] font-extrabold uppercase tracking-[0.06em] text-suave-2">Como funciona este evento</h3>

                        <label class="flex cursor-pointer items-start gap-3 rounded-[14px] border-2 p-4" :class="form.prepago_apenas_individuais ? 'border-laranja bg-laranja-claro' : 'border-linha bg-white'">
                            <input v-model="form.prepago_apenas_individuais" type="checkbox" role="switch" class="peer sr-only">
                            <span class="relative mt-0.5 h-7 w-12 shrink-0 rounded-full transition peer-focus-visible:ring-2 peer-focus-visible:ring-verde" :class="form.prepago_apenas_individuais ? 'bg-laranja' : 'bg-linha-forte'" aria-hidden="true">
                                <span class="absolute top-1 h-5 w-5 rounded-full bg-white transition-all" :class="form.prepago_apenas_individuais ? 'left-6' : 'left-1'"></span>
                            </span>
                            <span class="text-sm">
                                <strong class="block text-[15px] font-extrabold">Evento com pré-pagamento — um talão por unidade</strong>
                                <span class="block text-suave">
                                    O cliente paga tudo à cabeça e cada unidade sai no seu talão, cortado, com a senha e a
                                    secção onde se levanta. Saem agrupados por secção e a conta vem no fim. Tudo na mesma impressora, a do posto.
                                    Se o cliente pedir, no POS junta-se uma secção (comida, sobremesas ou bebidas) numa só folha.
                                </span>
                                <span class="mt-1 block text-suave">
                                    <strong class="text-tinta">Desligado (restaurante e café):</strong> o funcionamento do costume, incluindo a festa anual — o pedido
                                    é encaminhado para a impressora de cada secção (cozinha, frango, bebidas) e a conta sai no balcão. Configura-se em Impressoras, por secção.
                                </span>
                            </span>
                        </label>

                        <label class="opcao">
                            <input v-model="form.rodape_em_pedidos" type="checkbox" class="chk mt-0.5">
                            <span>
                                <strong class="block font-extrabold">Rodapé também nos pedidos da cozinha e bar</strong>
                                <span class="block text-suave">Normalmente não — gasta papel em talões que ninguém leva.</span>
                            </span>
                        </label>

                        <label class="opcao">
                            <input v-model="form.ativo" type="checkbox" class="chk mt-0.5">
                            <span>
                                <strong class="block font-extrabold">Modelo ligado</strong>
                                <span class="block text-suave">Se desligares, os talões voltam a "ARDC Santana" e à nota legal.</span>
                            </span>
                        </label>

                        <div v-if="Object.keys(form.errors).length" class="rounded-[10px] bg-perigo-claro p-3 text-sm text-perigo-texto">
                            <div v-for="(erro, campo) in form.errors" :key="campo"><strong>{{ campo }}:</strong> {{ erro }}</div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5 border-t border-linha-fraca pt-4">
                            <button class="btn-pri h-[52px] px-6 text-base" :disabled="form.processing">{{ form.processing ? 'A guardar...' : 'Guardar' }}</button>
                            <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
                                <label v-if="impressoras.length" class="flex items-center gap-2 text-sm font-semibold text-suave">
                                    Testar em
                                    <select v-model="impressoraId" class="h-11 rounded-[10px] border border-linha-forte bg-white px-3 text-[15px] text-tinta focus:border-verde focus:ring-verde">
                                        <option v-for="impressora in impressoras" :key="impressora.id" :value="impressora.id">{{ impressora.nome }}</option>
                                    </select>
                                </label>
                                <button type="button" class="btn-sec h-11 disabled:opacity-40" :disabled="!impressoras.length" @click="imprimirTeste">Imprimir teste</button>
                                <div v-if="erroAcao.onde === 'teste' && erroAcao.msg" role="alert" class="rounded-[10px] bg-perigo-claro p-3 font-semibold text-perigo-texto">{{ erroAcao.msg }}</div>
                                <span v-if="!impressoras.length" class="text-xs text-suave">Não há impressoras ativas.</span>
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Pré-visualização -->
                <aside class="flex flex-col gap-3 lg:sticky lg:top-4">
                    <div class="flex items-center justify-between gap-2 rounded-[14px] bg-linha-fraca p-1 pl-3">
                        <h2 class="text-[13px] font-extrabold uppercase tracking-[0.06em] text-suave-2">Pré-visualização</h2>
                        <div class="flex gap-1" role="tablist">
                            <button type="button" role="tab" class="h-10 rounded-[10px] px-3 text-[13px] font-bold" :class="previsaoTab === 'conta' ? 'bg-white text-tinta shadow-sm' : 'text-suave'" :aria-selected="previsaoTab === 'conta'" @click="previsaoTab = 'conta'">Conta</button>
                            <button type="button" role="tab" class="h-10 rounded-[10px] px-3 text-[13px] font-bold" :class="previsaoTab === 'individual' ? 'bg-white text-tinta shadow-sm' : 'text-suave'" :aria-selected="previsaoTab === 'individual'" @click="previsaoTab = 'individual'">Talão individual</button>
                        </div>
                    </div>

                    <div class="rounded-[14px] bg-linha-fraca p-3">
                        <div v-if="previsaoTab === 'conta'" class="talao">
                            <div class="text-center text-sm font-bold">{{ form.ativo ? (form.titulo || 'ARDC Santana') : 'ARDC Santana' }}</div>
                            <div class="text-center">CONTA</div>
                            <div>&nbsp;</div>
                            <template v-if="form.ativo">
                                <div v-for="linha in linhasCabecalho" :key="'c' + linha" class="text-center">{{ linha }}</div>
                            </template>
                            <div>Tipo: restaurante</div>
                            <div class="text-center font-bold">MESA 12</div>
                            <div>Hora: 20:15</div>
                            <div>------------------------------</div>
                            <div class="flex justify-between gap-2"><span>2x Frango assado</span><span>20,00 EUR</span></div>
                            <div>------------------------------</div>
                            <div class="font-bold">Total: 20,00 EUR</div>
                            <div>&nbsp;</div>
                            <div v-for="linha in (form.ativo && linhasRodape.length ? linhasRodape : ['Este documento nao serve de fatura'])" :key="'r' + linha">{{ linha }}</div>
                            <div class="mt-2 border-t border-dashed border-suave-2"></div>
                        </div>
                        <div v-else class="talao">
                            <div class="text-center text-sm font-bold">{{ form.ativo ? (form.titulo || 'ARDC Santana') : 'ARDC Santana' }}</div>
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
                            <div class="mt-2 border-t border-dashed border-suave-2"></div>
                        </div>
                    </div>
                    <p class="text-[13px] text-suave">{{ previsaoTab === 'conta' ? 'A conta sai no fim, com total, recebido e troco.' : 'Sai um destes por unidade, para entregar ao cliente.' }}</p>

                    <div class="cartao p-4">
                        <p class="text-[13px] font-extrabold">No pré-pagamento sai assim</p>
                        <p class="mt-1.5 font-mono text-[11px] leading-relaxed text-suave">
                            SENHA #42 · 1x Imperial · BEBIDAS ✂<br>
                            SENHA #42 · 1x Imperial · BEBIDAS ✂<br>
                            SENHA #42 · 1x Sumo Ananás · BEBIDAS ✂<br>
                            SENHA #42 · 1x Frango · FRANGO ✂<br>
                            CONTA · total, recebido e troco ✂
                        </p>
                    </div>
                </aside>
            </div>

            <!-- Produtos com talão individual -->
            <section class="cartao flex flex-col gap-4 p-4 sm:p-5">
                <div>
                    <h2 class="text-xl font-extrabold">Produtos com talão individual</h2>
                    <p class="mt-1 max-w-3xl text-sm text-suave">
                        <strong class="text-tinta">Só se aplica fora do pré-pagamento</strong> — no restaurante e na festa anual.
                        Marca os produtos que saem um talão por unidade, com o número da senha, para entregar
                        ao cliente. Os restantes saem todos juntos num talão só.
                    </p>
                    <p v-if="form.prepago_apenas_individuais" class="mt-2 rounded-[10px] bg-laranja-claro p-3 text-[13px] text-laranja-texto">
                        O modelo em uso está em pré-pagamento, por isso esta lista está a ser ignorada:
                        ali os talões saem por secção, com tudo o que o cliente comprou.
                    </p>
                </div>

                <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div v-for="categoria in categoriasComProdutos" :key="categoria.id">
                        <div class="mb-1 text-[13px] font-extrabold uppercase tracking-[0.06em]" :class="corSecao(categoria.secao)">
                            {{ categoria.nome }}
                            <span class="font-normal normal-case tracking-normal text-suave-2">· {{ categoria.secao }}</span>
                        </div>
                        <label v-for="produto in categoria.produtos" :key="produto.id" class="flex min-h-11 cursor-pointer items-center gap-3 border-t border-linha-fraca py-2 text-[15px]">
                            <input type="checkbox" class="chk" :checked="individuais.includes(produto.id)" @change="alternar(produto.id)">
                            <span :class="individuais.includes(produto.id) ? 'font-bold' : ''">{{ produto.nome }}</span>
                        </label>
                    </div>
                </div>

                <div v-if="erroAcao.onde === 'produtos' && erroAcao.msg" role="alert" class="rounded-[10px] bg-perigo-claro p-3 font-semibold text-perigo-texto">{{ erroAcao.msg }}</div>

                <button class="btn-pri h-12 w-fit" @click="guardarProdutos">Guardar talões individuais</button>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.cartao { @apply rounded-[14px] border border-linha bg-white; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-bold text-tinta; }
.ajuda { @apply text-[13px] font-normal text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base font-normal text-tinta focus:border-verde focus:ring-verde; }
.campo-area { @apply w-full rounded-[10px] border border-linha-forte bg-white px-3.5 py-3 font-normal text-tinta focus:border-verde focus:ring-verde; }
.opcao { @apply flex cursor-pointer items-start gap-3 rounded-[14px] border border-linha bg-white p-4 text-sm; }
.chk { @apply h-5 w-5 shrink-0 rounded border-linha-forte text-verde focus:ring-verde; }
.talao { @apply mx-auto w-full max-w-[302px] bg-white px-3 py-4 font-mono text-[11px] leading-[1.55] text-tinta shadow-[0_1px_4px_rgba(22,32,28,0.15)]; }
</style>
