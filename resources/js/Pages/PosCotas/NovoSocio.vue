<script setup>
import { Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ proximoNumero: String });
const form = useForm({ numero_socio: props.proximoNumero, nome: '', telefone: '', email: '' });
</script>

<template>
    <div class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums">
        <header class="flex h-16 shrink-0 items-center justify-between gap-3 bg-escuro px-4 text-white sm:px-6">
            <div class="flex items-center gap-4">
                <Link :href="route('pos.cotas.index')" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    Cancelar
                </Link>
                <div class="text-lg font-extrabold">Novo sócio</div>
            </div>
            <div class="hidden text-sm text-[#B9C4BE] sm:block">Tesouraria · Cotas</div>
        </header>

        <main class="flex flex-1 items-center justify-center p-4 sm:p-6">
            <form class="flex w-full max-w-[760px] flex-col gap-5 rounded-[18px] border border-linha bg-white p-5 sm:p-8" @submit.prevent="form.post(route('pos.cotas.socio.novo'))">
                <div>
                    <h1 class="text-[30px] font-extrabold">Novo sócio</h1>
                    <p class="mt-1.5 text-base text-suave">Só o nome é preciso para começar. O número já vem preenchido com o próximo livre.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-[200px_minmax(0,1fr)]">
                    <div>
                        <label for="numero" class="mb-1.5 block text-[15px] font-bold">Número de sócio</label>
                        <input
                            id="numero"
                            v-model="form.numero_socio"
                            inputmode="numeric"
                            :aria-invalid="form.errors.numero_socio ? 'true' : undefined"
                            class="h-16 w-full rounded-[10px] bg-white px-4 text-2xl font-extrabold text-tinta focus:border-verde focus:ring-4 focus:ring-verde-claro2"
                            :class="form.errors.numero_socio ? 'border-2 border-perigo' : 'border border-linha-forte'"
                        >
                    </div>
                    <div>
                        <label for="nome" class="mb-1.5 block text-[15px] font-bold">Nome</label>
                        <input
                            id="nome"
                            v-model="form.nome"
                            class="h-16 w-full rounded-[10px] bg-white px-4 text-[22px] font-semibold text-tinta focus:border-verde focus:ring-4 focus:ring-verde-claro2"
                            :class="form.errors.nome ? 'border-2 border-perigo' : 'border border-linha-forte'"
                        >
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="telefone" class="mb-1.5 block text-[15px] font-bold">Telefone <span class="font-medium text-suave-2">(opcional)</span></label>
                        <input
                            id="telefone"
                            v-model="form.telefone"
                            type="tel"
                            inputmode="tel"
                            placeholder="9XX XXX XXX"
                            class="h-16 w-full rounded-[10px] bg-white px-4 text-[22px] text-tinta focus:border-verde focus:ring-4 focus:ring-verde-claro2"
                            :class="form.errors.telefone ? 'border-2 border-perigo' : 'border border-linha-forte'"
                        >
                    </div>
                    <div>
                        <label for="email" class="mb-1.5 block text-[15px] font-bold">Email <span class="font-medium text-suave-2">(opcional)</span></label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            inputmode="email"
                            placeholder="nome@exemplo.pt"
                            class="h-16 w-full rounded-[10px] bg-white px-4 text-[22px] text-tinta focus:border-verde focus:ring-4 focus:ring-verde-claro2"
                            :class="form.errors.email ? 'border-2 border-perigo' : 'border border-linha-forte'"
                        >
                    </div>
                </div>

                <div v-if="Object.keys(form.errors).length" role="alert" class="flex items-center gap-2.5 rounded-[10px] bg-perigo-claro px-4 py-3 text-[15px] font-semibold text-perigo-texto">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 8v4M12 16h.01" /></svg>
                    {{ Object.values(form.errors)[0] }}
                </div>

                <div class="grid gap-3 sm:grid-cols-[220px_minmax(0,1fr)]">
                    <Link :href="route('pos.cotas.index')" class="order-2 flex h-[72px] items-center justify-center rounded-xl border border-linha-forte bg-white text-lg font-bold text-tinta sm:order-1">Cancelar</Link>
                    <button class="order-1 flex h-[72px] items-center justify-center gap-2.5 rounded-xl bg-verde text-[21px] font-extrabold text-white hover:bg-verde-escuro disabled:opacity-50 sm:order-2" :disabled="form.processing">
                        <svg class="h-[26px] w-[26px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
                        Criar sócio
                    </button>
                </div>
            </form>
        </main>
    </div>
</template>
