<script setup>
// Rede de segurança para mensagens: mostra no topo do ecrã qualquer erro de
// validação (page.props.errors) ou aviso de sessão (flash.error / flash.success)
// que a página não esteja a mostrar. Se o texto já está visível na página
// (por baixo do campo, num aviso da página ou no layout), não repete.
import { nextTick, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const avisos = ref([]);
let id = 0;
let temporizadores = [];

const textoVisivelNaPagina = () => document.getElementById('app')?.innerText ?? '';

const achatar = (valor) => {
    if (!valor) return [];
    if (typeof valor === 'string') return [valor];
    if (Array.isArray(valor)) return valor.flatMap(achatar);
    if (typeof valor === 'object') return Object.values(valor).flatMap(achatar);
    return [];
};

const fechar = (aviso) => {
    avisos.value = avisos.value.filter((a) => a.id !== aviso.id);
};

const atualizar = async () => {
    await nextTick();
    // Dar tempo à página para desenhar os erros junto dos campos
    await new Promise((r) => setTimeout(r, 80));
    const visivel = textoVisivelNaPagina();
    const novos = [];
    const erros = [...new Set(achatar(page.props.errors).map((m) => String(m).trim()).filter(Boolean))];
    const flashErro = page.props.flash?.error ? [String(page.props.flash.error).trim()] : [];
    const flashOk = page.props.flash?.success ? [String(page.props.flash.success).trim()] : [];

    const errosEmFalta = [...flashErro, ...erros].filter((msg) => !visivel.includes(msg));
    if (errosEmFalta.length) novos.push({ id: ++id, tipo: 'erro', msgs: errosEmFalta });
    for (const msg of flashOk) {
        if (!visivel.includes(msg)) novos.push({ id: ++id, tipo: 'ok', msgs: [msg] });
    }

    temporizadores.forEach(clearTimeout);
    temporizadores = [];
    avisos.value = novos;
    for (const aviso of novos.filter((a) => a.tipo === 'ok')) {
        temporizadores.push(setTimeout(() => fechar(aviso), 4000));
    }
};

watch(() => [page.props.errors, page.props.flash?.error, page.props.flash?.success], atualizar, { immediate: true, flush: 'post' });
router.on('start', () => { avisos.value = []; });
</script>

<template>
    <Teleport to="body">
        <div v-if="avisos.length" class="pointer-events-none fixed inset-x-0 top-3 z-[100] flex flex-col items-center gap-2 px-3">
            <div
                v-for="aviso in avisos"
                :key="aviso.id"
                :role="aviso.tipo === 'erro' ? 'alert' : 'status'"
                class="pointer-events-auto flex w-full max-w-xl items-start gap-3 rounded-[14px] border px-4 py-3 font-sans text-[15px] font-bold shadow-lg"
                :class="aviso.tipo === 'erro' ? 'border-[#F0C9C2] bg-perigo-claro text-perigo-texto' : 'border-verde-claro2 bg-verde-claro text-verde-escuro'"
            >
                <svg v-if="aviso.tipo === 'erro'" class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l9.5 17h-19z" /><path d="M12 10v4M12 17.5v.01" /></svg>
                <svg v-else class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                <div class="flex-1">
                    <p v-if="aviso.msgs.length > 1" class="mb-1">Não foi possível concluir:</p>
                    <ul :class="aviso.msgs.length > 1 ? 'list-disc space-y-0.5 pl-5 font-semibold' : ''">
                        <li v-for="m in aviso.msgs" :key="m" :class="aviso.msgs.length > 1 ? '' : 'list-none'">{{ m }}</li>
                    </ul>
                </div>
                <button type="button" class="-my-1.5 -mr-2 grid h-9 w-9 shrink-0 place-items-center rounded-[10px] hover:bg-white/60" aria-label="Fechar aviso" @click="fechar(aviso)">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>
        </div>
    </Teleport>
</template>
