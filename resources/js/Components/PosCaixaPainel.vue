<script setup>
// Painel da caixa no POS (bar e restaurante): resumo por forma de pagamento,
// leitura sem fechar e fecho com contagem de notas e o PIN do posto.
import AvisoErros from '@/Components/AvisoErros.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    caixa: { type: Object, default: null },
    titulo: { type: String, default: '' },
    rotaLeitura: { type: String, required: true },
    rotaFechar: { type: String, required: true },
    // Rotulo das vendas: senhas no bar, contas no restaurante
    rotuloVendas: { type: String, default: 'senhas' },
    // Posto sem internet com vendas por enviar: nao se fecha nem se tira leitura
    bloqueio: { type: String, default: '' },
});
const emit = defineEmits(['fechar']);

const NOTAS = ['500', '200', '100', '50', '20', '10', '5', '2', '1', '0.5', '0.2', '0.1', '0.05', '0.02', '0.01'];
const rotuloNota = (v) => (Number(v) >= 1 ? `${v} €` : `${Math.round(Number(v) * 100)} c`);
const eur = (v) => Number(v ?? 0).toLocaleString('pt-PT', { useGrouping: 'always', minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

const contarNotas = ref(false);
const form = useForm({ valor_contado: '', contagem: {}, observacoes_fecho: '', pin: '' });
const totalNotas = computed(() => Math.round(NOTAS.reduce((s, v) => s + Number(form.contagem[v] || 0) * Number(v), 0) * 100) / 100);
watch(totalNotas, (t) => { if (contarNotas.value) form.valor_contado = t.toFixed(2); });
const diferenca = computed(() => (form.valor_contado === '' || !props.caixa) ? null : Math.round((Number(form.valor_contado) - Number(props.caixa.esperado_caixa)) * 100) / 100);

// Dados sempre frescos ao abrir (sem rede fica o que ja estava)
if (navigator.onLine) router.reload({ only: ['caixa'] });

const aLer = ref(false);
const leitura = () => router.post(route(props.rotaLeitura), {}, {
    preserveScroll: true,
    onStart: () => (aLer.value = true),
    onFinish: () => (aLer.value = false),
    onSuccess: () => emit('fechar'),
});
const fecharCaixa = () => {
    if (props.bloqueio) return;
    form.transform((d) => ({ ...d, contagem: contarNotas.value ? Object.fromEntries(Object.entries(d.contagem).filter(([, q]) => Number(q) > 0)) : {} }))
        .post(route(props.rotaFechar), { preserveScroll: true, onSuccess: () => emit('fechar') });
};

// Sem impressora do agente (restaurante): abre o talao para imprimir no browser
const page = usePage();
watch(() => page.props.flash?.talaoCaixa, (url) => { if (url) router.visit(url); });
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 font-sans text-tinta tabular-nums sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="titulo-caixa-pos" @click.self="emit('fechar')">
        <div class="max-h-[94dvh] w-full max-w-xl overflow-y-auto rounded-t-[16px] bg-white p-5 sm:rounded-[16px]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="titulo-caixa-pos" class="text-xl font-extrabold">Caixa · {{ caixa?.ponto || titulo }}</h2>
                    <p v-if="caixa" class="text-sm text-suave">Aberta às {{ caixa.aberta_as }} · {{ caixa.pedidos }} {{ rotuloVendas }}</p>
                </div>
                <button type="button" class="h-11 rounded-[10px] border border-linha-forte px-4 text-[15px] font-bold" @click="emit('fechar')">Fechar janela</button>
            </div>

            <template v-if="caixa">
                <div class="mt-4 grid grid-cols-2 gap-2 text-[15px]">
                    <div class="rounded-[10px] bg-fundo p-3"><div class="text-sm text-suave">Dinheiro</div><strong class="text-xl">{{ eur(caixa.por_metodo.dinheiro) }}</strong></div>
                    <div class="rounded-[10px] bg-fundo p-3"><div class="text-sm text-suave">MB WAY</div><strong class="text-xl">{{ eur(caixa.por_metodo.mbway) }}</strong></div>
                    <div v-if="Number(caixa.por_metodo.contactless)" class="rounded-[10px] bg-fundo p-3"><div class="text-sm text-suave">Contactless</div><strong class="text-xl">{{ eur(caixa.por_metodo.contactless) }}</strong></div>
                    <div v-if="Number(caixa.por_metodo.multibanco)" class="rounded-[10px] bg-fundo p-3"><div class="text-sm text-suave">Multibanco</div><strong class="text-xl">{{ eur(caixa.por_metodo.multibanco) }}</strong></div>
                    <div class="rounded-[10px] bg-fundo p-3"><div class="text-sm text-suave">Fundo de maneio</div><strong class="text-xl">{{ eur(caixa.fundo_maneio) }}</strong></div>
                </div>
                <p v-if="bloqueio" role="alert" class="mt-3 rounded-[10px] bg-laranja-claro p-3 text-[15px] font-bold text-laranja-texto">{{ bloqueio }}</p>
                <button type="button" class="mt-3 h-12 w-full rounded-[10px] border border-linha-forte bg-white text-base font-bold disabled:opacity-45" :disabled="aLer || !!bloqueio" @click="leitura">{{ aLer ? 'A imprimir…' : 'Imprimir leitura (sem fechar)' }}</button>

                <form class="mt-5 flex flex-col gap-3 rounded-[12px] bg-perigo-claro p-4" @submit.prevent="fecharCaixa">
                    <h3 class="text-lg font-extrabold text-perigo-texto">Fechar caixa</h3>
                    <label class="flex flex-col gap-1 text-sm font-bold text-suave">Dinheiro contado na gaveta
                        <span class="flex h-14 items-center rounded-[10px] border border-linha-forte bg-white px-3">
                            <input v-model="form.valor_contado" type="number" min="0" step="0.01" inputmode="decimal" required placeholder="Contar a gaveta" class="w-full border-0 bg-transparent p-0 text-2xl font-extrabold text-tinta focus:ring-0">
                            <span class="font-bold text-suave-2">€</span>
                        </span>
                    </label>
                    <button type="button" class="self-start text-sm font-bold text-verde underline" :aria-expanded="contarNotas" @click="contarNotas = !contarNotas">{{ contarNotas ? 'Esconder contagem' : 'Contar notas e moedas' }}</button>
                    <div v-if="contarNotas" class="grid grid-cols-3 gap-1.5 sm:grid-cols-5">
                        <label v-for="v in NOTAS" :key="v" class="flex flex-col rounded-[8px] border border-linha-forte bg-white px-2 py-1 text-xs font-bold text-suave">
                            {{ rotuloNota(v) }}
                            <input v-model.number="form.contagem[v]" type="number" min="0" step="1" inputmode="numeric" placeholder="0" :aria-label="`Quantidade de ${rotuloNota(v)}`" class="h-10 w-full border-0 p-0 text-xl font-extrabold text-tinta focus:ring-0">
                        </label>
                    </div>
                    <p v-if="diferenca !== null" class="text-base font-bold" :class="diferenca === 0 ? 'text-tinta' : diferenca > 0 ? 'text-verde' : 'text-perigo'">
                        {{ diferenca === 0 ? 'Bate certo.' : diferenca > 0 ? `Sobram ${eur(diferenca)}` : `Faltam ${eur(-diferenca)}` }}
                    </p>
                    <label class="flex flex-col gap-1 text-sm font-bold text-suave">Observações
                        <input v-model="form.observacoes_fecho" class="h-12 rounded-[10px] border-linha-forte bg-white text-base text-tinta" placeholder="Opcional">
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-bold text-suave">PIN do posto
                        <input v-model="form.pin" type="password" inputmode="numeric" autocomplete="off" required class="h-12 rounded-[10px] border-linha-forte bg-white text-lg text-tinta">
                    </label>
                    <AvisoErros :errors="form.errors" class="!p-2" />
                    <button class="h-14 rounded-[10px] bg-perigo text-lg font-extrabold text-white disabled:opacity-45" :disabled="form.processing || !!bloqueio">{{ form.processing ? 'A fechar…' : 'Fechar caixa e imprimir' }}</button>
                </form>
            </template>
            <p v-else class="mt-4 text-suave">A caixa deste ponto não está aberta. Abre-a no backoffice, em Caixa diária.</p>
        </div>
    </div>
</template>
