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

        <h2 class="login-heading mb-6 text-xl font-bold">Entrar na plataforma</h2>

        <div v-if="status" class="mb-4 rounded-lg border p-3 text-sm font-medium" style="border-color:rgba(52,211,153,0.2);background:rgba(52,211,153,0.08);color:#34d399">
            {{ status }}
        </div>

        <div class="mb-6">
            <Link :href="route('pos.login')" class="pos-btn block w-full rounded-xl py-3 text-center text-sm font-bold tracking-wide">
                Aceder ao POS / Ecrãs
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold" style="color:rgba(255,253,248,0.65)">Email</span>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="field-input w-full rounded-md px-3 py-2.5 text-sm"
                />
                <p v-if="form.errors.email" class="mt-1 text-xs" style="color:#f87171">{{ form.errors.email }}</p>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold" style="color:rgba(255,253,248,0.65)">Password</span>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="field-input w-full rounded-md px-3 py-2.5 text-sm"
                />
                <p v-if="form.errors.password" class="mt-1 text-xs" style="color:#f87171">{{ form.errors.password }}</p>
            </label>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm" style="color:rgba(255,253,248,0.55)">
                    <input type="checkbox" name="remember" v-model="form.remember" class="rounded" style="accent-color:#D4AF37" />
                    Manter sessão
                </label>
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-semibold transition" style="color:#C9A84C">
                    Esqueceu a password?
                </Link>
            </div>

            <button
                type="submit"
                class="submit-btn w-full rounded-md py-2.5 text-sm font-bold shadow-sm transition"
                :disabled="form.processing"
            >
                {{ form.processing ? 'A entrar...' : 'Entrar' }}
            </button>
        </form>
    </GuestLayout>
</template>

<style scoped>
.login-heading {
    color: #fffdf8;
    font-family: "Playfair Display", "Fraunces", Georgia, serif;
}
.pos-btn {
    background: rgba(212, 175, 55, 0.1);
    border: 1px solid rgba(212, 175, 55, 0.35);
    color: #D4AF37;
    transition: background 150ms, box-shadow 150ms;
    letter-spacing: 0.05em;
}
.pos-btn:hover {
    background: rgba(212, 175, 55, 0.2);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.12);
}
.field-input {
    background: rgba(255, 253, 248, 0.05);
    border: 1px solid rgba(212, 175, 55, 0.2);
    color: #fffdf8;
    outline: none;
    transition: border-color 200ms, box-shadow 200ms;
}
.field-input:focus {
    border-color: rgba(212, 175, 55, 0.55);
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
}
.submit-btn {
    background: linear-gradient(135deg, #D4AF37 0%, #b8922a 100%);
    color: #0d0a05;
    font-weight: 800;
    box-shadow: 0 4px 20px rgba(212, 175, 55, 0.28);
}
.submit-btn:hover { box-shadow: 0 8px 28px rgba(212, 175, 55, 0.42); transform: translateY(-1px); }
.submit-btn:disabled { opacity: 0.5; }
</style>
