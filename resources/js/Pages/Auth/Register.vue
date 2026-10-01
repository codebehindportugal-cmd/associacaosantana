<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Criar conta" />

        <h2 class="m-0 text-[26px] font-extrabold leading-tight text-tinta">Criar conta</h2>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="rotulo">Nome</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    class="campo"
                    :class="{ 'campo-erro': form.errors.name }"
                    required
                    autofocus
                    autocomplete="name"
                />
                <p v-if="form.errors.name" class="erro">{{ form.errors.name }}</p>
            </div>
            <div>
                <label for="email" class="rotulo">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="campo"
                    :class="{ 'campo-erro': form.errors.email }"
                    required
                    autocomplete="username"
                />
                <p v-if="form.errors.email" class="erro">{{ form.errors.email }}</p>
            </div>
            <div>
                <label for="password" class="rotulo">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    class="campo"
                    :class="{ 'campo-erro': form.errors.password }"
                    required
                    autocomplete="new-password"
                />
                <p v-if="form.errors.password" class="erro">{{ form.errors.password }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="rotulo">Confirmar password</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="campo"
                    :class="{ 'campo-erro': form.errors.password_confirmation }"
                    required
                    autocomplete="new-password"
                />
                <p v-if="form.errors.password_confirmation" class="erro">{{ form.errors.password_confirmation }}</p>
            </div>

            <button type="submit" class="btn-primario" :disabled="form.processing">
                {{ form.processing ? 'A criar...' : 'Criar conta' }}
            </button>

            <p class="m-0 text-center text-[15px] text-suave">
                Já tem conta?
                <Link :href="route('login')" class="font-bold text-verde underline transition hover:text-verde-escuro">Entrar</Link>
            </p>
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
