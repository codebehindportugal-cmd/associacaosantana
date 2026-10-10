/**
 * Talões do pré-pagamento montados no próprio computador, para o POS que
 * trabalha sem internet.
 *
 * É uma cópia fiel do que o servidor faz em App\Services\PrintJobService e
 * PosBarController::taloesDoPedido (modo webusb): o mesmo pedido dá as mesmas
 * linhas. O teste tests/Feature/Pos/TaloesParidadeTest.php compara as duas
 * versões — se mudares uma, muda a outra.
 *
 * Venda:  { codigo, ponto, operador, items: [{ nome, quantidade, preco, secao, talao_individual }],
 *           total, caucao_cobrada, caucao_descontada, valor_recebido, troco, doacao, metodo, juntar }
 * Config: { titulo, cabecalho: [linha], rodape: [texto], instrucoes: [linha], por_seccao, metodos }
 */

const TRACOS = '------------------------------';

export const GRUPOS_JUNTOS = {
    cozinha: [],
    sobremesas: ['sobremesas'],
    bebidas: ['bebidas', 'bar', 'cafe'],
};

const NOMES_SECAO = {
    bebidas: 'Bebidas',
    bar: 'Bar',
    cafe: 'Cafe',
    frango: 'Frango',
    acompanhamentos: 'Acompanhamentos',
    comida: 'Comida',
    cozinha: 'Cozinha',
    sobremesas: 'Sobremesas',
    servico: 'Servico',
};

/** number_format($v, 2, ',', ' ') . ' EUR' */
export const euros = (valor) => {
    const negativo = Number(valor) < 0;
    const [inteiro, decimal] = Math.abs(Math.round(Number(valor || 0) * 100) / 100).toFixed(2).split('.');
    const milhares = inteiro.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    return `${negativo ? '-' : ''}${milhares},${decimal} EUR`;
};

export const nomeSecao = (secao) => NOMES_SECAO[secao]
    ?? (() => { const s = String(secao || 'Tasquinha').replace(/_/g, ' '); return s.charAt(0).toUpperCase() + s.slice(1); })();

export const grupoDaSeccao = (secao) => Object.keys(GRUPOS_JUNTOS).find((g) => GRUPOS_JUNTOS[g].includes(secao)) ?? 'cozinha';

const senhaGrande = (venda) => (venda.codigo ? [{ texto: `SENHA #${venda.codigo}`, alinhamento: 'centro', tamanho: 'grande' }] : []);

/** { secao: [nome, nome, ...] } — uma entrada por unidade, pela ordem da venda */
export const unidadesPorSeccao = (items) => {
    const grupos = new Map();
    for (const item of items) {
        const secao = item.secao || 'outros';
        if (!grupos.has(secao)) grupos.set(secao, []);
        for (let u = 0; u < Number(item.quantidade); u++) grupos.get(secao).push(item.nome ?? 'Produto');
    }
    return grupos;
};

export const taloesJuntos = (items, juntar = {}) => {
    const grupos = new Map(Object.keys(GRUPOS_JUNTOS).map((g) => [g, new Map()]));
    for (const [secao, unidades] of unidadesPorSeccao(items)) grupos.get(grupoDaSeccao(secao)).set(secao, unidades);

    const taloes = [];
    for (const [grupo, porSeccao] of grupos) {
        if (!porSeccao.size) continue;
        if (juntar[grupo]) {
            taloes.push({ seccoes: porSeccao });
            continue;
        }
        for (const [secao, unidades] of porSeccao) for (const nome of unidades) taloes.push({ nome, secao });
    }
    return taloes;
};

export const payloadTalaoUnitario = (venda, config, nomeProduto) => ({
    titulo: config.titulo,
    linhas: [...senhaGrande(venda), { texto: `1x ${nomeProduto}`, alinhamento: 'centro', tamanho: 'grande' }],
    cortar: true,
    compacto: true,
});

export const payloadFolhaJunta = (venda, config, porSeccao, indice, total, hora) => {
    const linhas = [...config.cabecalho, `Ponto: ${venda.ponto || 'Bar'}`, `Hora: ${hora}`, ...senhaGrande(venda)];
    for (const [secao, unidades] of porSeccao) {
        linhas.push(TRACOS, { texto: nomeSecao(secao).toUpperCase(), alinhamento: 'centro' });
        const contagem = new Map();
        for (const nome of unidades) contagem.set(nome, (contagem.get(nome) ?? 0) + 1);
        for (const [nome, quantidade] of contagem) linhas.push({ texto: `${quantidade}x ${nome}`, alinhamento: 'centro', tamanho: 'grande' });
    }
    linhas.push(TRACOS);
    if (total > 1) linhas.push({ texto: `Talao ${indice} de ${total}`, alinhamento: 'centro' });
    return { titulo: config.titulo, subtitulo: 'SENHA', linhas: [...linhas, ...config.instrucoes], cortar: true };
};

const linhasCaucao = (venda, total) => {
    const cobrada = Number(venda.caucao_cobrada || 0);
    const descontada = Number(venda.caucao_descontada || 0);
    if (cobrada <= 0 && descontada <= 0) return [`Total: ${euros(total)}`];
    const final = total + cobrada - descontada;
    return [
        `Produtos: ${euros(total)}`,
        ...(cobrada > 0 ? [`Caucao: ${euros(cobrada)}`] : []),
        ...(descontada > 0 ? [`Caucao devolvida: -${euros(descontada)}`] : []),
        final < 0 ? `A devolver: ${euros(Math.abs(final))}` : `Total: ${euros(final)}`,
    ];
};

/** A conta (sai no fim, para quem está na caixa conferir) */
export const payloadConta = (venda, config, hora, subtitulo = 'CONTA') => {
    const total = Number(venda.total) || venda.items.reduce((s, i) => s + Number(i.preco) * Number(i.quantidade), 0);
    const metodo = venda.metodo || 'dinheiro';
    const valorRecebido = venda.valor_recebido !== null && venda.valor_recebido !== undefined
        ? Number(venda.valor_recebido)
        : Math.max(0, total + Number(venda.caucao_cobrada || 0) - Number(venda.caucao_descontada || 0));
    const doacao = Number(venda.doacao || 0);

    return {
        titulo: config.titulo,
        subtitulo,
        linhas: [
            ...config.cabecalho,
            `Ponto: ${venda.ponto || 'Bar/Cafe'}`,
            `Operador: ${venda.operador || 'Sem operador'}`,
            `Hora: ${hora}`,
            ...senhaGrande(venda),
            TRACOS,
            ...venda.items.map((i) => `${i.quantidade}x ${i.nome ?? 'Produto'}  ${euros(Number(i.preco) * Number(i.quantidade))}`),
            TRACOS,
            ...linhasCaucao(venda, total),
            `Pagamento: ${config.metodos?.[metodo] ?? metodo}`,
            ...(metodo === 'dinheiro' ? [`Recebido: ${euros(valorRecebido)}`, `Troco: ${euros(Number(venda.troco || 0))}`] : []),
            ...(doacao > 0 ? [`Donativo: ${euros(doacao)}`] : []),
            '',
            ...config.rodape,
        ],
        cortar: true,
    };
};

/**
 * Todos os talões de uma venda, pela ordem de impressão (como o servidor no
 * modo webusb): senhas do cliente e a conta no fim.
 */
export const taloesDaVenda = (venda, config, hora) => {
    const juntar = venda.juntar ?? {};
    const payloads = [];

    if (config.por_seccao && Object.values(juntar).some(Boolean)) {
        const taloes = taloesJuntos(venda.items, juntar);
        taloes.forEach((t, i) => payloads.push(t.seccoes
            ? payloadFolhaJunta(venda, config, t.seccoes, i + 1, taloes.length, hora)
            : payloadTalaoUnitario(venda, config, t.nome)));
    } else {
        // Pré-pagamento: uma senha por unidade; fora dele, só os produtos com talão individual
        const items = config.por_seccao ? venda.items : venda.items.filter((i) => i.talao_individual);
        for (const [, unidades] of unidadesPorSeccao(items)) for (const nome of unidades) payloads.push(payloadTalaoUnitario(venda, config, nome));
    }

    if (payloads.length) payloads.push(payloadConta(venda, config, hora));
    return payloads;
};

export const marcarSegundaVia = (payload) => ({
    ...payload,
    subtitulo: `${payload.subtitulo ?? ''} 2a VIA`.trim(),
    linhas: [{ texto: '*** 2a VIA ***', alinhamento: 'centro', tamanho: 'grande' }, ...(payload.linhas ?? [])],
    abrir_caixa: false,
});

export const payloadAnulacao = (venda, config, devolver, { vendidaAs, anuladaAs, por, motivo }) => ({
    titulo: config.titulo,
    subtitulo: 'ANULADA',
    linhas: [
        { texto: `SENHA #${venda.codigo}`, alinhamento: 'centro', tamanho: 'grande' },
        { texto: 'ANULADA', alinhamento: 'centro', tamanho: 'grande' },
        TRACOS,
        `Ponto: ${venda.ponto || 'Bar'}`,
        `Vendida as: ${vendidaAs}`,
        `Anulada as: ${anuladaAs}`,
        `Por: ${por || '-'}`,
        `Motivo: ${motivo || '-'}`,
        `Pagamento: ${config.metodos?.[venda.metodo || 'dinheiro'] ?? venda.metodo}`,
        TRACOS,
        { texto: `Devolver: ${euros(devolver)}`, alinhamento: 'centro', tamanho: 'grande' },
    ],
    cortar: true,
});

export const payloadCaucao = (config, { ponto, operador, hora, quantidade, nome, valorTotal }) => ({
    titulo: config.titulo,
    subtitulo: 'DEVOLUCAO CAUCAO',
    linhas: [
        `Ponto: ${ponto || 'Bar'}`,
        `Operador: ${operador || 'Sem operador'}`,
        `Hora: ${hora}`,
        TRACOS,
        `${quantidade}x ${nome ?? 'Artigo'}  ${euros(valorTotal)}`,
        TRACOS,
        { texto: `DEVOLVIDO: ${euros(valorTotal)}`, alinhamento: 'centro', tamanho: 'grande' },
    ],
    cortar: true,
    abrir_caixa: true,
});
