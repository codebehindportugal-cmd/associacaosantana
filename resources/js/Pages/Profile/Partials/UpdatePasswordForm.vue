<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section class="flex flex-col gap-4">
        <header>
            <h2 class="text-xl font-extrabold">Mudar a password</h2>
            <p class="text-sm text-suave">Usa uma password longa e difícil de adivinhar para manter a conta segura.</p>
        </header>

        <form class="flex flex-col gap-4" @submit.prevent="updatePassword">
            <label class="rotulo sm:max-w-[calc(50%-0.5rem)]" for="current_password">Password atual
                <input id="current_password" ref="currentPasswordInput" v-model="form.current_password" type="password" class="campo" autocomplete="current-password">
                <InputError :message="form.errors.current_password" />
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="rotulo" for="password">Nova password
                    <input id="password" ref="passwordInput" v-model="form.password" type="password" class="campo" autocomplete="new-password">
                    <InputError :message="form.errors.password" />
                </label>
                <label class="rotulo" for="password_confirmation">Repetir a nova password
                    <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="campo" autocomplete="new-password">
                    <InputError :message="form.errors.password_confirmation" />
                </label>
            </div>

            <div class="flex items-center gap-4">
                <button class="btn-pri" :disabled="form.processing">Mudar password</button>
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
