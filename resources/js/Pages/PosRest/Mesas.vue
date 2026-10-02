<script setup>
import { Link, router } from '@inertiajs/vue3'; // Link still used for fechadas-hoje and back button
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import QRCode from 'qrcode';
import ChamadaFuncionarioAlert from '@/Components/ChamadaFuncionarioAlert.vue';
import ChamarComissaoModal from '@/Components/ChamarComissaoModal.vue';
import ComissaoChamadasAlert from '@/Components/ComissaoChamadasAlert.vue';

const chamandoComissao = ref(false);

const props = defineProps({ mesas: Array, pedidosFechadosHoje: { type: Array, default: () => [] }, reservasSemMesa: { type: Array, default: () => [] } });
let refresh = null;
const qrAberto = ref(false);
const qrDataUrl = ref('');
const somenteGrupos = ref(false);
const mesasFiltradas = computed(() => somenteGrupos.value ? (props.mesas ?? []).filter((m) => Number(m.capacidade ?? 0) >= 10) : (props.mesas ?? []));
const grupos = computed(() => mesasFiltradas.value.reduce((acc, m) => { const k = m.localizacao || 'Sala'; if (!acc[k]) acc[k] = []; acc[k].push(m); return acc; }, {}));
const precarioUrl = computed(() => route('precario'));
const pedidosAtivos = (mesa) => [
    ...(mesa.pedidos ?? []),
    ...(mesa.pedidos_grupo ?? []),
    ...((mesa.submesas ?? []).flatMap((submesa) => submesa.pedidos ?? [])),
    ...((mesa.submesas ?? []).flatMap((submesa) => submesa.pedidos_grupo ?? [])),
];
const coresGrupo = [
    'bg-violet-600',
    'bg-cyan-600',
    'bg-fuchsia-600',
    'bg-lime-500 text-gray-950',
    'bg-amber-500 text-gray-950',
    'bg-blue-600',
    'bg-rose-600',
    'bg-teal-600',
];
const pedidoGrupo = (mesa) => (mesa.pedidos_grupo ?? [])[0] ?? ((mesa.submesas ?? []).flatMap((submesa) => submesa.pedidos_grupo ?? []))[0] ?? null;
// "A pagar" é derivado: todos os pedidos abertos da mesa já têm a conta pedida
// (botão "Pedir conta"/"Fechar conta" no pedido da mesa) e ainda não foram pagos.
const pedidosAPagar = (mesa) => pedidosAtivos(mesa).filter((pedido) => !!pedido.conta_pedida_em);
const mesaAPagar = (mesa) => pedidosAtivos(mesa).length > 0 && pedidosAPagar(mesa).length === pedidosAtivos(mesa).length;
const estadoVisual = (mesa) => mesaAPagar(mesa) ? 'apagar' : (pedidoGrupo(mesa) ? 'grupo' : (pedidosAtivos(mesa).length ? 'ocupada' : mesa.estado));
const total = (mesa) => Number(pedidosAtivos(mesa).reduce((soma, pedido) => soma + Number(pedido.total_calculado ?? pedido.total ?? 0), 0)).toFixed(2) + '€';
const minutos = (mesa) => Math.max(0, Math.floor((Date.now() - new Date(pedidosAtivos(mesa)[0]?.created_at || Date.now())) / 60000)) + 'min';
const mesaLivre = (mesa) => !pedidosAtivos(mesa).length && (mesa?.estado ?? 'livre') === 'livre';
const lugaresLivres = (mesa) => {
    if (mesa.submesas?.length) {
        return mesa.submesas
            .filter((submesa) => mesaLivre(submesa))
            .reduce((total, submesa) => total + Number(submesa.capacidade || 0), 0);
    }

    return mesaLivre(mesa) ? Number(mesa.capacidade || 0) : 0;
};
const textoLugaresLivres = (mesa) => {
    const livres = lugaresLivres(mesa);

    return `${livres} ${livres === 1 ? 'lugar livre' : 'lugares livres'}`;
};
const cor = (mesa) => {
    const grupo = pedidoGrupo(mesa);

    if (grupo) {
        return coresGrupo[Number(grupo.id ?? 0) % coresGrupo.length];
    }

    return estadoVisual(mesa) === 'ocupada' ? 'bg-red-600' : estadoVisual(mesa) === 'reservada' ? 'bg-yellow-500 text-gray-950' : 'bg-emerald-600';
};
const mostrarQrPrecario = async () => {
    qrDataUrl.value = await QRCode.toDataURL(precarioUrl.value, { width: 420, margin: 2 });
    qrAberto.value = true;
};
const copiarPrecario = async () => {
    if (navigator?.clipboard) {
        await navigator.clipboard.writeText(precarioUrl.value);
    }
};
const euros = (v) => Number(v ?? 0).toLocaleString('pt-PT', { useGrouping: 'always', minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const horaFechada = (ts) => ts ? new Date(ts).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' }) : '';
const nomeMesaFechada = (p) => {
    const mp = p.mesa?.mesa_principal ?? p.mesa;
    return mp ? 'Mesa ' + mp.numero : 'Balcão';
};
const pedidosFechadosOrdenados = computed(() => [...(props.pedidosFechadosHoje ?? [])].filter(p => p.mesa_id).sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at)));

// Mesas com reserva sentada mas sem pedidos — precisam de atenção
const mesasAguardandoPedido = computed(() =>
    (props.mesas ?? []).filter((m) => m.reserva_ativa && !pedidosAtivos(m).length)
);

// Modal de associação de reserva a mesa
const mesaModal = ref(null);       // mesa seleccionada para o modal
const associandoId = ref(null);    // reserva que está a ser associada

const clicarMesa = (mesa) => {
    if (props.reservasSemMesa?.length) {
        mesaModal.value = mesa;
        erroAssociar.value = '';
    } else {
        router.visit(route('pos.rest.mesa', mesa.id));
    }
};

const erroAssociar = ref('');
const associarReserva = (reserva) => {
    if (!mesaModal.value) return;
    erroAssociar.value = '';
    associandoId.value = reserva.id;
    const mesaNum = mesaModal.value.numero;
    const mesaId  = mesaModal.value.id;
    router.patch(
        route('pos.rest.reserva.associar', reserva.id),
        { mesa_numero: mesaNum },
        {
            preserveScroll: true,
            onSuccess: () => {
                mesaModal.value    = null;
                associandoId.value = null;
                router.visit(route('pos.rest.mesa', mesaId));
            },
            onError: (erros) => {
                associandoId.value = null;
                erroAssociar.value = Object.values(erros).join(' ') || 'Não foi possível associar a reserva.';
            },
        }
    );
};

const irSemAssociar = () => {
    const id = mesaModal.value?.id;
    mesaModal.value = null;
    if (id) router.visit(route('pos.rest.mesa', id));
};

onMounted(() => { refresh = setInterval(() => router.reload({ only: ['mesas', 'pedidosFechadosHoje'], preserveScroll: true }), 20000); });
onBeforeUnmount(() => clearInterval(refresh));

// ---------------------------------------------------------------------------
// Apresentação (redesign)
// ---------------------------------------------------------------------------
const estadoTile = (mesa) => {
    const estado = estadoVisual(mesa);
    if (estado === 'apagar') return 'apagar';
    if (estado === 'grupo') return 'grupo';
    if (estado === 'ocupada') return 'ocupada';
    if (estado === 'reservada' || (mesa.reserva_ativa && !pedidosAtivos(mesa).length)) return 'reservada';
    return 'livre';
};
const estilosTile = {
    livre: { tile: 'border-2 border-linha-forte bg-white text-tinta', pill: 'bg-fundo text-tinta', label: 'Livre' },
    ocupada: { tile: 'border-2 border-verde bg-verde text-white', pill: 'bg-white/20 text-white', label: 'Ocupada' },
    apagar: { tile: 'border-2 border-laranja bg-laranja text-white', pill: 'bg-white/25 text-white', label: 'A pagar' },
    reservada: { tile: 'border-2 border-azul bg-azul text-white', pill: 'bg-white/20 text-white', label: 'Reservada' },
    grupo: { tile: 'border-2 border-roxo bg-roxo text-white', pill: 'bg-white/20 text-white', label: 'Grupo' },
};
const estiloTile = (mesa) => estilosTile[estadoTile(mesa)];
// Faixa de cor por grupo, para distinguir dois grupos diferentes no mapa
const faixasGrupo = ['#C4B5FD', '#67E8F9', '#F0ABFC', '#BEF264', '#FCD34D', '#93C5FD', '#FDA4AF', '#5EEAD4'];
const faixaGrupo = (mesa) => {
    const grupo = pedidoGrupo(mesa);
    return grupo ? faixasGrupo[Number(grupo.id ?? 0) % faixasGrupo.length] : null;
};
const livresNaZona = (lista) => lista.filter((m) => estadoTile(m) === 'livre').length;
const submesasAPagar = (mesa) => (!mesaAPagar(mesa) && pedidosAPagar(mesa).length) ? pedidosAPagar(mesa).length : 0;

</script>

<template>
    <ChamadaFuncionarioAlert />
    <ComissaoChamadasAlert />
    <main class="flex min-h-screen flex-col bg-fundo font-sans text-tinta tabular-nums">
        <header class="flex shrink-0 flex-wrap items-center justify-between gap-2 bg-escuro px-4 py-2.5 text-white sm:px-6 lg:h-16 lg:py-0">
            <div class="flex items-center gap-3.5">
                <Link :href="route('pos.rest.index')" aria-label="Voltar ao início do restaurante" class="flex h-11 w-11 items-center justify-center rounded-[10px] bg-escuro-2 text-white hover:text-white">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                </Link>
                <h1 class="text-[22px] font-extrabold">Mesas</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" class="h-11 rounded-[10px] px-4 text-[15px] font-bold text-white" :class="somenteGrupos ? 'bg-roxo' : 'bg-escuro-2'" :aria-pressed="somenteGrupos" @click="somenteGrupos = !somenteGrupos">Só grupos grandes (10+)</button>
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-4 text-[15px] font-semibold text-white" @click="mostrarQrPrecario">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><path d="M14 14h3v3M21 14v7h-7" /></svg>
                    QR preçário
                </button>
                <button type="button" class="h-11 rounded-[10px] bg-laranja px-4 text-[15px] font-bold text-white" @click="chamandoComissao = true">Chamar comissão</button>
            </div>
        </header>
        <ChamarComissaoModal v-if="chamandoComissao" @fechar="chamandoComissao = false" />

        <div class="flex-1 px-4 pb-6 sm:px-6">
            <!-- Alertas -->
            <div v-if="mesasAguardandoPedido.length || reservasSemMesa.length" class="flex flex-col gap-3 pt-3.5 lg:flex-row">
                <div v-if="mesasAguardandoPedido.length" role="status" class="flex flex-1 flex-wrap items-center gap-3 rounded-xl border-2 border-laranja bg-laranja-claro px-3.5 py-2.5">
                    <span class="text-[15px] font-bold text-laranja-texto">{{ mesasAguardandoPedido.length === 1 ? '1 mesa aguarda pedido' : `${mesasAguardandoPedido.length} mesas aguardam pedido` }}</span>
                    <Link
                        v-for="m in mesasAguardandoPedido"
                        :key="m.id"
                        :href="route('pos.rest.mesa', m.id)"
                        class="flex min-h-11 items-center rounded-[10px] bg-laranja px-3.5 text-[15px] font-bold text-white hover:text-white"
                    >Mesa {{ m.reserva_ativa.mesa_atribuida || m.numero }} · {{ m.reserva_ativa.nome }} · {{ m.reserva_ativa.pessoas }} pess.</Link>
                </div>
                <div v-if="reservasSemMesa.length" role="status" class="flex flex-wrap items-center gap-3 rounded-xl border-2 border-azul bg-[#EAF0FB] px-3.5 py-2.5 lg:max-w-[45%]">
                    <span class="text-[15px] font-bold text-[#1E4290]">{{ reservasSemMesa.length === 1 ? '1 reserva sentada sem mesa' : `${reservasSemMesa.length} reservas sentadas sem mesa` }}</span>
                    <span v-for="r in reservasSemMesa" :key="r.id" class="flex min-h-11 items-center rounded-[10px] bg-azul px-3.5 text-[15px] font-bold text-white">
                        {{ r.nome }} · {{ r.pessoas }} pess.<template v-if="r.mesa_atribuida"> · Mesa {{ r.mesa_atribuida }}</template>
                    </span>
                </div>
            </div>

            <!-- Legenda -->
            <div class="mt-3.5 flex flex-wrap items-center justify-between gap-2 text-sm text-suave">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span>Legenda:</span>
                    <span class="flex items-center gap-1.5"><span class="h-4 w-4 rounded border-2 border-suave bg-white"></span>Livre</span>
                    <span class="flex items-center gap-1.5"><span class="h-4 w-4 rounded bg-verde"></span>Ocupada</span>
                    <span class="flex items-center gap-1.5"><span class="h-4 w-4 rounded bg-laranja"></span>A pagar</span>
                    <span class="flex items-center gap-1.5"><span class="h-4 w-4 rounded bg-azul"></span>Reservada</span>
                    <span class="flex items-center gap-1.5"><span class="h-4 w-4 rounded bg-roxo"></span>Grupo</span>
                </div>
                <span>Toque numa mesa para a abrir</span>
            </div>

            <section v-for="(lista, local) in grupos" :key="local" class="mt-4">
                <h2 class="mb-2 text-[15px] font-extrabold uppercase tracking-wider text-suave">{{ local }} <span class="font-semibold normal-case tracking-normal">· {{ livresNaZona(lista) }} livres de {{ lista.length }}</span></h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
                    <button
                        v-for="mesa in lista"
                        :key="mesa.id"
                        type="button"
                        class="relative flex min-h-[116px] flex-col items-stretch overflow-hidden rounded-[14px] px-3 py-2.5 text-left"
                        :class="estiloTile(mesa).tile"
                        @click="clicarMesa(mesa)"
                    >
                        <span v-if="faixaGrupo(mesa)" class="absolute inset-y-0 left-0 w-1.5" :style="{ backgroundColor: faixaGrupo(mesa) }" aria-hidden="true"></span>
                        <span class="flex items-start justify-between gap-1">
                            <span class="text-[28px] font-extrabold leading-none">{{ mesa.numero }}</span>
                            <span class="pt-1 text-right text-xs font-semibold opacity-90"><template v-if="estadoVisual(mesa) === 'grupo' || pedidoGrupo(mesa)">Grupo · </template>Cap. {{ mesa.capacidade }}</span>
                        </span>
                        <span class="mt-1.5 self-start rounded-full px-2.5 py-0.5 text-xs font-extrabold uppercase tracking-wider" :class="estiloTile(mesa).pill">{{ estiloTile(mesa).label }}</span>
                        <span v-if="['grupo', 'ocupada', 'apagar'].includes(estadoTile(mesa))" class="mt-1 text-[13px] font-bold">{{ total(mesa) }} · {{ minutos(mesa) }}</span>
                        <span v-else-if="estadoTile(mesa) === 'livre'" class="mt-1 text-[13px] font-semibold">{{ textoLugaresLivres(mesa) }}</span>
                        <span v-if="['grupo', 'ocupada', 'apagar'].includes(estadoTile(mesa)) && mesa.submesas?.length" class="text-[13px] font-semibold">{{ textoLugaresLivres(mesa) }}</span>
                        <span v-if="mesa.submesas?.length" class="text-[13px] font-semibold">{{ mesa.submesas.length }} submesas</span>
                        <span v-if="submesasAPagar(mesa)" class="mt-0.5 self-start rounded bg-laranja px-1.5 py-0.5 text-xs font-bold text-white">{{ submesasAPagar(mesa) }} a pagar</span>
                        <span v-if="mesa.reserva_ativa" class="mt-0.5 truncate text-[13px] font-bold">
                            {{ mesa.reserva_ativa.nome }}
                            <span v-if="mesa.reserva_ativa.mesa_atribuida !== String(mesa.numero)" class="font-semibold opacity-80">({{ mesa.reserva_ativa.mesa_atribuida }})</span>
                        </span>
                        <span v-else-if="mesa.nome_reserva" class="mt-0.5 truncate text-[13px] font-bold">{{ mesa.nome_reserva }}</span>
                        <span v-if="mesa.reserva_ativa && !pedidosAtivos(mesa).length" class="mt-1 rounded-md bg-laranja-claro px-2 py-1 text-center text-xs font-extrabold text-laranja-texto">Fazer pedido</span>
                    </button>
                </div>
            </section>

            <!-- Mesas fechadas hoje -->
            <section v-if="pedidosFechadosOrdenados.length" class="mt-6">
                <h2 class="mb-2 text-[15px] font-extrabold uppercase tracking-wider text-suave">Fechadas hoje</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
                    <Link
                        v-for="p in pedidosFechadosOrdenados"
                        :key="p.id"
                        :href="route('pos.rest.mesa', p.mesa_id)"
                        class="flex min-h-[72px] flex-col rounded-[14px] border border-linha bg-[#ECEEEA] px-3 py-2 text-tinta hover:text-tinta"
                    >
                        <span class="text-lg font-extrabold leading-tight">{{ nomeMesaFechada(p) }}</span>
                        <span class="text-[15px] font-bold text-verde-escuro">{{ euros(p.total) }}</span>
                        <span class="flex items-center justify-between text-xs text-suave">
                            {{ horaFechada(p.updated_at) }}
                            <span v-if="p.observacoes" class="font-extrabold text-laranja-texto">OBS</span>
                        </span>
                    </Link>
                </div>
            </section>
        </div>

        <!-- Modal: associar reserva a mesa -->
        <div v-if="mesaModal" class="fixed inset-0 z-50 flex items-center justify-center bg-escuro/60 p-4" @click.self="mesaModal = null">
            <div role="dialog" aria-label="Associar reserva" class="w-full max-w-sm rounded-[14px] bg-white p-5">
                <h2 class="text-xl font-extrabold">Mesa {{ mesaModal.numero }}</h2>
                <p class="mt-1 text-[15px] text-suave">Associar uma reserva sem mesa?</p>
                <div v-if="erroAssociar" role="alert" class="mt-3 rounded-[10px] bg-perigo-claro p-3 text-sm font-semibold text-perigo-texto">{{ erroAssociar }}</div>
                <div class="mt-3 max-h-72 space-y-2 overflow-y-auto">
                    <button
                        v-for="r in reservasSemMesa"
                        :key="r.id"
                        type="button"
                        class="w-full rounded-[10px] border border-linha-forte bg-white p-3 text-left hover:bg-fundo disabled:opacity-45"
                        :disabled="associandoId === r.id"
                        @click="associarReserva(r)"
                    >
                        <div class="font-bold">{{ r.nome }}</div>
                        <div class="mt-0.5 text-sm text-suave">{{ r.pessoas }} pessoas<template v-if="r.hora"> · {{ r.hora?.slice(0, 5) }}</template></div>
                        <div v-if="r.observacoes" class="mt-0.5 text-xs text-suave-2">{{ r.observacoes }}</div>
                    </button>
                </div>
                <button type="button" class="mt-4 h-14 w-full rounded-[10px] bg-verde font-bold text-white hover:bg-verde-escuro" @click="irSemAssociar">Ir para a mesa sem associar</button>
                <button type="button" class="mt-2 h-11 w-full rounded-[10px] font-semibold text-suave hover:text-tinta" @click="mesaModal = null">Cancelar</button>
            </div>
        </div>

        <div v-if="qrAberto" class="fixed inset-0 z-50 flex items-center justify-center overflow-auto bg-escuro/80 p-5">
            <div class="w-full max-w-md rounded-[14px] bg-white p-6 text-center">
                <h2 class="text-2xl font-extrabold">Preçário</h2>
                <p class="mt-1 text-[15px] text-suave">Produtos e preços disponíveis</p>
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR code do preçário" class="mx-auto my-5 h-72 w-72 rounded-[14px] border border-linha p-3">
                <input :value="precarioUrl" readonly aria-label="Link do preçário" class="h-11 w-full rounded-[10px] border-linha-forte text-xs">
                <button type="button" class="mt-3 h-14 w-full rounded-[10px] bg-escuro font-bold text-white" @click="copiarPrecario">Copiar link</button>
                <button type="button" class="mt-2 h-14 w-full rounded-[10px] border border-linha-forte font-bold" @click="qrAberto = false">Fechar</button>
            </div>
        </div>
    </main>
</template>
