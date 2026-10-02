<script setup>
import AvisoErros from '@/Components/AvisoErros.vue';
import PublicShell from '@/Components/PublicShell.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    eventos: Array,
    recaptchaSiteKey: String,
});

const page = usePage();
const eventoAberto = ref(props.eventos?.length === 1 ? props.eventos[0].id : null);
const sucesso = ref('');

const form = useForm({
    nome: '',
    telefone: '',
    email: '',
    num_adultos: 1,
    opcao: '',
    num_criancas: 0,
    idades_criancas: '',
    observacoes: '',
    pagar_online: false,
    recaptcha_token: '',
});

const abrir = (evento) => {
    eventoAberto.value = eventoAberto.value === evento.id ? null : evento.id;
    sucesso.value = '';
    form.reset();
    form.clearErrors();
    form.pagar_online = false;
    if (evento.opcoes?.length) form.opcao = '';
};

// reCAPTCHA v3 (só carrega se houver chave configurada)
onMounted(() => {
    if (!props.recaptchaSiteKey || document.getElementById('recaptcha-script')) return;
    const s = document.createElement('script');
    s.id = 'recaptcha-script';
    s.src = `https://www.google.com/recaptcha/api.js?render=${props.recaptchaSiteKey}`;
    document.head.appendChild(s);
});

const obterToken = () => new Promise((resolve) => {
    if (!props.recaptchaSiteKey || !window.grecaptcha) return resolve('');
    window.grecaptcha.ready(() => {
        window.grecaptcha.execute(props.recaptchaSiteKey, { action: 'inscricao' })
            .then(resolve)
            .catch(() => resolve(''));
    });
});

const euros = (v) => Number(v).toLocaleString('pt-PT', { useGrouping: 'always', style: 'currency', currency: 'EUR' });

const precoOpcao = (evento) => {
    if (!form.opcao) return null;
    const opcao = (evento.opcoes ?? []).find((o) => o.nome === form.opcao);
    return opcao?.preco ?? null;
};

const adultos = () => Math.max(0, Number(form.num_adultos || 0));
const criancas = (evento) => evento.pede_idades ? Math.max(0, Number(form.num_criancas || 0)) : 0;

const totalEstimado = (evento) => {
    const precoAdulto = precoOpcao(evento) ?? evento.preco;
    if (precoAdulto === null || precoAdulto === undefined) return null;
    const precoCrianca = evento.preco_crianca ?? precoAdulto;
    return adultos() * precoAdulto + criancas(evento) * precoCrianca;
};

const detalheTotal = (evento) => {
    const precoAdulto = precoOpcao(evento) ?? evento.preco;
    if (precoAdulto === null || precoAdulto === undefined) return '';
    const precoCrianca = evento.preco_crianca ?? precoAdulto;
    const partes = [];
    if (adultos()) partes.push(`${adultos()} × ${euros(precoAdulto)}`);
    if (criancas(evento)) partes.push(`${criancas(evento)} criança(s) × ${euros(precoCrianca)}`);
    return partes.join(' + ');
};

const infoPrecos = (evento) => {
    const partes = [];
    if (evento.preco !== null && !evento.opcoes?.some((o) => o.preco !== null)) partes.push(`${euros(evento.preco)} por pessoa`);
    if (evento.preco_crianca !== null) {
        const ate = evento.idade_crianca ? ` até aos ${evento.idade_crianca} anos` : '';
        partes.push(Number(evento.preco_crianca) === 0 ? `crianças${ate} grátis` : `crianças${ate}: ${euros(evento.preco_crianca)}`);
    }
    return partes.join(' · ');
};

// Botões − / + (só UI; o input numérico continua a funcionar)
const passo = (campo, delta, min, max) => {
    const atual = Number(form[campo] || 0);
    form[campo] = Math.min(max, Math.max(min, atual + delta));
};
const srcCartaz = (cartaz) => (cartaz.startsWith('http') ? cartaz : `/${cartaz.replace(/^\//, '')}`);

const submeter = async (evento) => {
    form.recaptcha_token = await obterToken();
    form.transform((data) => {
        const { num_adultos, ...resto } = data;
        const nCriancas = evento.pede_idades ? Number(data.num_criancas || 0) : 0;
        return {
            ...resto,
            num_pessoas: Math.max(1, Number(num_adultos || 0) + nCriancas),
            num_criancas: nCriancas,
        };
    }).post(route('inscricoes.store', evento.id), {
        preserveScroll: true,
        onSuccess: () => {
            sucesso.value = evento.id;
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Inscrições" />
    <PublicShell>
        <div class="mx-auto w-full max-w-[760px] px-4 pb-16 pt-10 sm:px-6 sm:pt-14">
            <h1 class="m-0 text-[clamp(32px,4vw,44px)] font-extrabold leading-[1.1] tracking-[-0.01em]">Inscrições</h1>
            <p class="m-0 mt-2 text-[17px] text-suave">Inscreve-te nos próximos eventos da Associação de Santana.</p>

            <!-- Mensagens do regresso do pagamento online (redirect com success / erro 'pagamento') -->
            <p v-if="page.props.flash?.success && !sucesso" role="status" class="m-0 mt-5 rounded-[10px] border border-verde-claro2 bg-verde-claro p-3.5 text-[15px] font-semibold text-verde-escuro">{{ page.props.flash.success }}</p>
            <AvisoErros :apenas="['pagamento']" class="mt-5 text-[15px]" />

            <div v-if="!eventos.length" class="mt-8 flex flex-col items-center rounded-[14px] border border-linha bg-white p-8 text-center">
                <span class="flex h-14 w-14 items-center justify-center rounded-[14px] bg-verde-claro text-verde">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                </span>
                <p class="m-0 mt-4 text-lg font-extrabold">De momento não há inscrições abertas.</p>
                <p class="m-0 mt-1 text-[15px] text-suave">Volta a espreitar em breve — este QR/página é sempre o mesmo.</p>
            </div>

            <div
                v-for="evento in eventos"
                :key="evento.id"
                class="mt-6 overflow-hidden rounded-[14px] border bg-white transition-colors"
                :class="eventoAberto === evento.id ? 'border-verde' : 'border-linha'"
            >
                <button type="button" class="flex w-full items-center gap-4 p-4 text-left sm:p-5" :aria-expanded="eventoAberto === evento.id" @click="abrir(evento)">
                    <img v-if="evento.cartaz" :src="srcCartaz(evento.cartaz)" class="h-[90px] w-[72px] shrink-0 rounded-[10px] bg-linha object-cover" alt="">
                    <div class="min-w-0 flex-1">
                        <h2 class="m-0 text-[21px] font-extrabold leading-tight sm:text-[23px]">{{ evento.titulo }}</h2>
                        <p v-if="evento.subtitulo" class="m-0 mt-0.5 text-[15px] text-suave">{{ evento.subtitulo }}</p>
                        <p class="m-0 mt-1 flex flex-wrap items-center gap-x-1 text-[15px] font-bold text-verde">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="shrink-0"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                            {{ evento.data_inicio }}<span v-if="evento.periodo"> · {{ evento.periodo }}</span><span v-if="evento.localizacao"> · {{ evento.localizacao }}</span>
                        </p>
                        <p v-if="infoPrecos(evento)" class="m-0 mt-1 text-[15px] font-bold text-suave">{{ infoPrecos(evento) }}</p>
                        <p v-if="evento.esgotado" class="m-0 mt-2 inline-flex h-6 items-center rounded-full bg-perigo-claro px-2.5 text-[13px] font-extrabold text-perigo-texto">ESGOTADO</p>
                        <p v-else-if="evento.vagas_restantes !== null" class="m-0 mt-2 inline-flex h-6 items-center rounded-full bg-verde-claro px-2.5 text-[13px] font-extrabold text-verde-escuro">{{ evento.vagas_restantes }} vagas</p>
                    </div>
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-fundo text-tinta" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="transition-transform" :class="eventoAberto === evento.id ? 'rotate-180' : ''"><path d="M6 9l6 6 6-6" /></svg>
                    </span>
                </button>

                <div v-if="eventoAberto === evento.id" class="border-t border-linha p-4 sm:p-5">
                    <div v-if="sucesso === evento.id" class="flex flex-col items-center rounded-[10px] bg-verde-claro p-6 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-verde text-white">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L19 7" /></svg>
                        </span>
                        <p class="m-0 mt-3 text-lg font-extrabold text-verde-escuro">Inscrição registada!</p>
                        <p class="m-0 text-[15px] text-verde-escuro">Até já</p>
                    </div>

                    <div v-else-if="evento.esgotado" class="rounded-[10px] bg-perigo-claro p-5 text-center font-bold text-perigo-texto">
                        As vagas para este evento esgotaram.
                    </div>

                    <form v-else class="grid gap-4" @submit.prevent="submeter(evento)">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <input v-model="form.nome" required class="campo" placeholder="Nome *" aria-label="Nome">
                            <input v-model="form.telefone" required type="tel" class="campo" placeholder="Telefone *" aria-label="Telefone">
                        </div>
                        <input v-model="form.email" :required="evento.pagamento_online && form.pagar_online === true" type="email" class="campo" aria-label="Email" :placeholder="evento.pagamento_online && form.pagar_online === true ? 'Email * (recibo e confirmação)' : 'Email (para receberes a confirmação)'">

                        <select v-if="evento.opcoes?.length" v-model="form.opcao" required class="campo" aria-label="Opção">
                            <option value="" disabled>Escolhe uma opção *</option>
                            <option v-for="opcao in evento.opcoes" :key="opcao.nome" :value="opcao.nome">
                                {{ opcao.nome }}{{ opcao.preco !== null ? ` — ${euros(opcao.preco)}` : '' }}
                            </option>
                        </select>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="flex flex-col gap-1.5 text-[15px] font-bold">
                                {{ evento.pede_idades ? 'Nº de adultos' : 'Nº de pessoas' }}
                                <span class="flex items-center gap-2">
                                    <button type="button" class="passo" aria-label="Menos" @click="passo('num_adultos', -1, 1, 50)">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                                    </button>
                                    <input v-model.number="form.num_adultos" required type="number" min="1" max="50" class="campo-num">
                                    <button type="button" class="passo" aria-label="Mais" @click="passo('num_adultos', 1, 1, 50)">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                    </button>
                                </span>
                            </label>
                            <label v-if="evento.pede_idades" class="flex flex-col gap-1.5 text-[15px] font-bold">
                                Nº de crianças
                                <span class="flex items-center gap-2">
                                    <button type="button" class="passo" aria-label="Menos" @click="passo('num_criancas', -1, 0, 30)">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14" /></svg>
                                    </button>
                                    <input v-model.number="form.num_criancas" type="number" min="0" max="30" class="campo-num">
                                    <button type="button" class="passo" aria-label="Mais" @click="passo('num_criancas', 1, 0, 30)">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                                    </button>
                                </span>
                            </label>
                        </div>
                        <input v-if="evento.pede_idades && form.num_criancas > 0" v-model="form.idades_criancas" class="campo" placeholder="Idades das crianças (ex.: 4, 7, 11)" aria-label="Idades das crianças">
                        <textarea v-model="form.observacoes" rows="2" class="campo campo-area" placeholder="Observações (opcional)" aria-label="Observações"></textarea>

                        <!-- Escolha de método de pagamento (só quando o evento suporta online) -->
                        <div v-if="evento.pagamento_online">
                            <p class="m-0 mb-2 text-[13px] font-bold uppercase tracking-[0.1em] text-suave">Como preferes pagar?</p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    class="flex min-h-[56px] cursor-pointer items-center gap-3 rounded-[10px] border-2 px-4 text-base font-bold transition"
                                    :class="form.pagar_online === false ? 'border-verde bg-verde-claro text-tinta' : 'border-linha bg-white text-tinta hover:border-linha-forte'"
                                >
                                    <input v-model="form.pagar_online" type="radio" :value="false" class="h-5 w-5 border-linha-forte text-verde focus:ring-verde">
                                    Pagar no dia
                                </label>
                                <label
                                    class="flex min-h-[56px] cursor-pointer items-center gap-3 rounded-[10px] border-2 px-4 text-base font-bold transition"
                                    :class="form.pagar_online === true ? 'border-verde bg-verde-claro text-tinta' : 'border-linha bg-white text-tinta hover:border-linha-forte'"
                                >
                                    <input v-model="form.pagar_online" type="radio" :value="true" class="h-5 w-5 border-linha-forte text-verde focus:ring-verde">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20" /></svg>
                                    Pagar agora
                                </label>
                            </div>
                            <p v-if="form.errors.pagar_online" class="m-0 mt-1 text-sm font-bold text-perigo">{{ form.errors.pagar_online }}</p>
                        </div>

                        <div v-if="totalEstimado(evento) !== null" class="flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-[#F6D9BF] bg-laranja-claro p-4 sm:px-5">
                            <div class="text-laranja-texto">
                                <span class="block text-[15px] font-bold">Total estimado</span>
                                <span v-if="detalheTotal(evento)" class="block text-sm">{{ detalheTotal(evento) }}</span>
                                <span class="block text-sm">
                                    {{ evento.pagamento_online && form.pagar_online === true ? 'Pagamento online seguro (Viva)' : 'Pagamento no dia do evento' }}
                                </span>
                            </div>
                            <span class="text-[30px] font-extrabold tabular-nums text-laranja-texto">{{ euros(totalEstimado(evento)) }}</span>
                        </div>

                        <div v-if="Object.keys(form.errors).length" class="rounded-[10px] bg-perigo-claro p-3.5 text-[15px] font-bold text-perigo-texto" role="alert">
                            <div v-for="(erro, campo) in form.errors" :key="campo">{{ erro }}</div>
                        </div>

                        <button class="flex h-[60px] items-center justify-center gap-2.5 rounded-[10px] bg-verde px-4 text-lg font-extrabold tracking-[0.06em] text-white transition hover:bg-verde-escuro disabled:cursor-not-allowed disabled:opacity-50" :disabled="form.processing">
                            <svg v-if="!form.processing && evento.pagamento_online && form.pagar_online === true && totalEstimado(evento)" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20" /></svg>
                            {{ form.processing ? 'A enviar...' : (evento.pagamento_online && form.pagar_online === true && totalEstimado(evento) ? 'INSCREVER E PAGAR' : 'INSCREVER') }}
                        </button>
                        <p v-if="recaptchaSiteKey" class="m-0 text-center text-[13px] text-suave-2">Protegido por reCAPTCHA</p>
                    </form>
                </div>
            </div>
        </div>
    </PublicShell>
</template>

<style scoped>
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
.campo-area { height: auto; padding: 12px 14px; resize: vertical; }
.campo:focus, .campo-num:focus { border-color: #0F6B4F; box-shadow: 0 0 0 3px rgb(15 107 79 / 0.15); outline: none; }
.passo {
    display: flex; align-items: center; justify-content: center;
    width: 50px; height: 50px; flex-shrink: 0;
    border-radius: 10px; border: 1px solid #D5D9D3; background: #F4F5F2; color: #16201C;
    transition: background 150ms;
}
.passo:hover { background: #ECEEEA; }
.campo-num {
    width: 64px; height: 50px; text-align: center;
    border-radius: 10px; border: 1px solid #D5D9D3; background: #FFFFFF;
    font-size: 20px; font-weight: 800; color: #16201C; font-variant-numeric: tabular-nums;
    -moz-appearance: textfield;
}
.campo-num::-webkit-outer-spin-button, .campo-num::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
</style>
