<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verificar email" />

        <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-verde-claro text-verde">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></svg>
        </span>
        <h2 class="m-0 text-[26px] font-extrabold leading-tight text-tinta">Confirme o seu email</h2>
        <p class="m-0 mt-2 text-[15px] leading-relaxed text-suave">Obrigado por se registar! Antes de começar, confirme o seu email clicando na ligação que acabámos de lhe enviar. Se não recebeu o email, enviamos-lhe outro.</p>

        <div class="mt-5">
        <div v-if="verificationLinkSent" class="mb-5 flex items-start gap-2.5 rounded-[10px] bg-verde-claro p-3.5 text-[15px] font-semibold text-verde-escuro" role="status">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="mt-px shrink-0"><path d="M5 12l5 5L19 7" /></svg>
            <span>Foi enviada uma nova ligação de verificação para o email que indicou no registo.</span>
        </div>
        </div>

        <form class="space-y-3" :class="{ 'mt-5': !verificationLinkSent }" @submit.prevent="submit">
            <button type="submit" class="btn-primario" :disabled="form.processing">
                {{ form.processing ? 'A enviar...' : 'Reenviar email de verificação' }}
            </button>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="flex h-12 w-full items-center justify-center gap-2 rounded-[10px] border border-linha-forte bg-white text-base font-bold text-tinta transition hover:bg-fundo"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5M21 12H9" /></svg>
                Sair
            </Link>
        </form>
    </GuestLayout>
</template>

<style scoped>
.rotulo { display: block; margin-bottom: 6px; font-size: 15px; font-weight: 700; color: #16201C; }
.campo {
    width: 100%;
    height: 50px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid #D5D9D3;
    background: #FFFFFF;
    color: #16201C;
    font-size: 16px;
}
.campo:focus { border-color: #0F6B4F; box-shadow: 0 0 0 3px rgb(15 107 79 / 0.15); outline: none; }
.campo-erro { border-color: #A3241A; }
.erro { margin: 6px 0 0; font-size: 14px; font-weight: 600; color: #A3241A; }
.btn-primario {
    display: flex; align-items: center; justify-content: center; width: 100%; height: 52px;
    border-radius: 10px; background: #0F6B4F; color: #FFFFFF; font-size: 17px; font-weight: 700;
    transition: background 150ms;
}
.btn-primario:hover { background: #0A4D39; }
.btn-primario:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
