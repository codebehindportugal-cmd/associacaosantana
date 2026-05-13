<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';
const props = defineProps({ pedido: Object });
const data = () => new Date(props.pedido.created_at).toLocaleString('pt-PT');
const euros = (valor) => Number(valor ?? 0).toFixed(2) + '€';
onMounted(() => setTimeout(() => window.print(), 300));
</script>

<template>
    <main class="min-h-screen bg-slate-100 p-4 text-slate-950 print:bg-white print:p-0">
        <section class="mx-auto max-w-[300px] bg-white p-4 font-mono shadow print:shadow-none">
            <h1 class="text-center text-lg font-black">Associação de Santana</h1>
            <div class="text-center font-black">BAR</div>
            <div class="my-4 border-y border-dashed border-slate-400 py-4 text-center"><div class="text-xs uppercase">Número da senha</div><div class="text-6xl font-black">#{{ pedido.numero_senha || pedido.id }}</div></div>
            <div class="space-y-2 text-lg font-black">
                <div v-for="item in pedido.items" :key="item.id" class="flex justify-between gap-2">
                    <span>{{ item.produto?.nome }}</span>
                    <span>{{ item.quantidade }} un.</span>
                </div>
            </div>
            <div class="mt-4 border-t border-dashed border-slate-400 pt-3 text-sm">
                <div class="flex justify-between"><span>Total</span><strong>{{ euros(pedido.total) }}</strong></div>
                <div class="flex justify-between"><span>Recebido</span><strong>{{ euros(pedido.valor_recebido) }}</strong></div>
                <div class="flex justify-between"><span>Troco</span><strong>{{ euros(pedido.troco) }}</strong></div>
                <div v-if="Number(pedido.doacao || 0) > 0" class="flex justify-between"><span>Doação</span><strong>{{ euros(pedido.doacao) }}</strong></div>
            </div>
            <div class="mt-4 text-center text-xs">{{ data() }}</div><div class="mt-3 text-center font-black">Obrigado!</div>
        </section>
        <div class="mx-auto mt-4 flex max-w-[300px] gap-2 print:hidden"><button class="flex-1 rounded-xl bg-slate-900 px-4 py-3 font-black text-white" @click="window.print()">Imprimir</button><Link :href="route('bar.prepago')" class="flex-1 rounded-xl bg-emerald-600 px-4 py-3 text-center font-black text-white">Novo Pedido</Link></div>
    </main>
</template>
