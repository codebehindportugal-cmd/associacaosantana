<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ mesa: Object });
const form = useForm(props.mesa ?? { numero: '', nome: '', capacidade: 10, localizacao: 'sala', estado: 'livre' });
const submit = () => props.mesa ? form.put(route('mesas.update', props.mesa.id)) : form.post(route('mesas.store'));

// Apresentação
const localizacoes = [['sala', 'Sala'], ['interior', 'Interior'], ['exterior', 'Exterior'], ['bar', 'Bar']];
const estados = [['livre', 'Livre', 'border-suave bg-suave'], ['ocupada', 'Ocupada', 'border-verde bg-verde'], ['reservada', 'Reservada', 'border-azul bg-azul']];
const ajustarCapacidade = (delta) => { form.capacidade = Math.min(10, Math.max(1, Number(form.capacidade || 0) + delta)); };
</script>

<template>
    <AppLayout>
        <div class="flex flex-col gap-5 font-sans text-tinta tabular-nums">
            <Link :href="route('mesas.index')" class="inline-flex min-h-11 items-center gap-1.5 self-start text-[15px] font-bold text-verde hover:text-verde-escuro">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                Voltar ao mapa
            </Link>
            <div class="flex flex-col gap-1.5">
                <h1 class="text-[30px] font-extrabold leading-tight">{{ mesa ? 'Editar mesa' : 'Nova mesa' }}</h1>
                <p class="text-[15px] text-suave">{{ mesa ? 'Altera os dados desta mesa.' : 'Acrescenta uma mesa ao mapa da sala.' }}</p>
            </div>

            <form class="flex w-full max-w-[640px] flex-col gap-5 rounded-[14px] border border-linha bg-white p-5 sm:p-6" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex flex-col gap-1.5">
                        <span class="text-[15px] font-bold">Número</span>
                        <input v-model="form.numero" inputmode="numeric" class="h-[52px] rounded-[10px] border-linha-forte px-3.5 text-xl font-extrabold focus:border-verde focus:ring-verde" placeholder="Número">
                        <span v-if="form.errors.numero" class="text-sm font-semibold text-perigo">{{ form.errors.numero }}</span>
                    </label>
                    <label class="flex flex-col gap-1.5">
                        <span class="text-[15px] font-bold">Nome</span>
                        <input v-model="form.nome" class="h-[52px] rounded-[10px] border-linha-forte px-3.5 text-[17px] focus:border-verde focus:ring-verde" placeholder="Nome">
                        <span v-if="form.errors.nome" class="text-sm font-semibold text-perigo">{{ form.errors.nome }}</span>
                    </label>
                </div>

                <div class="flex flex-col gap-1.5">
                    <span id="cap-rotulo" class="text-[15px] font-bold">Capacidade</span>
                    <div role="group" aria-labelledby="cap-rotulo" class="flex flex-wrap items-center gap-2.5">
                        <button type="button" aria-label="Menos um lugar" class="h-[52px] w-[52px] rounded-[10px] border border-linha-forte bg-white text-2xl font-bold hover:bg-fundo" @click="ajustarCapacidade(-1)">−</button>
                        <input v-model="form.capacidade" type="number" min="1" max="10" aria-label="Capacidade" class="h-[52px] w-20 rounded-[10px] border-linha-forte text-center text-[22px] font-extrabold focus:border-verde focus:ring-verde">
                        <button type="button" aria-label="Mais um lugar" class="h-[52px] w-[52px] rounded-[10px] border border-linha-forte bg-white text-2xl font-bold hover:bg-fundo" @click="ajustarCapacidade(1)">+</button>
                        <span class="text-sm text-suave-2">Capacidade física até 10</span>
                    </div>
                    <span v-if="form.errors.capacidade" class="text-sm font-semibold text-perigo">{{ form.errors.capacidade }}</span>
                </div>

                <div class="flex flex-col gap-2">
                    <span id="loc-rotulo" class="text-[15px] font-bold">Localização</span>
                    <div role="radiogroup" aria-labelledby="loc-rotulo" class="flex flex-wrap gap-2">
                        <button v-for="[valor, label] in localizacoes" :key="valor" type="button" role="radio" :aria-checked="form.localizacao === valor"
                            class="h-12 rounded-full border px-5 text-[15px] font-bold transition"
                            :class="form.localizacao === valor ? 'border-escuro bg-escuro text-white' : 'border-linha-forte bg-white text-tinta hover:bg-fundo'"
                            @click="form.localizacao = valor">{{ label }}</button>
                    </div>
                    <span v-if="form.errors.localizacao" class="text-sm font-semibold text-perigo">{{ form.errors.localizacao }}</span>
                </div>

                <div class="flex flex-col gap-2">
                    <span id="est-rotulo" class="text-[15px] font-bold">Estado</span>
                    <div role="radiogroup" aria-labelledby="est-rotulo" class="flex flex-wrap gap-2">
                        <button v-for="[valor, label, cor] in estados" :key="valor" type="button" role="radio" :aria-checked="form.estado === valor"
                            class="h-12 rounded-full border px-5 text-[15px] font-bold transition"
                            :class="form.estado === valor ? [cor, 'text-white'] : 'border-linha-forte bg-white text-tinta hover:bg-fundo'"
                            @click="form.estado = valor">{{ label }}</button>
                    </div>
                    <span v-if="form.errors.estado" class="text-sm font-semibold text-perigo">{{ form.errors.estado }}</span>
                </div>

                <div v-if="Object.keys(form.errors).length" class="rounded-[10px] bg-perigo-claro p-3 text-sm font-bold text-perigo-texto">
                    <div v-for="(erro, campo) in form.errors" :key="campo">{{ erro }}</div>
                </div>

                <div class="flex flex-wrap gap-2.5 pt-1">
                    <button type="submit" class="h-14 rounded-[10px] bg-verde px-8 text-[17px] font-extrabold text-white hover:bg-verde-escuro disabled:opacity-60" :disabled="form.processing">{{ form.processing ? 'A guardar...' : 'Guardar' }}</button>
                    <Link :href="route('mesas.index')" class="inline-flex h-14 items-center rounded-[10px] border border-linha-forte bg-white px-6 text-base font-bold text-tinta hover:bg-fundo">Cancelar</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
