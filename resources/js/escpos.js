/**
 * Geração de ESC/POS no browser.
 *
 * É a mesma lógica do agente local (local-printer-agent/agent.mjs), portada
 * para o browser: o mesmo payload produz os mesmos bytes. Serve para imprimir
 * por WebUSB a partir do POS, sem agente nenhum — o único caminho possível nos
 * Chromebooks, e o único que envia o comando de corte.
 */

const codepageCommands = {
    cp437: [0x1b, 0x74, 0x00],
    cp860: [0x1b, 0x74, 0x03],
    cp858: [0x1b, 0x74, 0x13],
};

// Português na codepage 860, a mesma que o agente usa
const cp860 = new Map([
    ['Ç', 0x80], ['ü', 0x81], ['é', 0x82], ['â', 0x83], ['ã', 0x84], ['à', 0x85], ['Á', 0x86], ['ç', 0x87],
    ['ê', 0x88], ['Ê', 0x89], ['è', 0x8a], ['Í', 0x8b], ['Ô', 0x8c], ['ì', 0x8d], ['Ã', 0x8e], ['Â', 0x8f],
    ['É', 0x90], ['À', 0x91], ['È', 0x92], ['ô', 0x93], ['õ', 0x94], ['Ò', 0x95], ['Ú', 0x96], ['ù', 0x97],
    ['Ì', 0x98], ['Õ', 0x99], ['Ü', 0x9a], ['Ù', 0x9d], ['Ó', 0x9f], ['á', 0xa0], ['í', 0xa1], ['ó', 0xa2],
    ['ú', 0xa3], ['ñ', 0xa4], ['Ñ', 0xa5], ['ª', 0xa6], ['º', 0xa7],
]);

const juntar = (partes) => {
    const total = partes.reduce((soma, parte) => soma + parte.length, 0);
    const saida = new Uint8Array(total);
    let posicao = 0;

    for (const parte of partes) {
        saida.set(parte, posicao);
        posicao += parte.length;
    }

    return saida;
};

const bytes = (...valores) => Uint8Array.from(valores);

const limparPontuacao = (valor) => String(valor ?? '')
    .replace(/€/g, 'EUR')
    .replace(/[–—]/g, '-')
    .replace(/[“”]/g, '"')
    .replace(/[‘’]/g, "'");

const texto = (valor, codepage = 'cp860') => {
    const resultado = [];

    for (const char of limparPontuacao(valor)) {
        const codigo = char.charCodeAt(0);

        if (codigo <= 0x7f) {
            resultado.push(codigo);
            continue;
        }

        if (codepage === 'cp860' && cp860.has(char)) {
            resultado.push(cp860.get(char));
            continue;
        }

        // Sem correspondência: tira o acento e imprime a letra base
        const ascii = char.normalize('NFD').replace(/[̀-ͯ]/g, '');
        for (const fallback of ascii) {
            const codigoFallback = fallback.charCodeAt(0);
            resultado.push(codigoFallback <= 0x7f ? codigoFallback : 0x3f);
        }
    }

    return Uint8Array.from(resultado);
};

const textoLinha = (valor, codepage) => juntar([texto(valor, codepage), bytes(0x0a)]);

/**
 * Converte o payload de um trabalho de impressão nos bytes ESC/POS.
 * Aceita o mesmo formato guardado em print_jobs.payload.
 */
export const escpos = (payload = {}, codepage = 'cp860') => {
    const linhaEscpos = (linha) => {
        if (typeof linha === 'string') {
            return textoLinha(linha, codepage);
        }

        const alinharCentro = linha?.alinhamento === 'centro';
        const tamanhoGrande = linha?.tamanho === 'grande';

        return juntar([
            alinharCentro ? bytes(0x1b, 0x61, 0x01) : bytes(0x1b, 0x61, 0x00),
            tamanhoGrande ? bytes(0x1d, 0x21, 0x11) : bytes(0x1d, 0x21, 0x00),
            textoLinha(linha?.texto ?? '', codepage),
            bytes(0x1d, 0x21, 0x00),
            bytes(0x1b, 0x61, 0x00),
        ]);
    };

    return juntar([
        // ESC p 0 — abre a gaveta do dinheiro
        payload.abrir_caixa ? bytes(0x1b, 0x70, 0x00, 0x19, 0xfa) : new Uint8Array(0),
        bytes(0x1b, 0x40),
        bytes(...(codepageCommands[codepage] ?? codepageCommands.cp860)),
        bytes(0x1b, 0x61, 0x01),
        bytes(0x1b, 0x21, 0x18),
        textoLinha(payload.titulo ?? 'ARDC Santana', codepage),
        bytes(0x1b, 0x21, 0x00),
        payload.subtitulo ? textoLinha(payload.subtitulo, codepage) : new Uint8Array(0),
        bytes(0x1b, 0x61, 0x00),
        bytes(0x0a),
        ...((payload.linhas ?? []).map(linhaEscpos)),
        bytes(0x0a, 0x0a, 0x0a, 0x0a, 0x0a),
        // GS V 0 — corte do papel
        payload.cortar === false ? new Uint8Array(0) : bytes(0x1d, 0x56, 0x00),
    ]);
};

/**
 * Impressora USB ligada diretamente ao browser.
 *
 * O ChromeOS só deixa abrir a impressora por WebUSB se ela NÃO estiver
 * adicionada nas definições de impressão do sistema — se lá estiver, o
 * ChromeOS fica com ela e o pedido falha.
 */
export class ImpressoraUsb {
    constructor() {
        this.device = null;
        this.interfaceNumber = null;
        this.endpoint = null;
    }

    static suportado() {
        return typeof navigator !== 'undefined' && !!navigator.usb;
    }

    /** Impressoras já autorizadas antes neste dispositivo, sem voltar a perguntar. */
    async reconectar() {
        if (!ImpressoraUsb.suportado()) {
            return false;
        }

        const dispositivos = await navigator.usb.getDevices();
        const impressora = dispositivos.find((d) => this.temInterfaceDeImpressora(d));

        if (!impressora) {
            return false;
        }

        await this.abrir(impressora);

        return true;
    }

    /** Pede ao utilizador que escolha a impressora. Exige clique. */
    async escolher() {
        if (!ImpressoraUsb.suportado()) {
            throw new Error('Este browser não suporta WebUSB. Usa o Chrome ou um Chromebook.');
        }

        // classCode 7 = impressora
        const device = await navigator.usb.requestDevice({ filters: [{ classCode: 7 }] });
        await this.abrir(device);

        return device;
    }

    temInterfaceDeImpressora(device) {
        return (device.configurations ?? []).some((config) => (config.interfaces ?? []).some(
            (iface) => (iface.alternates ?? []).some((alt) => alt.interfaceClass === 7),
        ));
    }

    async abrir(device) {
        this.device = device;

        if (!device.opened) {
            await device.open();
        }

        if (device.configuration === null) {
            await device.selectConfiguration(1);
        }

        const interfaces = device.configuration?.interfaces ?? [];
        let escolhida = null;
        let alternativa = null;

        for (const iface of interfaces) {
            for (const alt of iface.alternates ?? []) {
                if (alt.interfaceClass === 7) {
                    escolhida = iface;
                    alternativa = alt;
                    break;
                }
            }
            if (escolhida) break;
        }

        if (!escolhida) {
            throw new Error('O dispositivo escolhido não tem interface de impressora.');
        }

        await device.claimInterface(escolhida.interfaceNumber);
        this.interfaceNumber = escolhida.interfaceNumber;

        const saida = (alternativa.endpoints ?? []).find(
            (ep) => ep.direction === 'out' && ep.type === 'bulk',
        );

        if (!saida) {
            throw new Error('A impressora não tem canal de saída USB (bulk out).');
        }

        this.endpoint = saida.endpointNumber;
    }

    get ligada() {
        return !!(this.device && this.device.opened && this.endpoint !== null);
    }

    get nome() {
        if (!this.device) return null;

        return [this.device.manufacturerName, this.device.productName].filter(Boolean).join(' ')
            || `USB ${this.device.vendorId}:${this.device.productId}`;
    }

    /** Envia os bytes em blocos, que é o que as térmicas aguentam melhor. */
    async enviar(dados, tamanhoBloco = 4096) {
        if (!this.ligada) {
            throw new Error('Impressora não ligada.');
        }

        for (let inicio = 0; inicio < dados.length; inicio += tamanhoBloco) {
            const bloco = dados.slice(inicio, inicio + tamanhoBloco);
            const resultado = await this.device.transferOut(this.endpoint, bloco);

            if (resultado.status !== 'ok') {
                throw new Error(`A impressora recusou os dados (${resultado.status}).`);
            }
        }
    }

    async imprimir(payload, codepage = 'cp860') {
        await this.enviar(escpos(payload, codepage));
    }
}
