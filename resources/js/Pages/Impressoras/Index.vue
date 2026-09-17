<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import DangerButton from '@/Components/DangerButton.vue'
import Modal from '@/Components/Modal.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
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

function guardarImpressoraDoPosto(terminal, impressoraId) {
    useForm({ impressora_id: impressoraId || null })
        .post(route('terminais.impressora', terminal.id), { preserveScroll: true })
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
        useForm({ _method: 'delete' }).post(route('terminais.destroy', terminal.id), { preserveScroll: true })
    }
}

const retentarForm = useForm({})
function retentarFalhados() {
    retentarForm.post(route('impressoras.retentar-falhados'))
}
</script>

<template>
    <AppLayout title="Impressoras">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-slate-800">Impressoras</h2>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header with Button -->
            <div class="mb-6 flex items-center justify-between">
                <p class="text-sm text-slate-600">
                    Total de impressoras: <span class="font-semibold">{{ impressoras.length }}</span>
                </p>
                <div class="flex items-center gap-3">
                    <a
                        :href="route('impressoras.teste-usb')"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Teste USB (WebUSB)
                    </a>
                    <PrimaryButton @click="openCreateModal"> + Nova Impressora </PrimaryButton>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="w-full">
                    <thead class="border-b border-slate-200 bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-900">Nome</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-900">Secção</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-900">Destino</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-900">Posto</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-slate-900">Status</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-slate-900">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="impressora in impressoras" :key="impressora.id" class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-900">{{ impressora.nome }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ getSectionName(impressora.secao) }}</td>
                            <td class="px-6 py-4 font-mono text-sm text-slate-600">{{ destino(impressora) }}</td>
                            <td class="px-6 py-4 text-sm text-slate-600">{{ impressora.agente || '—' }}</td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    v-if="impressora.ativa"
                                    class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-800"
                                >
                                    ● Ativa
                                </span>
                                <span v-else class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                    ● Inativa
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button
                                    @click="openEditModal(impressora)"
                                    class="mr-2 text-sm text-blue-600 hover:text-blue-900 hover:underline"
                                >
                                    Editar
                                </button>
                                <button
                                    @click="deleteImpressora(impressora)"
                                    class="text-sm text-red-600 hover:text-red-900 hover:underline"
                                >
                                    Remover
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty State -->
                <div v-if="impressoras.length === 0" class="px-6 py-12 text-center">
                    <p class="text-slate-500">Nenhuma impressora configurada ainda.</p>
                </div>
            </div>
        </div>

        <!-- Postos POS -->
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-5 shadow-sm">
                <h3 class="font-semibold text-slate-900">Postos POS</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Cada posto tem o seu PIN e a sua impressora. Como qualquer posto pode vender
                    qualquer produto, é a impressora do posto que manda — sem ela definida, o talão
                    sai na primeira impressora da secção, que com vários pontos é quase sempre a errada.
                    Podes criar aqui um posto novo a meio do evento, um telemóvel a ajudar num pico, por exemplo.
                    <strong>Como</strong> cada posto imprime vem da impressora que escolheres — agente, WebUSB
                    ou impressão do browser — e não precisa de programação nenhuma.
                </p>

                <div v-if="postosSemImpressora.length" class="mt-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                    <strong>Sem impressora definida:</strong>
                    {{ postosSemImpressora.map((t) => t.nome).join(', ') }}.
                    Os talões destes postos vão sair na primeira impressora da secção — na prática,
                    no posto do lado.
                </div>

                <div v-if="impressorasRepetidas.length" class="mt-4 rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                    <strong>Impressora partilhada por mais do que um posto:</strong>
                    {{ impressorasRepetidas.map((i) => i.nome).join(', ') }}.
                    Se cada posto tem a sua, isto é engano.
                </div>

                <div v-if="!terminais.length" class="mt-4 rounded-md bg-slate-50 p-4 text-center text-sm text-slate-500">
                    Ainda não há postos configurados.
                </div>

                <div v-else class="mt-4 divide-y divide-slate-100">
                    <div v-for="terminal in terminais" :key="terminal.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <span class="font-medium text-slate-900">{{ terminal.nome }}</span>
                            <span class="ml-2 text-xs uppercase text-slate-500">{{ terminal.tipo }}</span>
                            <span v-if="terminal.localizacao" class="ml-2 text-xs text-slate-500">· {{ terminal.localizacao }}</span>
                            <span v-if="!terminal.ativo" class="ml-2 text-xs font-semibold text-amber-700">inativo</span>
                            <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-700">
                                {{ comoImprime(terminal) }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <select
                                class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900"
                                :value="terminal.impressora_id || ''"
                                @change="guardarImpressoraDoPosto(terminal, $event.target.value)"
                            >
                                <option value="">Pela secção (comportamento antigo)</option>
                                <option v-for="impressora in impressoras" :key="impressora.id" :value="impressora.id">
                                    {{ impressora.nome }}
                                </option>
                            </select>
                            <button type="button" class="text-sm font-medium text-blue-600 hover:underline" @click="editarPosto(terminal)">Editar</button>
                            <button v-if="terminal.ativo" type="button" class="text-sm font-medium text-red-600 hover:underline" @click="desativarPosto(terminal)">Desativar</button>
                        </div>
                    </div>
                </div>

                <form class="mt-5 border-t border-slate-100 pt-5" @submit.prevent="guardarPosto">
                    <div class="mb-3 flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-slate-900">
                            {{ postoEmEdicao ? `Editar posto: ${postoEmEdicao.nome}` : 'Novo posto' }}
                        </h4>
                        <button v-if="postoEmEdicao" type="button" class="text-xs font-medium text-slate-500 hover:underline" @click="novoPosto">
                            Cancelar edição
                        </button>
                    </div>

                    <div class="grid gap-3 md:grid-cols-5">
                        <div>
                            <InputLabel for="posto_nome" value="Nome *" />
                            <TextInput id="posto_nome" v-model="postoForm.nome" type="text" class="mt-1 block w-full" placeholder="ex: Pré-pagamento 3" />
                            <InputError class="mt-1" :message="postoForm.errors.nome" />
                        </div>
                        <div>
                            <InputLabel for="posto_tipo" value="Tipo *" />
                            <select
                                id="posto_tipo"
                                v-model="postoForm.tipo"
                                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900"
                            >
                                <option v-for="tipo in tipos" :key="tipo" :value="tipo">{{ tipo }}</option>
                            </select>
                            <InputError class="mt-1" :message="postoForm.errors.tipo" />
                        </div>
                        <div>
                            <InputLabel for="posto_local" value="Localização" />
                            <TextInput id="posto_local" v-model="postoForm.localizacao" type="text" class="mt-1 block w-full" placeholder="ex: Tenda" />
                            <InputError class="mt-1" :message="postoForm.errors.localizacao" />
                        </div>
                        <div>
                            <InputLabel for="posto_pin" :value="postoEmEdicao ? 'PIN (em branco: mantém)' : 'PIN *'" />
                            <TextInput id="posto_pin" v-model="postoForm.pin" type="text" class="mt-1 block w-full" placeholder="4 a 12 dígitos" />
                            <InputError class="mt-1" :message="postoForm.errors.pin" />
                        </div>
                        <div>
                            <InputLabel for="posto_impressora" value="Impressora" />
                            <select
                                id="posto_impressora"
                                v-model="postoForm.impressora_id"
                                class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900"
                            >
                                <option value="">Pela secção</option>
                                <option v-for="impressora in impressoras" :key="impressora.id" :value="impressora.id">
                                    {{ impressora.nome }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="postoForm.errors.impressora_id" />
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-4">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="postoForm.ativo" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" />
                            Posto ativo
                        </label>
                        <PrimaryButton type="submit" :disabled="postoForm.processing">
                            {{ postoEmEdicao ? 'Guardar posto' : 'Criar posto' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Retentar Falhados -->
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="font-semibold text-red-900">Jobs de impressão falhados</h3>
                    <p class="mt-1 text-sm text-red-700">
                        Se o pedido não imprimiu tudo, usa este botão para colocar os jobs falhados de volta na fila.
                    </p>
                </div>
                <button
                    type="button"
                    :disabled="retentarForm.processing"
                    class="shrink-0 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60 transition"
                    @click="retentarFalhados"
                >
                    {{ retentarForm.processing ? 'A processar...' : 'Retentar jobs falhados' }}
                </button>
            </div>
        </div>

        <!-- Download Agente -->
        <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-5">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="font-semibold text-stone-800">Agente de impressão</h3>
                    <p class="mt-1 text-sm text-stone-600">
                        Corre no computador de cada posto: em Windows com as impressoras por USB, ou num
                        Raspberry Pi na rede das impressoras. O script abaixo é para Linux/Raspberry.
                    </p>
                    <p class="mt-1 text-xs text-stone-500 font-mono">
                        chmod +x setup-pi.sh &amp;&amp; ./setup-pi.sh
                    </p>
                </div>
                <a
                    :href="route('impressoras.download-agente')"
                    class="shrink-0 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700 transition"
                >
                    ↓ Download setup-pi.sh
                </a>
            </div>
        </div>

        <!-- Modal -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h3 class="mb-4 text-lg font-semibold text-slate-900">{{ modalTitle }}</h3>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel for="nome" value="Nome *" />
                        <TextInput
                            id="nome"
                            v-model="form.nome"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="ex: Impressora Bar"
                        />
                        <InputError class="mt-2" :message="form.errors.nome" />
                    </div>

                    <div>
                        <InputLabel for="secao" value="Secção" />
                        <select
                            id="secao"
                            v-model="form.secao"
                            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-blue-500"
                        >
                            <option value="">Sem secção</option>
                            <option v-for="(label, value) in secoes" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.secao" />
                    </div>

                    <div>
                        <InputLabel for="tipo" value="Ligação *" />
                        <select
                            id="tipo"
                            v-model="form.tipo"
                            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-blue-500"
                        >
                            <option v-for="(label, valor) in tiposImpressora" :key="valor" :value="valor">{{ label }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.tipo" />
                    </div>

                    <template v-if="form.tipo === 'rede'">
                        <div>
                            <InputLabel for="host" value="Host/IP *" />
                            <TextInput
                                id="host"
                                v-model="form.host"
                                type="text"
                                class="mt-1 block w-full"
                                placeholder="ex: 192.168.1.100"
                            />
                            <InputError class="mt-2" :message="form.errors.host" />
                        </div>

                        <div>
                            <InputLabel for="porta" value="Porta *" />
                            <TextInput
                                id="porta"
                                v-model.number="form.porta"
                                type="number"
                                class="mt-1 block w-full"
                                placeholder="ex: 9100"
                                min="1"
                                max="65535"
                            />
                            <InputError class="mt-2" :message="form.errors.porta" />
                        </div>
                    </template>

                    <div v-else-if="form.tipo === 'usb'">
                        <InputLabel for="dispositivo" value="Dispositivo *" />
                        <TextInput
                            id="dispositivo"
                            v-model="form.dispositivo"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="ex: POS-80C   ou   /dev/usb/lp0"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            No Raspberry/Linux, o ficheiro do dispositivo, normalmente
                            <code>/dev/usb/lp0</code>. Esta opção é para uma impressora ligada ao
                            computador onde corre o agente, não aos postos.
                        </p>
                        <InputError class="mt-2" :message="form.errors.dispositivo" />
                    </div>

                    <div v-else-if="form.tipo === 'webusb'" class="rounded-md bg-emerald-50 p-3 text-xs text-emerald-900">
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

                    <div v-else class="rounded-md bg-amber-50 p-3 text-xs text-amber-900">
                        Impressão normal do browser: serve para impressoras comuns. Numa térmica sai
                        desalinhada e <strong>não corta</strong>, porque o comando de corte não existe no HTML.
                    </div>

                    <div v-if="form.tipo === 'rede' || form.tipo === 'usb'">
                        <InputLabel for="agente" value="Posto (agente)" />
                        <TextInput
                            id="agente"
                            v-model="form.agente"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="ex: caixa-1"
                        />
                        <p class="mt-1 text-xs text-slate-500">
                            Que computador trata desta impressora. Tem de ser igual ao AGENTE
                            configurado no agente desse posto. Em branco: só um agente sem posto
                            definido a vai buscar.
                        </p>
                        <InputError class="mt-2" :message="form.errors.agente" />
                    </div>

                    <div class="flex items-center gap-2">
                         <input
                            id="ativa"
                            v-model="form.ativa"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <label for="ativa" class="text-sm font-medium text-slate-700">Impressora Ativa</label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Cancelar
                        </button>
                        <PrimaryButton type="submit" :disabled="form.processing">
                            {{ isEditing ? 'Atualizar' : 'Criar' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>
