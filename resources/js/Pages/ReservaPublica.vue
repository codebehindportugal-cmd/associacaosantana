<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    reserva: Object,
    vapidPublicKey: String,
});

const estado = ref('inicio'); // inicio | subscrito | nao_suportado | erro
const mensagem = ref('');

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    return new Uint8Array([...rawData].map(c => c.charCodeAt(0)));
}

onMounted(() => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        estado.value = 'nao_suportado';
        return;
    }
    if (props.reserva.tem_push) {
        estado.value = 'subscrito';
    }
});

const ativarNotificacoes = async () => {
    if (!props.vapidPublicKey) {
        mensagem.value = 'Configuração em falta. Contacta o administrador.';
        estado.value = 'erro';
        return;
    }

    estado.value = 'a_subscrever';

    try {
        // Registar service worker
        const reg = await navigator.serviceWorker.register('/sw.js');
        await navigator.serviceWorker.ready;

        // Pedir permissão
        const permissao = await Notification.requestPermission();
        if (permissao !== 'granted') {
            mensagem.value = 'Permissão negada. Podes ativar nas definições do browser.';
            estado.value = 'erro';
            return;
        }

        // Subscrever push
        const subscription = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(props.vapidPublicKey),
        });

        // Enviar para o servidor
        const res = await fetch(`/reserva/${props.reserva.token}/subscrever`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(subscription.toJSON()),
        });

        if (!res.ok) throw new Error('Erro ao guardar subscrição');

        estado.value = 'subscrito';
    } catch (e) {
        console.error(e);
        mensagem.value = 'Não foi possível ativar. Tenta novamente.';
        estado.value = 'erro';
    }
};

const desativarNotificacoes = async () => {
    try {
        await fetch(`/reserva/${props.reserva.token}/dessubscrever`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
        });
        estado.value = 'inicio';
    } catch (e) {
        console.error(e);
    }
};

const dataFormatada = (data) => {
    if (!data) return '';
    return new Date(`${data}T00:00:00`).toLocaleDateString('pt-PT', {
        weekday: 'long', day: '2-digit', month: 'long',
    });
};
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-fundo p-4 font-sans text-tinta tabular-nums">
        <div class="w-full max-w-[440px] space-y-4">

            <!-- Cabeçalho + info da reserva -->
            <div class="overflow-hidden rounded-[14px] border border-linha bg-white">
                <div class="px-6 pb-5 pt-6 text-center">
                    <span class="mx-auto flex h-[52px] w-[52px] items-center justify-center rounded-full bg-verde-claro text-verde">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M3 10h18M12 10v10" /></svg>
                    </span>
                    <h1 class="m-0 mt-3 text-2xl font-extrabold">Associação de Santana</h1>
                    <p class="m-0 mt-1 text-sm font-semibold text-suave">Reserva de</p>
                    <p class="m-0 mt-0.5 text-[26px] font-extrabold leading-tight text-verde">{{ reserva.nome }}</p>
                </div>

                <div class="border-t border-linha px-6 py-2">
                    <div class="flex min-h-[52px] items-center gap-3 border-b border-linha-fraca">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-suave"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                        <span class="text-lg font-bold first-letter:uppercase">{{ dataFormatada(reserva.data) }}</span>
                    </div>
                    <div class="flex min-h-[52px] items-center gap-3 border-b border-linha-fraca">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-suave"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                        <span class="text-lg font-bold">{{ reserva.hora }}</span>
                    </div>
                    <div class="flex min-h-[52px] items-center gap-3">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0 text-suave"><circle cx="9" cy="8" r="3" /><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" /><circle cx="17" cy="9" r="2.5" /><path d="M16 14.2c2.8.3 5 2.7 5 5.8" /></svg>
                        <span class="text-lg font-bold">{{ reserva.pessoas }} {{ reserva.pessoas === 1 ? 'pessoa' : 'pessoas' }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-[14px] border border-linha bg-white p-6">
                <!-- Estado: não suportado -->
                <div v-if="estado === 'nao_suportado'" class="rounded-[10px] bg-perigo-claro p-4 text-center text-[15px] font-bold text-perigo-texto">
                    O teu browser não suporta notificações push.<br>
                    Aguarda a chamada no local.
                </div>

                <!-- Estado: subscrito -->
                <div v-else-if="estado === 'subscrito'" class="text-center">
                    <div class="flex flex-col items-center rounded-[10px] bg-verde-claro p-5">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-verde text-white"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg></span>
                        <p class="m-0 mt-3 text-lg font-extrabold text-verde-escuro">Notificações ativas!</p>
                        <p class="m-0 mt-1 text-[15px] font-semibold text-verde-escuro">
                            Receberás uma notificação quando a tua vez chegar.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="mt-3 h-12 w-full rounded-[10px] border border-linha-forte bg-white text-[15px] font-bold text-suave transition hover:bg-fundo"
                        @click="desativarNotificacoes"
                    >
                        Desativar notificações
                    </button>
                </div>

                <!-- Estado: a subscrever -->
                <div v-else-if="estado === 'a_subscrever'" class="flex flex-col items-center py-4 text-center">
                    <span class="h-10 w-10 animate-spin rounded-full border-4 border-verde-claro2 border-t-verde" aria-hidden="true"></span>
                    <p class="m-0 mt-3 text-base font-bold text-suave">A ativar notificações...</p>
                </div>

                <!-- Estado: erro -->
                <div v-else-if="estado === 'erro'" class="text-center">
                    <div class="mb-4 rounded-[10px] bg-perigo-claro p-4 text-[15px] font-bold text-perigo-texto" role="alert">
                        {{ mensagem }}
                    </div>
                    <button
                        type="button"
                        class="h-[60px] w-full rounded-[10px] bg-verde text-lg font-extrabold text-white transition hover:bg-verde-escuro"
                        @click="ativarNotificacoes"
                    >
                        Tentar novamente
                    </button>
                </div>

                <!-- Estado: início -->
                <div v-else class="text-center">
                    <p class="m-0 mb-5 text-base text-suave">
                        Ativa as notificações e avisa-te quando for a tua vez, mesmo que não estejas a olhar para o telemóvel.
                    </p>
                    <button
                        type="button"
                        class="flex min-h-[60px] w-full items-center justify-center gap-2.5 rounded-[10px] bg-verde px-4 py-3 text-lg font-extrabold text-white transition hover:bg-verde-escuro active:scale-[0.98]"
                        @click="ativarNotificacoes"
                    >
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                        Notificar-me quando for chamado
                    </button>
                    <p class="m-0 mt-4 text-[13px] text-suave-2">
                        Só funciona enquanto tiveres o browser aberto.<br>
                        Não guardamos nenhum dado pessoal.
                    </p>
                </div>
            </div>

        </div>
    </div>
</template>
