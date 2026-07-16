<script setup>
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
    num_pessoas: 1,
    opcao: '',
    num_criancas: 0,
    idades_criancas: '',
    observacoes: '',
    recaptcha_token: '',
});

const abrir = (evento) => {
    eventoAberto.value = eventoAberto.value === evento.id ? null : evento.id;
    sucesso.value = '';
    form.reset();
    form.clearErrors();
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

const euros = (v) => Number(v).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' });

const precoOpcao = (evento) => {
    if (!form.opcao) return null;
    const opcao = (evento.opcoes ?? []).find((o) => o.nome === form.opcao);
    return opcao?.preco ?? null;
};

const totalEstimado = (evento) => {
    const precoAdulto = precoOpcao(evento) ?? evento.preco;
    if (precoAdulto === null || precoAdulto === undefined) return null;
    const criancas = Math.min(Number(form.num_criancas || 0), Number(form.num_pessoas || 1));
    const adultos = Math.max(0, Number(form.num_pessoas || 1) - criancas);
    const precoCrianca = evento.preco_crianca ?? precoAdulto;
    return adultos * precoAdulto + criancas * precoCrianca;
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

const submeter = async (evento) => {
    form.recaptcha_token = await obterToken();
    form.post(route('inscricoes.store', evento.id), {
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
        <div class="mx-auto max-w-3xl px-4 py-10">
            <h1 class="text-3xl font-black text-stone-800">Inscrições</h1>
            <p class="mt-1 text-stone-500">Inscreve-te nos próximos eventos da Associação de Santana.</p>

            <div v-if="!eventos.length" class="mt-10 rounded-xl bg-amber-50 p-8 text-center">
                <div class="text-4xl">📅</div>
                <p class="mt-3 text-lg font-bold text-stone-700">De momento não há inscrições abertas.</p>
                <p class="text-sm text-stone-500">Volta a espreitar em breve — este QR/página é sempre o mesmo.</p>
            </div>

            <div v-for="evento in eventos" :key="evento.id" class="mt-6 overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
                <button type="button" class="flex w-full items-center gap-4 p-5 text-left" @click="abrir(evento)">
                    <img v-if="evento.cartaz" :src="evento.cartaz.startsWith('http') ? evento.cartaz : `/${evento.cartaz.replace(/^\//, '')}`" class="h-20 w-16 rounded-lg object-cover" alt="">
                    <div class="min-w-0 flex-1">
                        <h2 class="text-xl font-black text-stone-800">{{ evento.titulo }}</h2>
                        <p v-if="evento.subtitulo" class="text-sm text-stone-500">{{ evento.subtitulo }}</p>
                        <p class="mt-1 text-sm font-bold text-amber-700">
                            {{ evento.data_inicio }}<span v-if="evento.periodo"> · {{ evento.periodo }}</span><span v-if="evento.localizacao"> · {{ evento.localizacao }}</span>
                        </p>
                        <p v-if="infoPrecos(evento)" class="mt-1 text-sm font-bold text-stone-600">{{ infoPrecos(evento) }}</p>
                        <p v-if="evento.esgotado" class="mt-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-black text-red-700">ESGOTADO</p>
                        <p v-else-if="evento.vagas_restantes !== null" class="mt-1 inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-black text-emerald-700">{{ evento.vagas_restantes }} vagas</p>
                    </div>
                    <span class="text-2xl text-stone-400">{{ eventoAberto === evento.id ? '▴' : '▾' }}</span>
                </button>

                <div v-if="eventoAberto === evento.id" class="border-t border-amber-100 p-5">
                    <div v-if="sucesso === evento.id" class="rounded-lg bg-emerald-50 p-5 text-center">
                        <div class="text-3xl">✅</div>
                        <p class="mt-2 font-black text-emerald-800">Inscrição registada!</p>
                        <p class="text-sm text-emerald-700">Até já 🎉</p>
                    </div>

                    <div v-else-if="evento.esgotado" class="rounded-lg bg-red-50 p-5 text-center font-bold text-red-700">
                        As vagas para este evento esgotaram.
                    </div>

                    <form v-else class="grid gap-3" @submit.prevent="submeter(evento)">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <input v-model="form.nome" required class="rounded-md border-stone-300" placeholder="Nome *">
                            <input v-model="form.telefone" required type="tel" class="rounded-md border-stone-300" placeholder="Telefone *">
                        </div>
                        <input v-model="form.email" :required="evento.pagamento_online" type="email" class="rounded-md border-stone-300" :placeholder="evento.pagamento_online ? 'Email * (recibo e confirmação)' : 'Email (para receberes a confirmação)'">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-2 text-sm font-bold text-stone-600">
                                Nº de pessoas
                                <input v-model.number="form.num_pessoas" required type="number" min="1" max="50" class="w-24 rounded-md border-stone-300">
                            </label>
                            <select v-if="evento.opcoes?.length" v-model="form.opcao" required class="rounded-md border-stone-300">
                                <option value="" disabled>Escolhe uma opção *</option>
                                <option v-for="opcao in evento.opcoes" :key="opcao.nome" :value="opcao.nome">
                                    {{ opcao.nome }}{{ opcao.preco !== null ? ` — ${euros(opcao.preco)}` : '' }}
                                </option>
                            </select>
                        </div>
                        <div v-if="evento.pede_idades" class="grid gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-2 text-sm font-bold text-stone-600">
                                Nº de crianças
                                <input v-model.number="form.num_criancas" type="number" min="0" max="30" class="w-24 rounded-md border-stone-300">
                            </label>
                            <input v-if="form.num_criancas > 0" v-model="form.idades_criancas" class="rounded-md border-stone-300" placeholder="Idades das crianças (ex.: 4, 7, 11)">
                        </div>
                        <textarea v-model="form.observacoes" rows="2" class="rounded-md border-stone-300" placeholder="Observações (opcional)"></textarea>

                        <div v-if="totalEstimado(evento) !== null" class="rounded-md bg-amber-50 p-3 text-center font-black text-stone-800">
                            Total estimado: {{ euros(totalEstimado(evento)) }}
                            <span class="block text-xs font-bold text-stone-500">{{ evento.pagamento_online ? 'Pagamento online seguro (Viva)' : 'Pagamento no dia do evento' }}</span>
                        </div>

                        <div v-if="Object.keys(form.errors).length" class="rounded-md bg-red-50 p-3 text-sm font-bold text-red-700">
                            <div v-for="(erro, campo) in form.errors" :key="campo">{{ erro }}</div>
                        </div>

                        <button class="rounded-xl bg-amber-600 p-3 text-lg font-black text-white hover:bg-amber-700 disabled:opacity-50" :disabled="form.processing">
                            {{ form.processing ? 'A enviar...' : (evento.pagamento_online && totalEstimado(evento) ? '💳 INSCREVER E PAGAR' : 'INSCREVER') }}
                        </button>
                        <p v-if="recaptchaSiteKey" class="text-center text-xs text-stone-400">Protegido por reCAPTCHA</p>
                    </form>
                </div>
            </div>
        </div>
    </PublicShell>
</template>
