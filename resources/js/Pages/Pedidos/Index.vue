<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({ pedidos: Object, filters: Object, mesas: Array });

const formatarPreco = (valor) => `${Number(valor ?? 0).toFixed(2)}€`;
</script>

<template>
    <AppLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold">Pedidos</h1>
            <Link :href="route('pedidos.create')" class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white">Novo pedido</Link>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-3">Mesa</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="pedido in pedidos.data" :key="pedido.id" class="border-t">
                        <td class="p-3 font-semibold">{{ pedido.mesa?.designacao ?? 'Para levar' }}</td>
                        <td>{{ pedido.estado }}</td>
                        <td>{{ formatarPreco(pedido.total) }}</td>
                        <td class="pr-3 text-right">
                            <Link :href="route('pedidos.show', pedido.id)" class="font-semibold text-emerald-700">Abrir</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
