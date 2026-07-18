<script setup>
import { Link, router } from '@inertiajs/vue3';
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
const estadoVisual = (mesa) => pedidoGrupo(mesa) ? 'grupo' : (pedidosAtivos(mesa).length ? 'ocupada' : mesa.estado);
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
const euros = (v) => Number(v ?? 0).toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
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

onMounted(() => { refresh = setInterval(() => router.reload({ only: ['mesas', 'pedidosFechadosHoje'], preserveScroll: true }), 20000); });
onBeforeUnmount(() => clearInterval(refresh));
</script>

<template>
    <ChamadaFuncionarioAlert />
    <ComissaoChamadasAlert />
    <main class="min-h-screen bg-gray-900 p-5 text-white">
        <header class="mb-5 flex flex-wrap items-center gap-3">
            <Link :href="route('pos.rest.index')" class="rounded-lg bg-gray-800 px-4 py-3 font-black">←</Link>
            <h1 class="min-w-0 flex-1 text-2xl font-black sm:text-4xl">MESAS</h1>
            <button class="rounded-lg bg-amber-500 px-3 py-2 text-sm font-black text-black sm:px-4 sm:py-3" @click="chamandoComissao = true">🎉 COMISSÃO</button>
        </header>
        <ChamarComissaoModal v-if="chamandoComissao" @fechar="chamandoComissao = false" />
        <!-- Alerta: mesas com reserva sentada aguardando pedido (mesa mapeada) -->
        <div v-if="mesasAguardandoPedido.length" class="mb-5 rounded-xl border-2 border-orange-400 bg-orange-950/80 p-4">
            <p class="mb-2 text-base font-black text-orange-300 uppercase tracking-wide">
                🔔 {{ mesasAguardandoPedido.length === 1 ? '1 mesa aguarda pedido' : `${mesasAguardandoPedido.length} mesas aguardam pedido` }}
            </p>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="m in mesasAguardandoPedido"
                    :key="m.id"
                    :href="route('pos.rest.mesa', m.id)"
                    class="flex items-center gap-2 rounded-lg bg-orange-500 px-3 py-2 font-black text-gray-950"
                >
                    <span class="text-lg">Mesa {{ m.reserva_ativa.mesa_atribuida || m.numero }}</span>
                    <span class="text-sm font-bold">· {{ m.reserva_ativa.nome }} · {{ m.reserva_ativa.pessoas }} pess.</span>
                </Link>
            </div>
        </div>

        <!-- Alerta: reservas sentadas sem mesa mapeada (ex: grupos grandes) -->
        <div v-if="reservasSemMesa.length" class="mb-5 rounded-xl border-2 border-yellow-400 bg-yellow-950/80 p-4">
            <p class="mb-2 text-base font-black text-yellow-300 uppercase tracking-wide">
                🪑 {{ reservasSemMesa.length === 1 ? '1 reserva sentada aguarda pedido' : `${reservasSemMesa.length} reservas sentadas aguardam pedido` }}
            </p>
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="r in reservasSemMesa"
                    :key="r.id"
                    class="flex items-center gap-2 rounded-lg bg-yellow-500 px-3 py-2 font-black text-gray-950"
                >
                    <span class="text-lg">{{ r.nome }}</span>
                    <span class="text-sm font-bold">· {{ r.pessoas }} pess.</span>
                    <span v-if="r.mesa_atribuida" class="text-sm font-bold">· Mesa {{ r.mesa_atribuida }}</span>
                </div>
            </div>
        </div>

        <div class="mb-5 flex flex-wrap justify-end gap-3">
            <button type="button" class="rounded-lg px-4 py-3 font-black" :class="somenteGrupos ? 'bg-amber-500 text-gray-950' : 'bg-gray-700'" @click="somenteGrupos = !somenteGrupos">GRUPOS GRANDES</button>
            <button type="button" class="rounded-lg bg-emerald-600 px-4 py-3 font-black" @click="mostrarQrPrecario">QR PREÇÁRIO</button>
        </div>
        <section v-for="(lista, local) in grupos" :key="local" class="mb-7">
            <h2 class="mb-3 text-xl font-black uppercase text-gray-300">{{ local }}</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
                <Link v-for="mesa in lista" :key="mesa.id" :href="route('pos.rest.mesa', mesa.id)" class="flex min-h-[116px] flex-col items-center justify-center rounded-lg p-3 text-center font-black shadow" :class="cor(mesa)">
                    <span class="text-2xl sm:text-3xl">{{ mesa.numero }}</span>
                    <span class="text-xs">Cap. {{ mesa.capacidade }}</span>
                    <span class="mt-1 rounded bg-black/15 px-2 py-0.5 text-xs">{{ textoLugaresLivres(mesa) }}</span>
                    <span v-if="mesa.submesas?.length" class="mt-1 text-xs">{{ mesa.submesas.length }} submesas</span>
                    <span v-if="estadoVisual(mesa) === 'grupo'" class="mt-1 text-xs uppercase">Grupo</span>
                    <span v-if="['grupo', 'ocupada'].includes(estadoVisual(mesa))" class="mt-1 text-sm">{{ total(mesa) }} · {{ minutos(mesa) }}</span>
                    <span v-if="mesa.reserva_ativa" class="mt-1 w-full truncate rounded bg-black/25 px-1.5 py-0.5 text-xs">
                        👤 {{ mesa.reserva_ativa.nome }}
                        <span v-if="mesa.reserva_ativa.mesa_atribuida !== String(mesa.numero)" class="opacity-75">({{ mesa.reserva_ativa.mesa_atribuida }})</span>
                    </span>
                    <span v-else-if="mesa.nome_reserva" class="mt-1 w-full truncate rounded bg-black/25 px-1.5 py-0.5 text-xs">
                        👤 {{ mesa.nome_reserva }}
                    </span>
                    <span v-if="mesa.reserva_ativa && !pedidosAtivos(mesa).length" class="mt-1 w-full animate-pulse rounded bg-orange-500 px-1.5 py-0.5 text-xs font-black text-gray-950">
                        ⚡ REALIZAR PEDIDO
                    </span>
                </Link>
            </div>
        </section>
        <!-- Mesas fechadas hoje -->
        <section v-if="pedidosFechadosOrdenados.length" class="mt-8">
            <h2 class="mb-3 text-xl font-black uppercase text-gray-400">✅ Fechadas Hoje</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
                <Link
                    v-for="p in pedidosFechadosOrdenados"
                    :key="p.id"
                    :href="route('pos.rest.mesa', p.mesa_id)"
                    class="flex min-h-[100px] flex-col items-center justify-center rounded-lg bg-gray-700 p-3 text-center font-black shadow opacity-80 hover:opacity-100"
                >
                    <span class="text-2xl sm:text-3xl">{{ nomeMesaFechada(p) }}</span>
                    <span class="mt-1 text-sm font-semibold text-emerald-400">{{ euros(p.total) }}</span>
                    <span class="mt-0.5 text-xs text-gray-400">{{ horaFechada(p.updated_at) }}</span>
                    <span v-if="p.observacoes" class="mt-1 rounded bg-amber-500/20 px-2 py-0.5 text-xs text-amber-300">OBS</span>
                </Link>
            </div>
        </section>

        <div v-if="qrAberto" class="fixed inset-0 z-50 overflow-auto bg-gray-950 p-5">
            <div class="mx-auto max-w-md rounded-2xl bg-white p-5 text-center text-slate-950">
                <h2 class="text-2xl font-black">Preçário</h2>
                <p class="mt-1 text-sm font-semibold text-slate-500">Produtos e preços disponíveis</p>
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR code do preçário" class="mx-auto my-5 h-72 w-72 rounded-xl border p-3">
                <input :value="precarioUrl" readonly class="w-full rounded-lg border-slate-300 text-xs">
                <button class="mt-3 w-full rounded-lg bg-slate-900 p-3 font-black text-white" @click="copiarPrecario">COPIAR LINK</button>
                <button class="mt-3 w-full rounded-lg bg-gray-200 p-3 font-black text-slate-950" @click="qrAberto = false">FECHAR</button>
            </div>
        </div>
    </main>
</template>
