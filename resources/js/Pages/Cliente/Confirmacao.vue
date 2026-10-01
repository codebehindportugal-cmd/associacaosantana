<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    token: String,
    pedido: Object,
    items: Array,
});
</script>

<template>
    <main class="flex min-h-screen flex-col bg-fundo font-sans tabular-nums text-tinta">
        <header class="bg-escuro text-white">
            <div class="mx-auto flex max-w-xl items-center justify-between gap-3 px-4 py-3.5">
                <div class="flex min-w-0 flex-col gap-0.5">
                    <span class="text-xs font-bold uppercase tracking-[.08em] text-[#8FD3B5]">ARDC Santana</span>
                    <span class="truncate text-[26px] font-extrabold leading-none">{{ pedido.mesa }}</span>
                </div>
                <Link
                    v-if="pedido.disponivel"
                    :href="route('cliente.chamar.show', token)"
                    class="flex h-12 shrink-0 items-center gap-2 rounded-full bg-laranja px-4 text-base font-extrabold text-white"
                >
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                    Chamar
                </Link>
            </div>
        </header>

        <div class="mx-auto flex w-full max-w-xl flex-1 flex-col gap-4 px-4 py-5">
            <section class="flex flex-col items-center gap-3 rounded-[14px] border border-linha bg-white px-5 py-7 text-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-verde-claro2 text-verde">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg>
                </div>
                <h1 class="text-[26px] font-extrabold leading-tight">O seu pedido foi enviado para a cozinha</h1>
                <p class="text-[17px] font-semibold text-suave">{{ pedido.mesa }}</p>
            </section>

            <section class="flex flex-col gap-3 rounded-[14px] border border-linha bg-white px-4 py-[18px]">
                <h2 class="text-xl font-extrabold">Produtos enviados</h2>
                <div class="flex items-start gap-2.5 rounded-xl bg-laranja-claro px-3.5 py-3 text-[15px] font-bold leading-snug text-laranja-texto">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mt-px shrink-0"><circle cx="12" cy="12" r="10" /><path d="M12 8v4M12 16h.01" /></svg>
                    <span>Se se enganou no pedido, chame um funcionário para ajudar.</span>
                </div>
                <div v-if="!items?.length" class="rounded-xl bg-fundo p-4 text-center text-[15px] font-semibold text-suave">
                    Ainda não foram enviados produtos.
                </div>
                <div v-else class="flex flex-col">
                    <div
                        v-for="item in items"
                        :key="item.id"
                        class="flex items-center justify-between gap-3 border-b border-linha-fraca py-3 last:border-b-0"
                    >
                        <div class="flex min-w-0 flex-col gap-1">
                            <span class="text-lg font-extrabold">{{ item.nome }}</span>
                            <span v-if="item.observacoes" class="text-[15px] font-semibold text-suave">{{ item.observacoes }}</span>
                        </div>
                        <span class="flex h-9 min-w-12 shrink-0 items-center justify-center rounded-full bg-escuro px-2.5 text-base font-extrabold text-white">{{ item.quantidade }}x</span>
                    </div>
                </div>
            </section>
        </div>

        <footer class="sticky bottom-0 border-t border-linha bg-white shadow-[0_-6px_18px_rgba(22,32,28,.08)]">
            <div class="mx-auto max-w-xl px-4 pb-5 pt-3">
                <Link
                    v-if="pedido.disponivel"
                    :href="route('cliente.mesa', token)"
                    class="flex h-[60px] items-center justify-center gap-2 rounded-xl bg-verde text-[19px] font-extrabold text-white hover:bg-verde-escuro"
                >
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                    Adicionar mais itens
                </Link>
                <div v-else class="rounded-xl bg-laranja-claro p-4 text-center text-[15px] font-bold text-laranja-texto">
                    Este pedido já não permite adicionar mais itens.
                </div>
            </div>
        </footer>
    </main>
</template>
