<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
defineProps({ socios: Object, filters: Object });

const eliminarSocio = (socio) => {
    if (confirm(`Eliminar o sócio ${socio.nome}?`)) {
        router.delete(route('socios.destroy', socio.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between"><h1 class="text-2xl font-bold">Sócios</h1><Link :href="route('socios.create')" class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white">Novo sócio</Link></div>
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="w-full min-w-[580px] text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-3">Número</th><th>Nome</th><th>Telefone</th><th>Cota</th><th></th></tr></thead>
                <tbody><tr v-for="socio in socios.data" :key="socio.id" class="border-t"><td class="p-3">{{ socio.numero_socio }}</td><td>{{ socio.nome }}</td><td>{{ socio.telefone }}</td><td><span class="rounded px-2 py-1 text-xs" :class="socio.cota_em_dia ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'">{{ socio.cota_em_dia ? 'Em dia' : 'Em atraso' }}</span></td><td class="space-x-3"><Link :href="route('socios.show', socio.id)" class="font-semibold text-emerald-700">Ver</Link><Link :href="route('socios.edit', socio.id)" class="font-semibold text-slate-700">Editar</Link><button type="button" class="font-semibold text-red-700" @click="eliminarSocio(socio)">Eliminar</button></td></tr></tbody>
            </table>
        </div>
    </AppLayout>
</template>
