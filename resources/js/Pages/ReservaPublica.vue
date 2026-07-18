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
    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-950 p-5 text-white">
        <div class="w-full max-w-sm rounded-2xl bg-gray-900 p-6 shadow-2xl">

            <!-- Cabeçalho -->
            <div class="mb-6 text-center">
                <div class="mb-2 text-4xl">🎉</div>
                <h1 class="text-2xl font-black">Associação de Santana</h1>
                <p class="mt-1 text-sm font-bold text-gray-400">Reserva de</p>
                <p class="mt-0.5 text-xl font-black text-amber-400">{{ reserva.nome }}</p>
            </div>

            <!-- Info da reserva -->
            <div class="mb-6 rounded-xl bg-gray-800 p-4 text-sm font-bold">
                <div class="flex items-center gap-2 text-gray-300">
                    <span>📅</span>
                    <span class="capitalize">{{ dataFormatada(reserva.data) }}</span>
                </div>
                <div class="mt-2 flex items-center gap-2 text-gray-300">
                    <span>🕐</span>
                    <span>{{ reserva.hora }}</span>
                </div>
                <div class="mt-2 flex items-center gap-2 text-gray-300">
                    <span>👥</span>
                    <span>{{ reserva.pessoas }} {{ reserva.pessoas === 1 ? 'pessoa' : 'pessoas' }}</span>
                </div>
            </div>

            <!-- Estado: não suportado -->
            <div v-if="estado === 'nao_suportado'" class="rounded-xl bg-red-900/50 p-4 text-center text-sm font-bold text-red-300">
                O teu browser não suporta notificações push.<br>
                Aguarda a chamada no local.
            </div>

            <!-- Estado: subscrito -->
            <div v-else-if="estado === 'subscrito'" class="text-center">
                <div class="mb-4 rounded-xl bg-emerald-900/50 p-4">
                    <div class="text-3xl">🔔</div>
                    <p class="mt-2 font-black text-emerald-400">Notificações ativas!</p>
                    <p class="mt-1 text-sm font-bold text-emerald-300">
                        Receberás uma notificação quando a tua vez chegar.
                    </p>
                </div>
                <button
                    class="mt-2 w-full rounded-xl bg-gray-700 py-3 text-sm font-bold text-gray-400 hover:bg-gray-600"
                    @click="desativarNotificacoes"
                >
                    Desativar notificações
                </button>
            </div>

            <!-- Estado: a subscrever -->
            <div v-else-if="estado === 'a_subscrever'" class="text-center">
                <div class="text-3xl">⏳</div>
                <p class="mt-2 font-bold text-gray-300">A ativar notificações...</p>
            </div>

            <!-- Estado: erro -->
            <div v-else-if="estado === 'erro'" class="text-center">
                <div class="mb-3 rounded-xl bg-red-900/50 p-4 text-sm font-bold text-red-300">
                    {{ mensagem }}
                </div>
                <button
                    class="w-full rounded-xl bg-amber-500 py-4 text-lg font-black text-gray-950"
                    @click="ativarNotificacoes"
                >
                    Tentar novamente
                </button>
            </div>

            <!-- Estado: início -->
            <div v-else class="text-center">
                <p class="mb-4 text-sm font-bold text-gray-400">
                    Ativa as notificações e avisa-te quando for a tua vez, mesmo que não estejas a olhar para o telemóvel.
                </p>
                <button
                    class="w-full rounded-xl bg-amber-500 py-4 text-lg font-black text-gray-950 active:scale-95 transition-transform"
                    @click="ativarNotificacoes"
                >
                    🔔 Notificar-me quando for chamado
                </button>
                <p class="mt-3 text-xs font-bold text-gray-500">
                    Só funciona enquanto tiveres o browser aberto.<br>
                    Não guardamos nenhum dado pessoal.
                </p>
            </div>

        </div>
    </div>
</template>
