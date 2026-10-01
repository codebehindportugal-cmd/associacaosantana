<script setup>
import InputError from '@/Components/InputError.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section class="flex flex-col gap-4">
        <header>
            <h2 class="text-xl font-extrabold">Os meus dados</h2>
            <p class="text-sm text-suave">Atualiza o teu nome e o email com que entras.</p>
        </header>

        <form class="flex flex-col gap-4" @submit.prevent="form.patch(route('profile.update'))">
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="rotulo" for="name">Nome
                    <input id="name" v-model="form.name" type="text" class="campo" required autofocus autocomplete="name">
                    <InputError :message="form.errors.name" />
                </label>
                <label class="rotulo" for="email">Email
                    <input id="email" v-model="form.email" type="email" class="campo" required autocomplete="username">
                    <InputError :message="form.errors.email" />
                </label>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null" class="flex flex-col gap-2 rounded-[10px] bg-laranja-claro p-3 text-sm text-laranja-texto">
                <div class="flex flex-wrap items-center gap-3">
                    <span>O teu email ainda não está confirmado.</span>
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="inline-flex h-11 items-center rounded-[10px] border border-laranja/30 bg-white px-3.5 text-sm font-bold text-laranja-texto hover:bg-fundo focus:outline-none focus:ring-2 focus:ring-verde"
                    >
                        Reenviar email de confirmação
                    </Link>
                </div>
                <div v-show="status === 'verification-link-sent'" class="font-bold text-verde-escuro">
                    Foi enviado um novo link de confirmação para o teu email.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button class="btn-pri" :disabled="form.processing">Guardar</button>
                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful" class="inline-flex items-center gap-1.5 text-sm font-bold text-verde-escuro">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10" /></svg>
                        Guardado.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>

<style scoped>
.cartao { @apply rounded-[14px] border border-linha bg-white p-4 sm:p-6; }
.rotulo { @apply flex min-w-0 flex-col gap-1.5 text-sm font-bold text-tinta; }
.campo { @apply h-12 w-full rounded-[10px] border border-linha-forte bg-white px-3.5 text-base font-normal text-tinta focus:border-verde focus:ring-verde; }
.btn-pri { @apply inline-flex h-12 items-center justify-center gap-2 rounded-[10px] bg-verde px-6 text-[15px] font-bold text-white transition hover:bg-verde-escuro disabled:opacity-60; }
</style>
