<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ socio: Object });
const form = useForm(props.socio ?? { numero_socio: '', nome: '', email: '', telefone: '', morada: '', data_nascimento: '', data_inscricao: new Date().toISOString().slice(0, 10), estado: 'ativo' });
const submit = () => props.socio ? form.put(route('socios.update', props.socio.id)) : form.post(route('socios.store'));

const campo = 'h-12 w-full min-w-0 rounded-[10px] border px-3 text-base text-tinta focus:border-verde focus:ring-verde';
const borda = (nome) => form.errors[nome] ? 'border-2 border-perigo' : 'border-linha-forte';
</script>

<template>
    <AppLayout>
        <div class="mx-auto flex max-w-[1200px] flex-col gap-5 font-sans text-tinta tabular-nums">
            <div class="flex flex-col gap-1.5">
                <Link :href="route('socios.index')" class="inline-flex items-center gap-1 self-start text-sm font-bold text-verde hover:text-verde-escuro">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    Sócios
                </Link>
                <h1 class="text-[30px] font-extrabold leading-tight">{{ socio ? 'Editar sócio' : 'Novo sócio' }}</h1>
            </div>

            <form class="flex max-w-[820px] flex-col gap-5" @submit.prevent="submit">
                <section class="flex flex-col gap-4 rounded-[14px] border border-linha bg-white p-5">
                    <h2 class="text-lg font-extrabold">Identificação</h2>
                    <div class="grid grid-cols-[96px_minmax(0,1fr)] gap-3 sm:grid-cols-[160px_minmax(0,1fr)] sm:gap-4">
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                            N.º de sócio
                            <input v-model="form.numero_socio" type="text" inputmode="numeric" :aria-invalid="!!form.errors.numero_socio" :class="[campo, borda('numero_socio'), 'text-lg font-extrabold']">
                        </label>
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                            Nome *
                            <input v-model="form.nome" type="text" required placeholder="Nome completo" :aria-invalid="!!form.errors.nome" :class="[campo, borda('nome')]">
                        </label>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                            Data de nascimento
                            <input v-model="form.data_nascimento" type="date" :class="[campo, borda('data_nascimento')]">
                        </label>
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                            Data de inscrição
                            <input v-model="form.data_inscricao" type="date" :class="[campo, borda('data_inscricao')]">
                        </label>
                    </div>
                    <fieldset class="flex flex-col gap-1.5">
                        <legend class="mb-1.5 text-sm font-semibold text-suave">Estado</legend>
                        <div role="radiogroup" aria-label="Estado" class="grid max-w-[320px] grid-cols-2 gap-1.5 rounded-xl bg-fundo p-1">
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="form.estado === 'ativo'"
                                class="h-11 rounded-[10px] text-[15px] font-extrabold"
                                :class="form.estado === 'ativo' ? 'bg-verde text-white' : 'text-suave'"
                                @click="form.estado = 'ativo'"
                            >Ativo</button>
                            <button
                                type="button"
                                role="radio"
                                :aria-checked="form.estado === 'inativo'"
                                class="h-11 rounded-[10px] text-[15px] font-extrabold"
                                :class="form.estado === 'inativo' ? 'bg-suave text-white' : 'text-suave'"
                                @click="form.estado = 'inativo'"
                            >Inativo</button>
                        </div>
                    </fieldset>
                </section>

                <section class="flex flex-col gap-4 rounded-[14px] border border-linha bg-white p-5">
                    <h2 class="text-lg font-extrabold">Contactos</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                            Telefone
                            <input v-model="form.telefone" type="tel" placeholder="9xx xxx xxx" :class="[campo, borda('telefone')]">
                        </label>
                        <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                            Email
                            <input v-model="form.email" type="email" placeholder="nome@exemplo.pt" :class="[campo, borda('email')]">
                        </label>
                    </div>
                    <label class="flex flex-col gap-1.5 text-sm font-semibold text-suave">
                        Morada / terra
                        <textarea v-model="form.morada" rows="3" placeholder="Rua, n.º, localidade" class="w-full resize-y rounded-[10px] border p-3 text-base text-tinta focus:border-verde focus:ring-verde" :class="borda('morada')"></textarea>
                        <span class="text-[13px] font-medium text-suave-2">Aparece na lista de sócios como "Terra".</span>
                    </label>
                </section>

                <div v-if="Object.keys(form.errors).length" role="alert" class="flex items-start gap-2.5 rounded-[10px] bg-perigo-claro px-4 py-3 text-[15px] font-semibold text-perigo-texto">
                    <svg class="mt-0.5 shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v5M12 16v.01" /></svg>
                    <div>
                        <div v-for="(erro, nome) in form.errors" :key="nome">{{ erro }}</div>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2.5">
                    <Link :href="route('socios.index')" class="inline-flex h-[52px] items-center rounded-[10px] border border-linha-forte bg-white px-[22px] text-base font-bold text-tinta hover:bg-fundo">Cancelar</Link>
                    <button type="submit" class="h-[52px] rounded-[10px] bg-verde px-8 text-base font-extrabold text-white hover:bg-verde-escuro disabled:opacity-60" :disabled="form.processing">{{ form.processing ? 'A guardar...' : 'Guardar' }}</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
