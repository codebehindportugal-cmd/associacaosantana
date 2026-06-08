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
})

const showModal = ref(false)
const isEditing = ref(false)
const selectedImpressora = ref(null)

const form = useForm({
    nome: '',
    secao: '',
    host: '',
    porta: 9100,
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
    form.host = impressora.host
    form.porta = impressora.porta
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
    return props.secoes[secao] || secao
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
                <PrimaryButton @click="openCreateModal"> + Nova Impressora </PrimaryButton>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="w-full">
                    <thead class="border-b border-slate-200 bg-slate-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-900">Nome</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-900">Secção</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-slate-900">Host:Porta</th>
                            <th class="px-6 py-4 text-center text-sm font-semibold text-slate-900">Status</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-slate-900">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="impressora in impressoras" :key="impressora.id" class="hover:bg-slate-50">
                            <td class="px-6 py-4 font-medium text-slate-900">{{ impressora.nome }}</td>
                            <td class="px-6 py-4 text-slate-600">{{ getSectionName(impressora.secao) }}</td>
                            <td class="px-6 py-4 font-mono text-sm text-slate-600">{{ impressora.host }}:{{ impressora.porta }}</td>
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
