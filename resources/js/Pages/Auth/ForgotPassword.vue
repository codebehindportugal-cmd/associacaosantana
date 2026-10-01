<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Recuperar password" />

        <h2 class="m-0 text-[26px] font-extrabold leading-tight text-tinta">Recuperar password</h2>
        <p class="m-0 mt-2 text-[15px] leading-relaxed text-suave">Esqueceu-se da password? Não há problema. Indique o seu email e enviamos-lhe uma ligação para escolher uma nova.</p>

        <div class="mt-5">
        <div v-if="status" class="mb-5 flex items-start gap-2.5 rounded-[10px] bg-verde-claro p-3.5 text-[15px] font-semibold text-verde-escuro" role="status">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mt-px shrink-0"><path d="M5 12l5 5L19 7" /></svg>
            <span>{{ status }}</span>
        </div>
        </div>

        <form class="space-y-4" :class="{ 'mt-5': !status }" @submit.prevent="submit">
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

            <button type="submit" class="btn-primario" :disabled="form.processing">
                {{ form.processing ? 'A enviar...' : 'Enviar ligação de recuperação' }}
            </button>

            <p class="m-0 text-center">
                <Link :href="route('login')" class="inline-flex min-h-11 items-center gap-1.5 text-[15px] font-bold text-verde no-underline transition hover:text-verde-escuro">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                    Voltar a entrar
                </Link>
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
