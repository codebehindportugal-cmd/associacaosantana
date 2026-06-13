<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    token: String,
    pedido: Object,
    items: Array,
});
</script>

<template>
    <main class="min-h-screen bg-slate-950 px-4 py-6 text-white">
        <section class="mx-auto max-w-xl">
            <div class="rounded-3xl bg-white p-6 text-center text-slate-950 shadow-sm">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-3xl font-black text-emerald-700">✓</div>
                <h1 class="text-2xl font-black">O seu pedido foi enviado para a cozinha</h1>
                <p class="mt-2 text-sm font-semibold text-slate-500">Mesa {{ pedido.mesa }}</p>
            </div>

            <div class="mt-5 rounded-3xl bg-white p-5 text-slate-950 shadow-sm">
                <h2 class="mb-3 text-lg font-black">Itens enviados nesta sessao</h2>
                <div v-if="!items?.length" class="rounded-2xl bg-slate-100 p-4 text-center text-sm font-semibold text-slate-500">
                    Ainda nao foram enviados itens neste telemovel.
                </div>
                <div v-else class="grid gap-2">
                    <div v-for="(item, index) in items" :key="`${item.nome}-${index}`" class="flex items-center justify-between gap-3 rounded-2xl bg-slate-100 p-3">
                        <div>
                            <div class="font-black">{{ item.nome }}</div>
                            <div class="text-xs font-semibold text-slate-500">{{ item.hora }}</div>
                            <div v-if="item.observacoes" class="mt-1 text-sm font-semibold text-slate-700">{{ item.observacoes }}</div>
                        </div>
                        <div class="rounded-full bg-slate-950 px-3 py-1 text-sm font-black text-white">{{ item.quantidade }}x</div>
                    </div>
                </div>
            </div>

            <Link
                v-if="pedido.disponivel"
                :href="route('cliente.mesa', token)"
                class="mt-5 block rounded-2xl bg-emerald-500 px-5 py-4 text-center text-lg font-black text-slate-950"
            >
                Adicionar mais itens
            </Link>
            <div v-else class="mt-5 rounded-2xl bg-amber-500/10 p-4 text-center text-sm font-bold text-amber-100">
                Este pedido ja nao permite adicionar mais itens.
            </div>
        </section>
    </main>
</template>
