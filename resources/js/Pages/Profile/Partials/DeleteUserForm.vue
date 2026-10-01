<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="flex flex-col gap-4">
        <header>
            <h2 class="text-xl font-extrabold text-perigo">Apagar a minha conta</h2>
            <p class="text-sm text-suave">
                Quando a conta é apagada, todos os seus dados são apagados para sempre. Antes de apagar,
                guarda o que quiseres manter.
            </p>
        </header>

        <button v-if="!confirmingUserDeletion" type="button" class="inline-flex h-12 w-fit items-center rounded-[10px] border border-perigo/40 bg-white px-5 text-[15px] font-bold text-perigo transition hover:bg-perigo-claro" @click="confirmUserDeletion">
            Apagar conta
        </button>

        <div v-else class="flex flex-col gap-3 rounded-[10px] bg-perigo-claro p-4" role="alertdialog" aria-labelledby="t-apagar-conta">
            <h3 id="t-apagar-conta" class="text-base font-extrabold text-perigo-texto">Tens a certeza que queres apagar a tua conta?</h3>
            <p class="text-sm text-perigo-texto">Escreve a tua password para confirmar que queres apagar a conta para sempre.</p>
            <label class="flex flex-col gap-1.5 text-sm font-bold text-perigo-texto sm:max-w-sm" for="password_apagar">Password
                <input
                    id="password_apagar"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="h-12 w-full rounded-[10px] border border-perigo/40 bg-white px-3.5 text-base font-normal text-tinta focus:border-perigo focus:ring-perigo"
                    placeholder="Password"
                    @keyup.enter="deleteUser"
                >
                <InputError :message="form.errors.password" />
            </label>
            <div class="flex flex-wrap gap-2.5">
                <button type="button" class="inline-flex h-12 items-center rounded-[10px] border border-linha-forte bg-white px-5 text-[15px] font-bold text-tinta hover:bg-fundo" @click="closeModal">Cancelar</button>
                <button type="button" class="inline-flex h-12 items-center rounded-[10px] bg-perigo px-5 text-[15px] font-bold text-white transition hover:opacity-90 disabled:opacity-50" :disabled="form.processing" @click="deleteUser">Apagar conta para sempre</button>
            </div>
        </div>
    </section>
</template>
