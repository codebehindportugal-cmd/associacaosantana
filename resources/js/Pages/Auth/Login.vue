<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Entrar" />

        <h2 class="m-0 text-[26px] font-extrabold leading-tight text-tinta">Entrar na plataforma</h2>

        <div v-if="status" class="mt-4 rounded-[10px] bg-verde-claro p-3.5 text-[15px] font-semibold text-verde-escuro" role="status">
            {{ status }}
        </div>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="rotulo">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="campo"
                    :class="{ 'campo-erro': form.errors.email }"
                    required
                    autofocus
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
                    autocomplete="current-password"
                />
                <p v-if="form.errors.password" class="erro">{{ form.errors.password }}</p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-[15px] text-suave">
                    <input v-model="form.remember" type="checkbox" name="remember" class="h-5 w-5 rounded border-linha-forte text-verde focus:ring-verde" />
                    Manter sessão
                </label>
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-[15px] font-bold text-verde underline transition hover:text-verde-escuro">
                    Esqueceu a password?
                </Link>
            </div>

            <button type="submit" class="btn-primario" :disabled="form.processing">
                {{ form.processing ? 'A entrar...' : 'Entrar' }}
            </button>
        </form>

        <div class="my-5 flex items-center gap-3 text-[13px] font-semibold text-suave-2" aria-hidden="true">
            <span class="h-px flex-1 bg-linha"></span>
            ou
            <span class="h-px flex-1 bg-linha"></span>
        </div>

        <Link :href="route('pos.login')" class="flex h-[52px] w-full items-center justify-center gap-2.5 rounded-[10px] border border-linha-forte bg-fundo text-base font-bold text-tinta no-underline transition hover:border-verde hover:text-verde">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2" /><path d="M8 20h8M12 16v4" /></svg>
            Aceder ao POS / Ecrãs
        </Link>
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
