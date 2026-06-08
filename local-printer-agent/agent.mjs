import net from 'node:net';

const APP_URL = process.env.APP_URL;
const PRINT_AGENT_TOKEN = process.env.PRINT_AGENT_TOKEN;
const POLL_SECONDS = Number(process.env.POLL_SECONDS ?? 3);
const PRINT_DELAY_MS = Number(process.env.PRINT_DELAY_MS ?? 200);
const PRINT_CODEPAGE = (process.env.PRINT_CODEPAGE ?? 'cp860').toLowerCase();

if (!APP_URL || !PRINT_AGENT_TOKEN) {
    console.error('Configura APP_URL e PRINT_AGENT_TOKEN antes de iniciar o agente.');
    process.exit(1);
}

const endpoint = (path) => `${APP_URL.replace(/\/$/, '')}/api/print-agent/${path}`;

const api = async (path, options = {}) => {
    const response = await fetch(endpoint(path), {
        ...options,
        headers: {
            Authorization: `Bearer ${PRINT_AGENT_TOKEN}`,
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(options.headers ?? {}),
        },
    });

    if (!response.ok) {
        throw new Error(`${response.status} ${response.statusText}`);
    }

    return response.json();
};

const codepageCommands = {
    cp437: [0x1b, 0x74, 0x00],
    cp860: [0x1b, 0x74, 0x03],
    cp858: [0x1b, 0x74, 0x13],
};

const cp860 = new Map([
    ['Ç', 0x80], ['ü', 0x81], ['é', 0x82], ['â', 0x83], ['ã', 0x84], ['à', 0x85], ['Á', 0x86], ['ç', 0x87],
    ['ê', 0x88], ['Ê', 0x89], ['è', 0x8a], ['Í', 0x8b], ['Ô', 0x8c], ['ì', 0x8d], ['Ã', 0x8e], ['Â', 0x8f],
    ['É', 0x90], ['À', 0x91], ['È', 0x92], ['ô', 0x93], ['õ', 0x94], ['Ò', 0x95], ['Ú', 0x96], ['ù', 0x97],
    ['Ì', 0x98], ['Õ', 0x99], ['Ü', 0x9a], ['Ù', 0x9d], ['Ó', 0x9f], ['á', 0xa0], ['í', 0xa1], ['ó', 0xa2],
    ['ú', 0xa3], ['ñ', 0xa4], ['Ñ', 0xa5], ['ª', 0xa6], ['º', 0xa7],
]);

const bytes = (...valores) => Buffer.from(valores);

const limparPontuacao = (valor) => String(valor ?? '')
    .replace(/€/g, 'EUR')
    .replace(/[–—]/g, '-')
    .replace(/[“”]/g, '"')
    .replace(/[‘’]/g, "'");

const texto = (valor) => {
    const resultado = [];

    for (const char of limparPontuacao(valor)) {
        const codigo = char.charCodeAt(0);

        if (codigo <= 0x7f) {
            resultado.push(codigo);
            continue;
        }

        if (PRINT_CODEPAGE === 'cp860' && cp860.has(char)) {
            resultado.push(cp860.get(char));
            continue;
        }

        const ascii = char.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        for (const fallback of ascii) {
            const fallbackCodigo = fallback.charCodeAt(0);
            resultado.push(fallbackCodigo <= 0x7f ? fallbackCodigo : 0x3f);
        }
    }

    return Buffer.from(resultado);
};

const textoLinha = (valor) => Buffer.concat([texto(valor), bytes(0x0a)]);

const escpos = (job) => {
    const payload = job.payload ?? {};
    const linhaEscpos = (linha) => {
        if (typeof linha === 'string') {
            return textoLinha(linha);
        }

        const alinharCentro = linha?.alinhamento === 'centro';
        const tamanhoGrande = linha?.tamanho === 'grande';

        return Buffer.concat([
            alinharCentro ? bytes(0x1b, 0x61, 0x01) : bytes(0x1b, 0x61, 0x00),
            tamanhoGrande ? bytes(0x1d, 0x21, 0x11) : bytes(0x1d, 0x21, 0x00),
            textoLinha(linha?.texto ?? ''),
            bytes(0x1d, 0x21, 0x00),
            bytes(0x1b, 0x61, 0x00),
        ]);
    };

    return Buffer.concat([
        bytes(0x1b, 0x40),
        bytes(...(codepageCommands[PRINT_CODEPAGE] ?? codepageCommands.cp860)),
        bytes(0x1b, 0x61, 0x01),
        bytes(0x1b, 0x21, 0x18),
        textoLinha(payload.titulo ?? 'ARDC Santana'),
        bytes(0x1b, 0x21, 0x00),
        payload.subtitulo ? textoLinha(payload.subtitulo) : Buffer.alloc(0),
        bytes(0x1b, 0x61, 0x00),
        bytes(0x0a),
        ...((payload.linhas ?? []).map(linhaEscpos)),
        bytes(0x0a, 0x0a, 0x0a),
        payload.cortar === false ? Buffer.alloc(0) : bytes(0x1d, 0x56, 0x00),
    ]);
};

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const imprimir = (job) => new Promise((resolve, reject) => {
    let terminou = false;
    const concluir = () => {
        if (!terminou) {
            terminou = true;
            resolve();
        }
    };
    const falhar = (error) => {
        if (!terminou) {
            terminou = true;
            reject(error);
        }
    };

    const socket = net.createConnection({
        host: job.printer.host,
        port: Number(job.printer.porta ?? 9100),
        timeout: 6000,
    });

    socket.on('connect', () => {
        socket.setNoDelay(true);
        socket.write(escpos(job), () => socket.end());
    });

    socket.on('error', falhar);
    socket.on('timeout', () => {
        socket.destroy();
        falhar(new Error('Timeout a ligar a impressora.'));
    });
    socket.on('close', concluir);
});

const ciclo = async () => {
    try {
        const { jobs } = await api('jobs');

        for (const job of jobs ?? []) {
            try {
                await imprimir(job);
                await api(`jobs/${job.id}/done`, { method: 'POST', body: '{}' });
                console.log(`Impresso job #${job.id} em ${job.printer.nome}`);
                await sleep(PRINT_DELAY_MS);
            } catch (error) {
                await api(`jobs/${job.id}/fail`, { method: 'POST', body: JSON.stringify({ error: error.message }) });
                console.error(`Falhou job #${job.id}: ${error.message}`);
            }
        }
    } catch (error) {
        console.error(`Erro no agente: ${error.message}`);
    }
};

setInterval(ciclo, POLL_SECONDS * 1000);
ciclo();
