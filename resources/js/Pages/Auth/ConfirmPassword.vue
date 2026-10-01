<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirmar password" />

        <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-verde-claro text-verde">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
        </span>
        <h2 class="m-0 text-[26px] font-extrabold leading-tight text-tinta">Confirmar password</h2>
        <p class="m-0 mt-2 text-[15px] leading-relaxed text-suave">Esta é uma área protegida da aplicação. Confirme a sua password antes de continuar.</p>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <div>
                <label for="password" class="rotulo">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="campo"
                    :class="{ 'campo-erro': form.errors.password }"
                    required
                    autofocus
                    autocomplete="current-password"
                />
                <p v-if="form.errors.password" class="erro">{{ form.errors.password }}</p>
            </div>

            <button type="submit" class="btn-primario" :disabled="form.processing">
                {{ form.processing ? 'A confirmar...' : 'Confirmar' }}
            </button>
        </form>
    </GuestLayout>
</template>

<style scoped>
.rotulo { display: block; margin-bottom: 6px; font-size: 15px; font-weight: 700; color: #16201C; }
.campo {
    width: 100%;
    height: 50px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid #D5D9D3;
    background: #FFFFFF;
    color: #16201C;
    font-size: 16px;
}
.campo:focus { border-color: #0F6B4F; box-shadow: 0 0 0 3px rgb(15 107 79 / 0.15); outline: none; }
.campo-erro { border-color: #A3241A; }
.erro { margin: 6px 0 0; font-size: 14px; font-weight: 600; color: #A3241A; }
.btn-primario {
    display: flex; align-items: center; justify-content: center; width: 100%; height: 52px;
    border-radius: 10px; background: #0F6B4F; color: #FFFFFF; font-size: 17px; font-weight: 700;
    transition: background 150ms;
}
.btn-primario:hover { background: #0A4D39; }
.btn-primario:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
