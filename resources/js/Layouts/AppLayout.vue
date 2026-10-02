<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePermissions } from '@/Composables/usePermissions';

const page = usePage();
const { can, hasRole } = usePermissions();
const drawerAberto = ref(false);
let polling = null;

// Ano ativo — persiste em localStorage, sincroniza com URL
const ROTAS_COM_ANO = ['contas-festa.index', 'relatorios.index', 'contas-bancarias.index'];
const anoAtual = new Date().getFullYear();
const anoGuardado = typeof localStorage !== 'undefined' ? parseInt(localStorage.getItem('ano_ativo') || anoAtual) : anoAtual;
const anoSelecionado = ref(anoGuardado);

const mudarAno = (delta) => {
    anoSelecionado.value += delta;
    if (typeof localStorage !== 'undefined') localStorage.setItem('ano_ativo', anoSelecionado.value);
    if (ROTAS_COM_ANO.some(r => ativo(r))) {
        router.get(window.location.pathname, {
            data_inicio: `${anoSelecionado.value}-01-01`,
            data_fim: `${anoSelecionado.value}-12-31`,
        }, { preserveScroll: false });
    }
};

// Links do sidebar que devem incluir o ano selecionado
const linkRota = (rota) => {
    if (ROTAS_COM_ANO.includes(rota)) {
        return route(rota) + `?data_inicio=${anoSelecionado.value}-01-01&data_fim=${anoSelecionado.value}-12-31`;
    }
    return route(rota);
};

// Evita que uma rota em falta no servidor (deploy desalinhado) deixe o backoffice em branco
const rotaExiste = (nome) => { try { return route().has(nome); } catch { return false; } };

const podeGerir = () => hasRole('admin') || hasRole('gerente');
const itemVisivel = (perm) => perm ? can(perm) : podeGerir();
const urgentes = () => page.props.urgentes_count ?? 0;
const ativo = (nome) => route().current(nome) || route().current(nome.replace('.index', '.*'));

// Chamadas da comissão de festas
const chamadas = computed(() => page.props.chamadas_comissao ?? []);

// Som quando chegam chamadas novas
let audioCtx = null;
let chamadasAnteriores = (page.props.chamadas_comissao ?? []).length;
const somChamada = () => {
    try {
        audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
        [523, 659, 784].forEach((freq, i) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.2, audioCtx.currentTime + i * 0.4);
            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + i * 0.4 + 0.35);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start(audioCtx.currentTime + i * 0.4);
            osc.stop(audioCtx.currentTime + i * 0.4 + 0.4);
        });
    } catch { /* sem audio */ }
};
watch(chamadas, (novas) => {
    if (novas.length > chamadasAnteriores) somChamada();
    chamadasAnteriores = novas.length;
});

const atenderChamada = async (id) => {
    // O utilizador já está autenticado — usa-se o nome dele, sem perguntar
    const nome = page.props.auth?.user?.name ?? '';
    try {
        const res = await fetch(route('comissao.atender', id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ nome: nome || null }),
        });
        if (!res.ok) {
            alert('Não foi possível marcar como atendida (erro ' + res.status + '). Atualiza a página e tenta de novo.');
        }
        router.reload({ only: ['chamadas_comissao'], preserveScroll: true });
    } catch { /* ignora */ }
};

// Ícones SVG (stroke, 24x24) — um "d" por item
const ICONES = {
    hoje: 'M3 10l9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
    pedidos: 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6',
    bar: 'M8 3h8l-1 9a3 3 0 0 1-6 0zM12 15v6M8 21h8',
    sala: 'M3 6h18v4H3zM6 10v9M18 10v9',
    mesas: 'M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z',
    reservas: 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4',
    produtos: 'M3 7l9-4 9 4-9 4-9-4zM3 7v10l9 4 9-4V7',
    caixa: 'M3 7h18v12H3zM9 13a3 3 0 1 0 6 0a3 3 0 1 0-6 0',
    contas: 'M3 10l9-6 9 6M5 10v8M10 10v8M14 10v8M19 10v8M3 20h18',
    festa: 'M4 21l5-14 8 8zM14 4l1 2M19 4l-2 3M20 9l-2 1',
    faturas: 'M6 3h9l4 4v14H6zM15 3v4h4M9 12h6M9 16h6',
    relatorios: 'M3 21h18M6 17v-6M11 17V6M16 17v-4M20 17V9',
    socios: 'M6 8a3 3 0 1 0 6 0a3 3 0 1 0-6 0M3 20a6 6 0 0 1 12 0M16 5a3 3 0 0 1 0 6M21 20a6 6 0 0 0-4-5.6',
    cotas: 'M3 6h18v13H3zM3 10h18M7 15h4',
    eventos: 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z',
    alugueres: 'M5 21V4h10v17M3 21h18M12 12h.01M15 7h4v14',
    patrocinadores: 'M12 20s-7-4.5-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.5-7 10-7 10z',
    paginas: 'M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18',
    users: 'M8 8a4 4 0 1 0 8 0a4 4 0 1 0-8 0M4 21a8 8 0 0 1 16 0',
    impressoras: 'M6 9V3h12v6M3 9h18v8H3zM6 14h12v7H6z',
    talao: 'M7 3h10v18H7zM10 8h4M10 12h4M10 16h2',
    extras: 'M3 12a9 9 0 1 0 18 0a9 9 0 1 0-18 0M12 8v8M8 12h8',
    manutencao: 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z',
    pos: 'M5 4h14a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zM8 20h8M12 16v4',
    ecras: 'M3 5h18v11H3zM8 20h8M12 16v4',
    menu: 'M4 6h16M4 12h16M4 18h16',
    sair: 'M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l-5-5 5-5M5 12h11',
    fechar: 'M6 6l12 12M18 6L6 18',
    sino: 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0',
};

// Itens: { nome, rota, perm, icone, ativoEm?: [padrões extra], alternativas?: [[rota, perm], ...] }
// A permissão de cada item é a mesma que já existia no menu.
const grupos = [
    { label: '', items: [
        { nome: 'Hoje', rota: 'dashboard', perm: 'dashboard.ver', icone: 'hoje' },
    ] },
    { label: 'Restaurante', items: [
        { nome: 'Pedidos', rota: 'pedidos.index', perm: 'pedidos.ver', icone: 'pedidos' },
        { nome: 'Bar', rota: 'bar.index', perm: 'bar.ver', icone: 'bar' },
        { nome: 'Mesas e sala', rota: 'sala.index', perm: 'mesas.ver', icone: 'sala', alternativas: [['mesas.index', 'restaurante.ver']] },
        { nome: 'Lista de mesas', rota: 'mesas.index', perm: 'restaurante.ver', icone: 'mesas', sub: true },
        { nome: 'Reservas', rota: 'reservas.index', perm: 'reservas.ver', icone: 'reservas' },
        { nome: 'Produtos', rota: 'produtos.index', perm: 'produtos.ver', icone: 'produtos' },
    ] },
    { label: 'Dinheiro', items: [
        { nome: 'Caixa diária', rota: 'caixa.index', perm: 'caixa.ver', icone: 'caixa' },
        { nome: 'Contas bancárias', rota: 'contas-bancarias.index', perm: 'relatorios.ver', icone: 'contas' },
        { nome: 'Contas da festa', rota: 'contas-festa.index', perm: 'relatorios.ver', icone: 'festa' },
        { nome: 'Faturas de compra', rota: 'faturas-compras.index', perm: 'produtos.ver', icone: 'faturas' },
        { nome: 'Relatórios', rota: 'relatorios.index', perm: 'relatorios.ver', icone: 'relatorios' },
    ] },
    { label: 'Associação', items: [
        { nome: 'Sócios', rota: 'socios.index', perm: 'socios.ver', icone: 'socios', ativoEm: ['socios.emAtraso'] },
        { nome: 'Cotas', rota: 'cotas.index', perm: 'cotas.ver', icone: 'cotas' },
        { nome: 'Eventos', rota: 'eventos.index', perm: null, icone: 'eventos' },
        { nome: 'Alugueres', rota: 'alugueres.index', perm: null, icone: 'alugueres', ativoEm: ['alugueres.opcoes'] },
        { nome: 'Patrocinadores', rota: 'patrocinadores.index', perm: null, icone: 'patrocinadores' },
    ] },
    { label: 'Site', items: [
        { nome: 'Páginas', rota: 'paginas.index', perm: null, icone: 'paginas' },
    ] },
    { label: 'Sistema', items: [
        { nome: 'Utilizadores', rota: 'users.index', perm: 'users.ver', icone: 'users' },
        { nome: 'Impressoras', rota: 'impressoras.index', perm: null, icone: 'impressoras' },
        { nome: 'Talão', rota: 'talao.index', perm: null, icone: 'talao' },
        { nome: 'Valores extra', rota: 'valor-extras.index', perm: null, icone: 'extras' },
        { nome: 'Manutenção', rota: 'manutencao.limpeza.index', perm: null, icone: 'manutencao', ativoEm: ['manutencao.*'], alternativas: [['manutencao.logs.index', null]] },
        { nome: 'Painel POS', rota: 'pos-painel.index', perm: 'pos.comissao', icone: 'pos' },
    ] },
];

const podeVer = (rota, perm) => rotaExiste(rota) && itemVisivel(perm);

// Resolve a rota final de cada item (usa a alternativa se a principal não estiver acessível)
const resolverItem = (item) => {
    if (podeVer(item.rota, item.perm)) return item;
    for (const [rota, perm] of item.alternativas ?? []) {
        if (podeVer(rota, perm)) return { ...item, rota, perm };
    }
    return null;
};

const itemAtivo = (item) => ativo(item.rota) || (item.ativoEm ?? []).some((padrao) => route().current(padrao));

const gruposVisiveis = computed(() => {
    const res = grupos.map((g) => {
        const items = g.items.map(resolverItem).filter(Boolean);
        // "Lista de mesas" só aparece como sub-item quando "Mesas e sala" aponta para a sala
        const temSala = items.some((i) => i.nome === 'Mesas e sala' && i.rota === 'sala.index');
        return { ...g, items: items.filter((i) => !i.sub || temSala) };
    }).filter((g) => g.items.length > 0);
    return res;
});

// Item "Mesas e sala" fica ativo nas mesas quando não há sub-item visível
const estaAtivo = (item, grupo) => {
    if (item.nome === 'Mesas e sala') {
        const temSub = grupo.items.some((i) => i.sub);
        return ativo('sala.index') || (!temSub && ativo('mesas.index')) || (item.rota === 'mesas.index' && ativo('mesas.index'));
    }
    return itemAtivo(item);
};

const tituloAtual = computed(() => {
    for (const g of gruposVisiveis.value) {
        for (const i of g.items) if (estaAtivo(i, g)) return i.nome;
    }
    return '';
});

const nomeUtilizador = computed(() => page.props.auth?.user?.name ?? '');
const iniciais = computed(() => nomeUtilizador.value.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase());
const year = new Date().getFullYear();

const bottomLinks = computed(() =>
    [['Início','dashboard','dashboard.ver','hoje'],['Pedidos','pedidos.index','pedidos.ver','pedidos'],['Reservas','reservas.index','reservas.ver','reservas'],['Sala','sala.index','mesas.ver','sala']]
    .filter(([, rota, perm]) => rotaExiste(rota) && itemVisivel(perm))
);

const ecrasSecao = [
    ['Bebidas', 'secao.bebidas'],
    ['Frango', 'secao.frango'],
    ['Comida', 'secao.comida'],
    ['Sobremesas', 'secao.sobremesas'],
    ['Acompanhamentos', 'secao.acompanhamentos'],
    ['Bar', 'secao.bar'],
];

onMounted(() => { polling = setInterval(() => router.reload({ only: ['urgentes_count', 'chamadas_comissao'], preserveScroll: true }), 30000); });
onBeforeUnmount(() => clearInterval(polling));
</script>


<template>
    <div class="min-h-screen bg-fundo pb-24 font-sans text-tinta tabular-nums min-[901px]:pb-0">

        <!-- Sidebar desktop -->
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-[248px] flex-col bg-escuro px-3 pb-3 pt-5 text-white min-[901px]:flex">
            <Link :href="route('dashboard')" class="block px-3 pb-2 pt-1 text-[19px] font-extrabold text-white no-underline hover:text-white">ARDC Santana</Link>
            <nav aria-label="Menu" class="-mx-1 flex min-h-0 flex-1 flex-col gap-0.5 nav-escura overflow-y-auto overscroll-contain px-1 pb-4">
                <template v-for="grupo in gruposVisiveis" :key="grupo.label || 'inicio'">
                    <div v-if="grupo.label" class="px-3 pb-1.5 pt-4 text-xs font-bold uppercase tracking-[0.08em] text-[#8FA39A]">{{ grupo.label }}</div>
                    <Link
                        v-for="item in grupo.items"
                        :key="item.rota"
                        :href="linkRota(item.rota)"
                        :aria-current="estaAtivo(item, grupo) ? 'page' : undefined"
                        class="flex min-h-10 items-center gap-2.5 rounded-[10px] px-3 text-[15px] no-underline transition"
                        :class="[
                            estaAtivo(item, grupo) ? 'bg-verde font-bold text-white hover:text-white' : 'font-semibold text-escuro-inativo hover:bg-escuro-2 hover:text-white',
                            item.sub ? 'pl-10 text-sm' : '',
                        ]"
                    >
                        <svg v-if="!item.sub" class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="ICONES[item.icone]" /></svg>
                        <span class="min-w-0 flex-1 truncate">{{ item.nome }}</span>
                        <span v-if="item.rota === 'pedidos.index' && urgentes()" class="rounded-full bg-laranja px-2 py-0.5 text-xs font-bold text-white" :aria-label="`${urgentes()} urgentes`">{{ urgentes() }}</span>
                    </Link>
                </template>
            </nav>
            <div class="flex items-center gap-2 border-t border-escuro-2 pt-3">
                <component
                    :is="rotaExiste('profile.edit') ? Link : 'div'"
                    :href="rotaExiste('profile.edit') ? route('profile.edit') : undefined"
                    class="flex min-w-0 flex-1 items-center gap-2.5 rounded-[10px] p-2 text-escuro-inativo no-underline transition hover:bg-escuro-2 hover:text-escuro-inativo"
                >
                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-escuro-2 text-[13px] font-extrabold text-white">{{ iniciais }}</span>
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate text-sm font-bold text-white">{{ nomeUtilizador }}</span>
                        <span class="text-[13px]">Perfil</span>
                    </span>
                </component>
                <Link :href="route('logout')" method="post" as="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] text-escuro-inativo transition hover:bg-escuro-2 hover:text-white" aria-label="Sair (logout)" title="Sair">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="ICONES.sair" /></svg>
                </Link>
            </div>
        </aside>

        <!-- Drawer mobile -->
        <div v-if="drawerAberto" class="fixed inset-0 z-40 bg-escuro/50 min-[901px]:hidden" @click="drawerAberto = false" />
        <aside
            class="fixed inset-y-0 left-0 z-[60] flex w-[300px] max-w-[85vw] transform flex-col bg-escuro text-white shadow-xl transition min-[901px]:hidden"
            :class="drawerAberto ? 'translate-x-0' : '-translate-x-full'"
            :inert="!drawerAberto"
        >
            <div class="flex shrink-0 items-center justify-between gap-3 px-4 pb-2 pt-4">
                <strong class="text-lg font-extrabold">ARDC Santana</strong>
                <button type="button" class="flex h-11 items-center gap-2 rounded-[10px] bg-escuro-2 px-3 text-[15px] font-bold text-white" @click="drawerAberto = false">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="ICONES.fechar" /></svg>
                    Fechar
                </button>
            </div>
            <nav aria-label="Menu" class="flex min-h-0 flex-1 flex-col gap-0.5 nav-escura overflow-y-auto overscroll-contain px-3 pb-28">
                <template v-for="grupo in gruposVisiveis" :key="grupo.label || 'inicio'">
                    <div v-if="grupo.label" class="px-3 pb-1.5 pt-4 text-xs font-bold uppercase tracking-[0.08em] text-[#8FA39A]">{{ grupo.label }}</div>
                    <Link
                        v-for="item in grupo.items"
                        :key="item.rota"
                        :href="linkRota(item.rota)"
                        :aria-current="estaAtivo(item, grupo) ? 'page' : undefined"
                        class="flex min-h-12 items-center gap-3 rounded-[10px] px-3 text-base no-underline transition"
                        :class="[
                            estaAtivo(item, grupo) ? 'bg-verde font-bold text-white hover:text-white' : 'font-semibold text-escuro-inativo hover:bg-escuro-2 hover:text-white',
                            item.sub ? 'pl-11 text-[15px]' : '',
                        ]"
                        @click="drawerAberto = false"
                    >
                        <svg v-if="!item.sub" class="shrink-0" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="ICONES[item.icone]" /></svg>
                        <span class="min-w-0 flex-1 truncate">{{ item.nome }}</span>
                        <span v-if="item.rota === 'pedidos.index' && urgentes()" class="rounded-full bg-laranja px-2 py-0.5 text-xs font-bold text-white">{{ urgentes() }} urgentes</span>
                    </Link>
                </template>

                <div v-if="podeGerir()" class="mt-3 border-t border-escuro-2 pt-3">
                    <div class="flex items-center gap-2 px-3 pb-2 text-xs font-bold uppercase tracking-[0.08em] text-[#8FA39A]">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="ICONES.ecras" /></svg>
                        Ecrãs de secção
                    </div>
                    <div class="grid grid-cols-2 gap-2 px-1">
                        <a
                            v-for="[nome, rota] in ecrasSecao"
                            :key="rota"
                            :href="route(rota)"
                            target="_blank"
                            class="flex min-h-11 items-center justify-center rounded-[10px] bg-escuro-2 px-2 text-center text-sm font-bold text-white no-underline transition hover:bg-verde hover:text-white"
                        >{{ nome }}</a>
                        <a
                            :href="route('pos.reservas.index')"
                            target="_blank"
                            class="col-span-2 flex min-h-11 items-center justify-center rounded-[10px] bg-escuro-2 px-2 text-center text-sm font-bold text-white no-underline transition hover:bg-verde hover:text-white"
                        >Reservas POS</a>
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-2 border-t border-escuro-2 pt-3">
                    <component
                        :is="rotaExiste('profile.edit') ? Link : 'div'"
                        :href="rotaExiste('profile.edit') ? route('profile.edit') : undefined"
                        class="flex min-w-0 flex-1 items-center gap-2.5 rounded-[10px] p-2 text-escuro-inativo no-underline"
                        @click="drawerAberto = false"
                    >
                        <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-escuro-2 text-[13px] font-extrabold text-white">{{ iniciais }}</span>
                        <span class="flex min-w-0 flex-col">
                            <span class="truncate text-sm font-bold text-white">{{ nomeUtilizador }}</span>
                            <span class="text-[13px]">Perfil</span>
                        </span>
                    </component>
                    <Link :href="route('logout')" method="post" as="button" class="flex h-11 shrink-0 items-center gap-2 rounded-[10px] bg-escuro-2 px-3 text-[15px] font-bold text-white">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="ICONES.sair" /></svg>
                        Sair
                    </Link>
                </div>
            </nav>
        </aside>

        <!-- Conteúdo principal -->
        <main class="backoffice-main min-[901px]:pl-[248px]">
            <!-- Notificações da comissão de festas -->
            <div v-if="chamadas.length" class="border-b border-laranja bg-laranja-claro" role="alert">
                <div
                    v-for="chamada in chamadas"
                    :key="chamada.id"
                    class="mx-auto flex max-w-[1320px] items-center justify-between gap-3 px-4 py-2.5 min-[901px]:px-[clamp(16px,3vw,40px)]"
                >
                    <div class="flex min-w-0 items-center gap-3 text-[15px] text-laranja-texto">
                        <span class="flex h-9 w-9 shrink-0 animate-pulse items-center justify-center rounded-[10px] bg-laranja text-white">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="ICONES.sino" /></svg>
                        </span>
                        <span class="min-w-0">
                            <strong class="font-extrabold">{{ chamada.operador_nome }}</strong> chama a comissão
                            em <strong class="font-extrabold">{{ chamada.local }}</strong>
                            <span class="ml-1 font-semibold opacity-80">· {{ chamada.criado_em }}</span>
                        </span>
                    </div>
                    <button
                        type="button"
                        class="h-11 shrink-0 rounded-[10px] bg-laranja px-4 text-[15px] font-bold text-white transition hover:bg-laranja-texto"
                        @click="atenderChamada(chamada.id)"
                    >
                        Atender
                    </button>
                </div>
            </div>

            <div class="mx-auto w-full max-w-[1320px] px-4 pt-4 min-[901px]:px-[clamp(16px,3vw,40px)] min-[901px]:pt-6">
                <!-- Barra superior: telemóvel (marca + menu) -->
                <div class="flex h-[52px] items-center justify-between gap-3 rounded-[14px] bg-escuro pl-4 pr-1.5 text-white min-[901px]:hidden">
                    <span class="truncate font-extrabold">ARDC Santana</span>
                    <div class="flex min-w-0 items-center gap-2">
                        <span v-if="tituloAtual" class="truncate text-sm font-semibold text-escuro-inativo">{{ tituloAtual }}</span>
                        <button type="button" class="flex h-10 items-center gap-1.5 rounded-[10px] bg-escuro-2 px-3 text-sm font-bold text-white" aria-label="Abrir menu" @click="drawerAberto = true">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="ICONES.menu" /></svg>
                            Menu
                        </button>
                    </div>
                </div>

                <!-- Seletor de ano (todas as larguras) -->
                <div class="mt-3 flex items-center justify-end gap-2 min-[901px]:mt-0">
                    <div class="flex h-11 items-center overflow-hidden rounded-[10px] border border-linha-forte bg-white text-[15px] font-bold text-tinta" role="group" aria-label="Ano ativo">
                        <button type="button" class="flex h-full w-11 items-center justify-center transition hover:bg-fundo" aria-label="Ano anterior" @click="mudarAno(-1)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
                        </button>
                        <span class="px-1 tabular-nums">{{ anoSelecionado }}</span>
                        <button type="button" class="flex h-full w-11 items-center justify-center transition hover:bg-fundo" aria-label="Ano seguinte" :disabled="anoSelecionado >= anoAtual" :class="anoSelecionado >= anoAtual ? 'opacity-30' : ''" @click="mudarAno(1)">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <section class="mx-auto w-full max-w-[1320px] px-4 pb-8 pt-4 min-[901px]:px-[clamp(16px,3vw,40px)]">
                <div v-if="page.props.flash?.success" class="mb-4 rounded-[14px] border border-verde-claro2 bg-verde-claro px-4 py-3 text-[15px] font-bold text-verde-escuro" role="status">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="mb-4 rounded-[14px] border border-[#F0C9C2] bg-perigo-claro px-4 py-3 text-[15px] font-bold text-perigo-texto" role="alert">
                    {{ page.props.flash.error }}
                </div>
                <slot />
            </section>
            <footer class="mx-auto w-full max-w-[1320px] px-4 pb-6 text-center text-[13px] text-suave-2 min-[901px]:px-[clamp(16px,3vw,40px)]">
                <span>Copyright © {{ year }} Associação de Santana.</span>
                <span class="mx-2">·</span>
                <a href="https://ateneya.com/" target="_blank" rel="noopener" class="font-semibold text-suave hover:text-verde">#CreatingDevelopingImproving4you</a>
            </footer>
        </main>

        <!-- Navegação inferior (telemóvel) -->
        <nav aria-label="Atalhos" class="fixed inset-x-0 bottom-0 z-50 grid gap-1 border-t border-linha bg-white p-2 min-[901px]:hidden" :style="`grid-template-columns: repeat(${bottomLinks.length + 1}, minmax(0, 1fr))`">
            <Link
                v-for="[label, rota,, icon] in bottomLinks"
                :key="rota"
                :href="route(rota)"
                :aria-current="ativo(rota) ? 'page' : undefined"
                class="relative flex min-h-[52px] flex-col items-center justify-center gap-1 rounded-[10px] px-1 text-xs font-bold no-underline transition"
                :class="ativo(rota) ? 'bg-verde text-white hover:text-white' : 'text-suave hover:bg-fundo hover:text-tinta'"
            >
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="ICONES[icon]" /></svg>
                <span>{{ label }}</span>
                <span v-if="rota === 'pedidos.index' && urgentes()" class="absolute right-1 top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-laranja px-1 text-[11px] font-extrabold text-white" :aria-label="`${urgentes()} urgentes`">{{ urgentes() }}</span>
            </Link>
            <button type="button" class="flex min-h-[52px] flex-col items-center justify-center gap-1 rounded-[10px] px-1 text-xs font-bold text-suave transition hover:bg-fundo hover:text-tinta" @click="drawerAberto = true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="ICONES.menu" /></svg>
                <span>Menu</span>
            </button>
        </nav>
    </div>
</template>
