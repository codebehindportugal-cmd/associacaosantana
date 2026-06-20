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
    remember: false,
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

        <h2 class="mb-6 text-xl font-bold text-stone-800">Entrar na plataforma</h2>

        <div v-if="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-800">
            {{ status }}
        </div>

        <!-- Acessos rápidos -->
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
            <div class="mb-3 text-xs font-bold uppercase tracking-wide text-amber-700">Acessos rápidos</div>
            <div class="grid gap-2 sm:grid-cols-4">
                <Link :href="route('pos.login', { tipo: 'restaurante' })" class="rounded-md bg-stone-800 px-3 py-2 text-center text-sm font-bold text-white transition hover:bg-stone-700">Restaurante</Link>
                <Link :href="route('pos.login', { tipo: 'bar' })" class="rounded-md bg-stone-800 px-3 py-2 text-center text-sm font-bold text-white transition hover:bg-stone-700">Bares</Link>
                <Link :href="route('pos.login', { tipo: 'cotas' })" class="rounded-md bg-amber-600 px-3 py-2 text-center text-sm font-bold text-white transition hover:bg-amber-700">Cotas</Link>
                <Link :href="route('pos.login', { tipo: 'cafe' })" class="rounded-md bg-stone-800 px-3 py-2 text-center text-sm font-bold text-white transition hover:bg-stone-700">Café</Link>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-stone-700">Email</span>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-stone-900 shadow-sm transition focus:border-amber-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/30"
                >
                <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-stone-700">Password</span>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-stone-900 shadow-sm transition focus:border-amber-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/30"
                >
                <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
            </label>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-stone-600">
                    <input type="checkbox" name="remember" v-model="form.remember" class="rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                    Manter sessão
                </label>
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-semibold text-amber-700 hover:text-amber-900 transition">
                    Esqueceu a password?
                </Link>
            </div>

            <button
                type="submit"
                class="w-full rounded-md bg-amber-600 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-amber-700 disabled:opacity-50"
                :disabled="form.processing"
            >
                {{ form.processing ? 'A entrar...' : 'Entrar' }}
            </button>
        </form>
    </GuestLayout>
</template>
