<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const utilizador = computed(() => page.props.auth?.user ?? {});
const perfil = computed(() => (page.props.auth?.roles ?? [])[0] ?? null);
const iniciais = computed(() => String(utilizador.value.name ?? '')
    .trim().split(/\s+/).filter(Boolean)
    .filter((_, i, a) => i === 0 || i === a.length - 1)
    .map((p) => p[0]).join('').toUpperCase());
</script>

<template>
    <Head title="O meu perfil" />

    <AppLayout>
        <div class="bg-fundo px-4 py-6 font-sans text-tinta sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-[760px] flex-col gap-5">
                <div class="flex items-center gap-4">
                    <span class="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-verde text-xl font-extrabold text-white" aria-hidden="true">{{ iniciais }}</span>
                    <div class="min-w-0">
                        <h1 class="text-[30px] font-extrabold leading-tight">O meu perfil</h1>
                        <p class="truncate text-[15px] text-suave">
                            {{ utilizador.name }} · {{ utilizador.email }}<span v-if="perfil"> · perfil <strong class="font-bold text-tinta">{{ perfil }}</strong></span>
                        </p>
                    </div>
                </div>

                <div class="cartao">
                    <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
                </div>

                <div class="cartao">
                    <UpdatePasswordForm />
                </div>

                <div class="cartao">
                    <DeleteUserForm />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.cartao { @apply rounded-[14px] border border-linha bg-white p-4 sm:p-6; }
</style>
