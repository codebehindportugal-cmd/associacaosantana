<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    impressoras: {
        type: Array,
        required: true,
    },
    secoes: {
        type: Object,
        required: true,
    },
    terminais: {
        type: Array,
        default: () => [],
    },
    tiposTerminal: {
        type: Array,
        default: () => [],
    },
    tiposImpressora: {
        type: Object,
        default: () => ({}),
    },
    agente: {
        type: Object,
        default: () => ({}),
    },
})

const showModal = ref(false)
const isEditing = ref(false)
const selectedImpressora = ref(null)
const impressoras = computed(() => props.impressoras ?? [])
const secoes = computed(() => props.secoes ?? {})

const form = useForm({
    nome: '',
    secao: '',
    tipo: 'usb',
    host: '',
    porta: 9100,
    dispositivo: '',
    agente: '',
    ativa: true,
})

const modalTitle = computed(() => (isEditing.value ? 'Editar Impressora' : 'Nova Impressora'))

function openCreateModal() {
    isEditing.value = false
    form.reset()
    form.clearErrors()
    showModal.value = true
}

function openEditModal(impressora) {
    isEditing.value = true
    selectedImpressora.value = impressora
    form.nome = impressora.nome
    form.secao = impressora.secao || ''
    form.tipo = impressora.tipo || 'rede'
    form.host = impressora.host || ''
    form.porta = impressora.porta || 9100
    form.dispositivo = impressora.dispositivo || ''
    form.agente = impressora.agente || ''
    form.ativa = impressora.ativa
    form.clearErrors()
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.reset()
}

function submit() {
    if (isEditing.value) {
        form.patch(route('impressoras.update', selectedImpressora.value.id), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.post(route('impressoras.store'), {
            onSuccess: () => closeModal(),
        })
    }
}

function deleteImpressora(impressora) {
    if (confirm(`Tem certeza que deseja remover a impressora "${impressora.nome}"?`)) {
        useForm({}).delete(route('impressoras.destroy', impressora.id))
    }
}

const getSectionName = (secao) => {
    if (!secao) return '—'
    return secoes.value[secao] || secao
}

const destino = (impressora) => {
    if (impressora.tipo === 'usb') return `USB · ${impressora.dispositivo || 'sem dispositivo'}`
    if (impressora.tipo === 'webusb') return 'USB pelo browser (WebUSB)'
    if (impressora.tipo === 'navegador') return 'Impressão normal do browser'

    return `${impressora.host || '—'}:${impressora.porta || 9100}`
}

// Cada posto tem a sua impressora e nao ha mais nenhuma: um posto sem
// impressora manda o talao para a primeira da seccao, ou seja, para o posto
// do lado. E dois postos na mesma impressora e quase sempre engano.
const postosSemImpressora = computed(
    () => (props.terminais ?? []).filter((t) => t.ativo && !t.impressora_id),
)

const impressorasRepetidas = computed(() => {
    const contagem = new Map()

    for (const terminal of props.terminais ?? []) {
        if (!terminal.ativo || !terminal.impressora_id) continue
        contagem.set(terminal.impressora_id, (contagem.get(terminal.impressora_id) ?? 0) + 1)
    }

    return (props.impressoras ?? []).filter((i) => (contagem.get(i.id) ?? 0) > 1)
})

const comoImprime = (terminal) => {
    const impressora = (props.impressoras ?? []).find((i) => i.id === terminal.impressora_id)

    if (!impressora) return 'pela secção, via agente'
    if (impressora.tipo === 'webusb') return 'browser (WebUSB)'
    if (impressora.tipo === 'navegador') return 'browser (HTML)'

    return 'agente local'
}

const terminais = computed(() => props.terminais ?? [])

const erroPostos = ref('')
function guardarImpressoraDoPosto(terminal, impressoraId) {
    erroPostos.value = ''
    useForm({ impressora_id: impressoraId || null })
        .post(route('terminais.impressora', terminal.id), {
            preserveScroll: true,
            onError: (erros) => { erroPostos.value = Object.values(erros).join(' ') },
        })
}

const tipos = computed(() => props.tiposTerminal ?? [])
const tiposImpressora = computed(() => props.tiposImpressora ?? {})

const postoEmEdicao = ref(null)

const postoForm = useForm({
    nome: '',
    tipo: 'bar',
    localizacao: '',
    pin: '',
    impressora_id: '',
    impressao_navegador: false,
    ativo: true,
})

function novoPosto() {
    postoEmEdicao.value = null
    postoForm.reset()
    postoForm.clearErrors()
    postoForm.tipo = 'bar'
    postoForm.impressao_navegador = false
}

function editarPosto(terminal) {
    postoEmEdicao.value = terminal
    postoForm.clearErrors()
    postoForm.nome = terminal.nome
    postoForm.tipo = terminal.tipo
    postoForm.localizacao = terminal.localizacao || ''
    postoForm.pin = ''
    postoForm.impressora_id = terminal.impressora_id || ''
    postoForm.impressao_navegador = Boolean(terminal.impressao_navegador)
    postoForm.ativo = terminal.ativo
}

function guardarPosto() {
    if (postoEmEdicao.value) {
        postoForm
            .transform((dados) => ({ ...dados, _method: 'patch' }))
            .post(route('terminais.update', postoEmEdicao.value.id), {
                preserveScroll: true,
                onSuccess: () => novoPosto(),
            })

        return
    }

    postoForm.post(route('terminais.store'), {
        preserveScroll: true,
        onSuccess: () => novoPosto(),
    })
}

function desativarPosto(terminal) {
    if (confirm(`Desativar o posto "${terminal.nome}"? Deixa de aparecer no login do POS.`)) {
        erroPostos.value = ''
        useForm({ _method: 'delete' }).post(route('terminais.destroy', terminal.id), {
            preserveScroll: true,
            onError: (erros) => { erroPostos.value = Object.values(erros).join(' ') },
        })
    }
}

// ── Estado da fila (rota statusJobs) — atualiza sozinho a cada 20s ──────────
const fila = ref(null)
let filaTimer = null
async function carregarFila() {
    try {
        const { data } = await axios.get(route('impressoras.status-jobs'))
        fila.value = data
    } catch { /* sem estado da fila */ }
}
onMounted(() => {
    carregarFila()
    filaTimer = setInterval(carregarFila, 20000)
})
onBeforeUnmount(() => clearInterval(filaTimer))

// ── Estado por impressora (calculado no servidor: estado_impressao) ─────────
const agora = ref(Date.now())
const relogio = setInterval(() => { agora.value = Date.now() }, 30000)
onBeforeUnmount(() => clearInterval(relogio))

const haQuanto = (iso) => {
    if (!iso) return null
    const min = Math.max(0, Math.round((agora.value - new Date(iso).getTime()) / 60000))
    if (min < 1) return 'agora mesmo'
    if (min < 60) return `há ${min} min`
    const h = Math.round(min / 60)
    if (h < 24) return `há ${h} h`
    const d = Math.round(h / 24)
    return d === 1 ? 'há 1 dia' : `há ${d} dias`
}

const estadoVisual = {
    ok: { pill: 'bg-verde-claro text-verde-escuro', ponto: 'bg-verde-ok' },
    erro: { pill: 'bg-perigo-claro text-perigo-texto', ponto: 'bg-perigo' },
    atencao: { pill: 'bg-laranja-claro text-laranja-texto', ponto: 'bg-laranja' },
    sem_atividade: { pill: 'bg-fundo text-suave', ponto: 'bg-suave-2' },
    browser: { pill: 'bg-fundo text-suave', ponto: 'bg-azul' },
    inativa: { pill: 'bg-fundo text-suave', ponto: 'bg-suave-2/50' },
}
const estadoDe = (impressora) => impressora.estado_impressao
    ?? (impressora.ativa ? { nivel: 'sem_atividade', texto: 'Ativa' } : { nivel: 'inativa', texto: 'Inativa' })

// Cor da barra no topo do cartão, pela secção (sempre com o nome escrito)
const corSecao = {
    frango: 'border-t-secao-grelhados text-secao-grelhados',
    comida: 'border-t-secao-cozinha text-secao-cozinha',
    cozinha: 'border-t-secao-cozinha text-secao-cozinha',
    bebidas: 'border-t-secao-bar text-secao-bar',
    bar: 'border-t-secao-bar text-secao-bar',
    cafe: 'border-t-secao-bar text-secao-bar',
    sobremesas: 'border-t-secao-sobremesas text-secao-sobremesas',
    acompanhamentos: 'border-t-secao-acompanhamentos text-secao-acompanhamentos',
}
const classeSecao = (secao) => corSecao[secao] ?? 'border-t-secao-servico text-secao-servico'

const ligacaoCurta = {
    rede: 'Rede (Raspberry)',
    usb: 'USB no Raspberry',
    webusb: 'USB pelo browser',
    navegador: 'Browser (não corta)',
}

// Token do agente (Raspberry / Windows)
const verToken = ref(false)
const copiado = ref('')
const tokenForm = useForm({ token: '' })
const tokenAgente = computed(() => props.agente?.token ?? '')
const tokenVisivel = computed(() => {
    const t = tokenAgente.value
    if (!t) return 'Sem token definido'
    return verToken.value ? t : t.slice(0, 4) + '••••••••••••' + t.slice(-4)
})
const comandoPi = computed(() =>
    `sed -i 's|^PRINT_AGENT_TOKEN=.*|PRINT_AGENT_TOKEN=${tokenAgente.value}|' ~/printer-agent/.env && sudo systemctl restart printer-agent`,
)
const avisoAgente = computed(() => {
    const ok = props.agente?.ultimo_ok_at ? new Date(props.agente.ultimo_ok_at) : null
    const recusado = props.agente?.ultimo_401_at ? new Date(props.agente.ultimo_401_at) : null
    if (recusado && (!ok || recusado > ok) && Date.now() - recusado < 5 * 60 * 1000) {
        return { nivel: 'erro', texto: 'Há um agente a tentar ligar com o token errado. Atualiza o .env dele com o comando abaixo.' }
    }
    if (ok && Date.now() - ok < 2 * 60 * 1000) {
        return { nivel: 'ok', texto: 'Agente ligado — último contacto ' + ok.toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' }) + '.' }
    }
    if (ok) {
        return { nivel: 'aviso', texto: 'Sem contacto do agente desde ' + ok.toLocaleString('pt-PT', { dateStyle: 'short', timeStyle: 'short' }) + '.' }
    }
    return { nivel: 'aviso', texto: 'Ainda não houve contacto de nenhum agente.' }
})
async function copiar(texto, qual) {
    try {
        await navigator.clipboard.writeText(texto)
        copiado.value = qual
        setTimeout(() => (copiado.value = ''), 2000)
    } catch {
        window.prompt('Copia daqui:', texto)
    }
}
function guardarToken() {
    tokenForm.post(route('impressoras.token-agente'), {
        preserveScroll: true,
        onSuccess: () => tokenForm.reset(),
    })
}
function gerarNovoToken() {
    if (!window.confirm('Gerar um token novo? Os agentes com o token antigo deixam de imprimir até atualizares o .env deles.')) return
    useForm({}).post(route('impressoras.token-agente'), { preserveScroll: true })
}

const retentarForm = useForm({})
function retentarFalhados() {
    retentarForm.post(route('impressoras.retentar-falhados'), { onFinish: () => carregarFila() })
}
</script>

<template>
    <AppLayout title="Impressoras">
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="text-[30px] font-extrabold leading-tight">Impressoras</h1>
                    <p class="text-[15px] text-suave">Para onde vai cada talão: impressoras por secção e a impressora de cada posto.</p>
                </div>
                <div class="flex flex-wrap gap-2.5">
                    <a :href="route('impressoras.teste-usb')" class="btn-sec h-12">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v14M9 6l3-3 3 3M8 11l-2 2v3M16 9l2 2v3M12 17a2 2 0 1 0 0 4 2 2 0 0 0 0-4z" /></svg>
                        Teste USB (WebUSB)
                    </a>
                    <button type="button" class="btn-pri h-12" @click="openCreateModal">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                        Nova impressora
                    </button>
                </div>
            </div>

            <!-- Estado da fila de impressão -->
            <section aria-label="Estado da fila de impressão" class="flex flex-col gap-2">
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="cartao p-4">
                        <div class="text-[13px] font-semibold text-suave">Impressos hoje</div>
                        <div class="mt-1 text-[28px] font-extrabold leading-none">{{ fila ? fila.impresso : '—' }}</div>
                    </div>
                    <div class="cartao p-4">
                        <div class="text-[13px] font-semibold text-suave">Na fila</div>
                        <div class="mt-1 text-[28px] font-extrabold leading-none">{{ fila ? fila.pendente : '—' }}</div>
                    </div>
                    <div class="cartao p-4">
                        <div class="text-[13px] font-semibold text-suave">A imprimir</div>
                        <div class="mt-1 text-[28px] font-extrabold leading-none">{{ fila ? fila.processando : '—' }}</div>
                    </div>
                    <div class="flex items-center justify-between gap-2 rounded-[14px] border p-4" :class="fila?.falhado ? 'border-perigo-claro bg-perigo-claro' : 'border-linha bg-white'">
                        <div>
                            <div class="text-[13px] font-semibold" :class="fila?.falhado ? 'text-perigo-texto' : 'text-suave'">Falhados</div>
                            <div class="mt-1 text-[28px] font-extrabold leading-none" :class="fila?.falhado ? 'text-perigo' : ''">{{ fila ? fila.falhado : '—' }}</div>
                        </div>
                        <button
                            type="button"
                            :disabled="retentarForm.processing"
                            class="h-11 shrink-0 rounded-[10px] bg-perigo px-4 text-sm font-bold text-white transition hover:opacity-90 disabled:opacity-60"
                            @click="retentarFalhados"
                        >
                            {{ retentarForm.processing ? 'A processar...' : 'Retentar' }}
                        </button>
                    </div>
                </div>
                <p class="text-[13px] text-suave">Se o pedido não imprimiu tudo, "Retentar" coloca os jobs falhados de volta na fila.</p>
            </section>

            <div v-if="postosSemImpressora.length" class="flex items-start gap-3 rounded-[14px] border border-perigo-claro bg-perigo-claro p-4 text-sm text-perigo-texto">
                <svg class="mt-0.5 shrink-0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l10 18H2z" /><path d="M12 10v4M12 17.5v.5" /></svg>
                <p><strong class="font-extrabold">Sem impressora definida: {{ postosSemImpressora.map((t) => t.nome).join(', ') }}.</strong> Os talões destes postos vão sair na primeira impressora da secção — na prática, no posto do lado.</p>
            </div>

            <div v-if="impressorasRepetidas.length" class="flex items-start gap-3 rounded-[14px] border border-laranja-claro bg-laranja-claro p-4 text-sm text-laranja-texto">
                <svg class="mt-0.5 shrink-0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16v.5" /></svg>
                <p><strong class="font-extrabold">Impressora partilhada por mais do que um posto: {{ impressorasRepetidas.map((i) => i.nome).join(', ') }}.</strong> Se cada posto tem a sua, isto é engano.</p>
            </div>

            <!-- Impressoras por secção -->
            <div class="flex flex-wrap items-end justify-between gap-2">
                <h2 class="text-xl font-extrabold">Impressoras por secção</h2>
                <p class="text-[13px] text-suave">Total de impressoras: <strong class="text-tinta">{{ impressoras.length }}</strong></p>
            </div>

            <div v-if="impressoras.length === 0" class="cartao p-10 text-center text-suave-2">Nenhuma impressora configurada ainda.</div>

            <div class="grid gap-3.5 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="impressora in impressoras" :key="impressora.id" class="flex flex-col gap-3 rounded-[14px] border border-t-4 border-linha bg-white p-4" :class="[classeSecao(impressora.secao), impressora.ativa ? '' : 'opacity-80']">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-xs font-extrabold uppercase tracking-[0.08em]">{{ getSectionName(impressora.secao) === '—' ? 'Sem secção' : getSectionName(impressora.secao) }}</p>
                            <h3 class="text-lg font-extrabold leading-tight text-tinta">{{ impressora.nome }}</h3>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-extrabold" :class="estadoVisual[estadoDe(impressora).nivel]?.pill">
                            <span class="h-2 w-2 rounded-full" :class="estadoVisual[estadoDe(impressora).nivel]?.ponto"></span>
                            {{ estadoDe(impressora).texto }}
                        </span>
                    </div>

                    <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 text-sm text-tinta">
                        <dt class="text-suave">Ligação</dt>
                        <dd class="font-semibold">{{ ligacaoCurta[impressora.tipo] ?? impressora.tipo }}</dd>
                        <dt class="text-suave">Destino</dt>
                        <dd class="break-words font-mono text-[13px] font-semibold">{{ destino(impressora) }}</dd>
                        <dt class="text-suave">Posto (agente)</dt>
                        <dd class="font-semibold">{{ impressora.agente || '—' }}</dd>
                    </dl>

                    <div v-if="impressora.estado_impressao && impressora.ativa && impressora.estado_impressao.nivel !== 'browser'" class="rounded-[10px] bg-fundo px-3 py-2 text-[13px] text-suave">
                        <p v-if="impressora.estado_impressao.ultimo_ok_at">Último talão impresso <strong class="text-tinta">{{ haQuanto(impressora.estado_impressao.ultimo_ok_at) }}</strong></p>
                        <p v-else>Ainda sem talões confirmados pelo agente.</p>
                        <p v-if="impressora.estado_impressao.pendentes">{{ impressora.estado_impressao.pendentes }} na fila</p>
                        <p v-if="impressora.estado_impressao.falhados" class="text-perigo-texto">
                            Último erro {{ haQuanto(impressora.estado_impressao.ultimo_erro_at) }}<span v-if="impressora.estado_impressao.ultimo_erro">: {{ impressora.estado_impressao.ultimo_erro }}</span>
                        </p>
                    </div>

                    <div class="mt-auto flex gap-2 border-t border-linha-fraca pt-3">
                        <button type="button" class="btn-sec h-11 flex-1" @click="openEditModal(impressora)">Editar</button>
                        <button type="button" class="grid h-11 w-11 shrink-0 place-items-center rounded-[10px] border border-linha-forte bg-white text-perigo hover:bg-perigo-claro" :aria-label="`Remover a impressora ${impressora.nome}`" title="Remover" @click="deleteImpressora(impressora)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13" /></svg>
                        </button>
                    </div>
                </article>
            </div>

            <!-- Postos POS -->
            <div class="mt-3 flex flex-col gap-1">
                <h2 class="text-xl font-extrabold">Postos POS</h2>
                <p class="max-w-3xl text-sm text-suave">
                    Cada posto tem o seu PIN e a sua impressora. Como qualquer posto pode vender
                    qualquer produto, é a impressora do posto que manda — sem ela definida, o talão
                    sai na primeira impressora da secção, que com vários pontos é quase sempre a errada.
                    <strong class="text-tinta">Como</strong> cada posto imprime vem da impressora que escolheres — agente, WebUSB
                    ou impressão do browser — e não precisa de programação nenhuma.
                </p>
            </div>

            <div class="cartao overflow-hidden">
                <div v-if="!terminais.length" class="p-6 text-center text-sm text-suave-2">Ainda não há postos configurados.</div>
                <template v-else>
                    <div class="hidden grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)_minmax(0,0.8fr)_auto] gap-3 border-b border-linha-fraca px-5 py-3 text-[13px] font-semibold text-suave lg:grid">
                        <span>Posto</span><span>Impressora deste posto</span><span>Como imprime</span><span class="text-right">Ações</span>
                    </div>
                    <ul class="divide-y divide-linha-fraca">
                        <li v-for="terminal in terminais" :key="terminal.id" class="grid items-center gap-3 px-4 py-3 sm:px-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)_minmax(0,0.8fr)_auto]" :class="terminal.ativo && !terminal.impressora_id ? 'bg-perigo-claro/40' : ''">
                            <div class="min-w-0">
                                <div class="font-bold">
                                    {{ terminal.nome }}
                                    <span v-if="terminal.ativo && !terminal.impressora_id" class="ml-1 text-xs font-bold text-perigo">Sem impressora</span>
                                    <span v-if="!terminal.ativo" class="ml-1 text-xs font-bold text-laranja-texto">inativo</span>
                                </div>
                                <div class="text-[13px] text-suave">{{ terminal.tipo }}<span v-if="terminal.localizacao"> · {{ terminal.localizacao }}</span></div>
                            </div>
                            <label class="block min-w-0">
                                <span class="sr-only">Impressora do posto {{ terminal.nome }}</span>
                                <select
                                    class="h-12 w-full rounded-[10px] border bg-white px-3.5 text-[15px] font-semibold text-tinta focus:border-verde focus:ring-verde"
                                    :class="terminal.ativo && !terminal.impressora_id ? 'border-perigo' : 'border-linha-forte'"
                                    :value="terminal.impressora_id || ''"
                                    @change="guardarImpressoraDoPosto(terminal, $event.target.value)"
                                >
                                    <option value="">Pela secção (comportamento antigo)</option>
                                    <option v-for="impressora in impressoras" :key="impressora.id" :value="impressora.id">{{ impressora.nome }}</option>
                                </select>
                            </label>
                            <div><span class="rounded-full bg-fundo px-2.5 py-1 text-xs font-bold text-suave">{{ comoImprime(terminal) }}</span></div>
                            <div class="flex gap-2 lg:justify-end">
                                <button type="button" class="btn-sec h-11 flex-1 lg:flex-none" @click="editarPosto(terminal)">Editar</button>
                                <button v-if="terminal.ativo" type="button" class="btn-sec h-11 flex-1 text-perigo hover:bg-perigo-claro lg:flex-none" @click="desativarPosto(terminal)">Desativar</button>
                            </div>
                        </li>
                    </ul>
                </template>
            </div>

            <div v-if="erroPostos" role="alert" class="rounded-[10px] bg-perigo-claro p-3 font-semibold text-perigo-texto">{{ erroPostos }}</div>

            <form class="cartao flex flex-col gap-3 p-4 sm:p-5" :class="postoEmEdicao ? 'border-verde' : ''" @submit.prevent="guardarPosto">
                <AvisoErros :errors="postoForm.errors" :excluir="['impressora_id', 'localizacao', 'nome', 'pin', 'tipo']" />
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h3 class="text-lg font-extrabold">{{ postoEmEdicao ? `Editar posto: ${postoEmEdicao.nome}` : 'Novo posto' }}</h3>
                        <p class="text-[13px] text-suave">Podes criar aqui um posto novo a meio do evento — um telemóvel a ajudar num pico, por exemplo.</p>
                    </div>
                    <button v-if="postoEmEdicao" type="button" class="btn-sec h-11 text-sm" @click="novoPosto">Cancelar edição</button>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <label class="rotulo" for="posto_nome">Nome *
                        <input id="posto_nome" v-model="postoForm.nome" type="text" class="campo" placeholder="ex: Pré-pagamento 3">
                        <InputError :message="postoForm.errors.nome" />
                    </label>
                    <label class="rotulo" for="posto_tipo">Tipo *
                        <select id="posto_tipo" v-model="postoForm.tipo" class="campo">
                            <option v-for="tipo in tipos" :key="tipo" :value="tipo">{{ tipo }}</option>
                        </select>
                        <InputError :message="postoForm.errors.tipo" />
                    </label>
                    <label class="rotulo" for="posto_local">Localização
                        <input id="posto_local" v-model="postoForm.localizacao" type="text" class="campo" placeholder="ex: Tenda">
                        <InputError :message="postoForm.errors.localizacao" />
                    </label>
                    <label class="rotulo" for="posto_pin">{{ postoEmEdicao ? 'PIN (em branco: mantém)' : 'PIN *' }}
                        <input id="posto_pin" v-model="postoForm.pin" type="text" inputmode="numeric" class="campo" placeholder="4 a 12 dígitos">
                        <InputError :message="postoForm.errors.pin" />
                    </label>
                    <label class="rotulo" for="posto_impressora">Impressora
                        <select id="posto_impressora" v-model="postoForm.impressora_id" class="campo">
                            <option value="">Pela secção</option>
                            <option v-for="impressora in impressoras" :key="impressora.id" :value="impressora.id">{{ impressora.nome }}</option>
                        </select>
                        <InputError :message="postoForm.errors.impressora_id" />
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex h-11 cursor-pointer items-center gap-3 text-[15px] font-bold">
                        <input v-model="postoForm.ativo" type="checkbox" class="h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde" />
                        Posto ativo
                    </label>
                    <button type="submit" class="btn-pri h-12 px-6 disabled:opacity-60" :disabled="postoForm.processing">
                        {{ postoEmEdicao ? 'Guardar posto' : 'Criar posto' }}
                    </button>
                </div>
            </form>

            <!-- Agente de impressão -->
            <section class="cartao flex flex-col gap-5 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0 max-w-3xl">
                        <h3 class="text-lg font-extrabold">Agente de impressão</h3>
                        <p class="mt-1 text-sm text-suave">
                            Corre num Raspberry Pi na rede das impressoras (ou num PC Windows). Vai buscar os talões
                            a este site com o token abaixo — tem de ser igual ao <code class="font-mono">PRINT_AGENT_TOKEN</code> do <code class="font-mono">.env</code> dele.
                        </p>
                    </div>
                    <a :href="route('impressoras.download-agente')" class="inline-flex h-12 shrink-0 items-center gap-2 rounded-[10px] bg-tinta px-5 text-[15px] font-bold text-white transition hover:bg-escuro-2">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v12M7 11l5 5 5-5M4 20h16" /></svg>
                        Download setup-pi.sh
                    </a>
                </div>

                <div
                    class="rounded-[10px] px-4 py-3 text-sm font-semibold"
                    :class="{
                        'bg-red-50 text-red-800': avisoAgente.nivel === 'erro',
                        'bg-emerald-50 text-emerald-800': avisoAgente.nivel === 'ok',
                        'bg-amber-50 text-amber-900': avisoAgente.nivel === 'aviso',
                    }"
                    role="status"
                >
                    {{ avisoAgente.texto }}
                </div>

                <div class="flex flex-col gap-2">
                    <span class="text-sm font-bold">Token do agente</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <code class="min-w-0 flex-1 break-all rounded-md bg-fundo px-3 py-2.5 font-mono text-sm">{{ tokenVisivel }}</code>
                        <button v-if="tokenAgente" type="button" class="btn-sec h-11" @click="verToken = !verToken">{{ verToken ? 'Esconder' : 'Mostrar' }}</button>
                        <button v-if="tokenAgente" type="button" class="btn-sec h-11" @click="copiar(tokenAgente, 'token')">{{ copiado === 'token' ? 'Copiado ✓' : 'Copiar' }}</button>
                        <button type="button" class="btn-sec h-11" @click="gerarNovoToken">Gerar novo</button>
                    </div>
                    <p v-if="agente?.origem === 'servidor'" class="text-xs text-suave">
                        Este token vem do <code class="font-mono">.env</code> do servidor. Ao gerar ou definir um aqui, passa a valer o do site.
                    </p>
                </div>

                <form class="flex flex-col gap-2" @submit.prevent="guardarToken">
                    <label for="token-agente" class="text-sm font-bold">Usar um token que já tens</label>
                    <p class="text-xs text-suave">Ex.: o que está no <code class="font-mono">.env</code> do Raspberry (<code class="font-mono">grep TOKEN ~/printer-agent/.env</code>). Assim não tens de mexer no Raspberry.</p>
                    <div class="flex flex-wrap gap-2">
                        <input id="token-agente" v-model="tokenForm.token" type="text" autocomplete="off" spellcheck="false" class="min-w-0 flex-1 rounded-[10px] border-gray-300 font-mono text-sm" placeholder="cola aqui o token" />
                        <button type="submit" class="btn-pri h-11" :disabled="tokenForm.processing || !tokenForm.token">Guardar</button>
                    </div>
                    <InputError :message="tokenForm.errors.token" />
                </form>

                <div v-if="tokenAgente" class="flex flex-col gap-2">
                    <span class="text-sm font-bold">Atualizar o Raspberry com este token</span>
                    <p class="text-xs text-suave">Cola no terminal do Raspberry. Muda o token no <code class="font-mono">.env</code> e reinicia o agente.</p>
                    <div class="flex flex-wrap items-start gap-2">
                        <code class="min-w-0 flex-1 break-all rounded-md bg-fundo px-3 py-2.5 font-mono text-xs">{{ comandoPi }}</code>
                        <button type="button" class="btn-sec h-11" @click="copiar(comandoPi, 'comando')">{{ copiado === 'comando' ? 'Copiado ✓' : 'Copiar comando' }}</button>
                    </div>
                    <p class="text-xs text-suave">Instalação nova: <code class="font-mono">chmod +x setup-pi.sh &amp;&amp; ./setup-pi.sh</code> e, quando pedir o token, cola o de cima.</p>
                </div>
            </section>
        </div>

        <!-- Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-5 font-sans text-tinta sm:p-6">
                <h3 class="mb-4 text-xl font-extrabold">{{ modalTitle }}</h3>

                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <AvisoErros :errors="form.errors" :excluir="['agente', 'dispositivo', 'host', 'nome', 'porta', 'secao', 'tipo']" />
                    <label class="rotulo" for="nome">Nome *
                        <input id="nome" v-model="form.nome" type="text" class="campo" placeholder="ex: Impressora Bar">
                        <InputError :message="form.errors.nome" />
                    </label>

                    <label class="rotulo" for="secao">Secção
                        <select id="secao" v-model="form.secao" class="campo">
                            <option value="">Sem secção</option>
                            <option v-for="(label, value) in secoes" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.secao" />
                    </label>

                    <label class="rotulo" for="tipo">Ligação *
                        <select id="tipo" v-model="form.tipo" class="campo">
                            <option v-for="(label, valor) in tiposImpressora" :key="valor" :value="valor">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.tipo" />
                    </label>

                    <div v-if="form.tipo === 'rede'" class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                        <label class="rotulo" for="host">Host/IP *
                            <input id="host" v-model="form.host" type="text" class="campo" placeholder="ex: 192.168.1.100">
                            <InputError :message="form.errors.host" />
                        </label>
                        <label class="rotulo" for="porta">Porta *
                            <input id="porta" v-model.number="form.porta" type="number" class="campo" placeholder="ex: 9100" min="1" max="65535">
                            <InputError :message="form.errors.porta" />
                        </label>
                    </div>

                    <label v-else-if="form.tipo === 'usb'" class="rotulo" for="dispositivo">Dispositivo *
                        <input id="dispositivo" v-model="form.dispositivo" type="text" class="campo" placeholder="ex: POS-80C   ou   /dev/usb/lp0">
                        <span class="text-xs font-normal text-suave">
                            No Raspberry/Linux, o ficheiro do dispositivo, normalmente
                            <code class="font-mono">/dev/usb/lp0</code>. Esta opção é para uma impressora ligada ao
                            computador onde corre o agente, não aos postos.
                        </span>
                        <InputError :message="form.errors.dispositivo" />
                    </label>

                    <div v-else-if="form.tipo === 'webusb'" class="rounded-[10px] bg-verde-claro p-3 text-[13px] text-verde-escuro">
                        Não há nada a preencher: a impressora é escolhida uma vez em cada equipamento,
                        na primeira venda ou pela página <strong>Teste USB (WebUSB)</strong>.
                        <span class="mt-1 block">
                            <strong>Chromebook:</strong> funciona, desde que a impressora não esteja
                            adicionada nas definições de impressão do ChromeOS.
                        </span>
                        <span class="mt-1 block">
                            <strong>Windows:</strong> o driver de impressora do sistema fica com o
                            dispositivo e o browser não o consegue abrir. Nesses postos, liga a impressora
                            à rede e deixa o Raspberry imprimir.
                        </span>
                    </div>

                    <div v-else class="rounded-[10px] bg-laranja-claro p-3 text-[13px] text-laranja-texto">
                        Impressão normal do browser: serve para impressoras comuns. Numa térmica sai
                        desalinhada e <strong>não corta</strong>, porque o comando de corte não existe no HTML.
                    </div>

                    <label v-if="form.tipo === 'rede' || form.tipo === 'usb'" class="rotulo" for="agente">Posto (agente)
                        <input id="agente" v-model="form.agente" type="text" class="campo" placeholder="ex: caixa-1">
                        <span class="text-xs font-normal text-suave">
                            Que computador trata desta impressora. Tem de ser igual ao AGENTE
                            configurado no agente desse posto. Em branco: só um agente sem posto
                            definido a vai buscar.
                        </span>
                        <InputError :message="form.errors.agente" />
                    </label>

                    <label for="ativa" class="flex h-12 cursor-pointer items-center gap-3 rounded-[10px] border border-linha-forte px-3.5 text-[15px] font-bold">
                        <input id="ativa" v-model="form.ativa" type="checkbox" class="h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde" />
                        Impressora ativa
                    </label>

                    <div class="flex justify-end gap-2.5 pt-2">
                        <button type="button" class="btn-sec h-12" @click="closeModal">Cancelar</button>
                        <button type="submit" class="btn-pri h-12 px-6 disabled:opacity-60" :disabled="form.processing">{{ isEditing ? 'Atualizar' : 'Criar' }}</button>
                    </div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.btn-pri { @apply inline-flex items-center justify-center gap-2 rounded-[10px] bg-verde px-5 text-[15px] font-bold text-white transition hover:bg-verde-escuro; }
.btn-sec { @apply inline-flex items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white px-4 text-[15px] font-bold text-tinta transition hover:bg-fundo; }
.cartao { @apply rounded-[14px] border border-linha bg-white; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-semibold text-suave; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base font-normal text-tinta focus:border-verde focus:ring-verde; }
</style>
