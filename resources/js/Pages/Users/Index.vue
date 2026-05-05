<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';
defineProps({ users: Array, roles: Array });
const updateRole = (user, role) => useForm({ name: user.name, email: user.email, role }).put(route('users.update', user.id));
</script>

<template>
    <AppLayout>
        <h1 class="mb-6 text-2xl font-bold">Utilizadores</h1>
        <div class="overflow-hidden rounded-lg bg-white shadow-sm"><table class="w-full text-left text-sm"><tbody><tr v-for="user in users" :key="user.id" class="border-t"><td class="p-3">{{ user.name }}</td><td>{{ user.email }}</td><td><select class="rounded-md border-slate-300" :value="user.roles[0]?.name" @change="updateRole(user, $event.target.value)"><option v-for="role in roles">{{ role }}</option></select></td></tr></tbody></table></div>
    </AppLayout>
</template>
