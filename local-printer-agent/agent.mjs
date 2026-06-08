import net from 'node:net';

const APP_URL = process.env.APP_URL;
const PRINT_AGENT_TOKEN = process.env.PRINT_AGENT_TOKEN;
const POLL_SECONDS = Number(process.env.POLL_SECONDS ?? 3);
const PRINT_DELAY_MS = Number(process.env.PRINT_DELAY_MS ?? 200);

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

const escpos = (job) => {
    const payload = job.payload ?? {};
    const linhaEscpos = (linha) => {
        if (typeof linha === 'string') {
            return `${linha}\n`;
        }

        const texto = linha?.texto ?? '';
        const alinharCentro = linha?.alinhamento === 'centro';
        const tamanhoGrande = linha?.tamanho === 'grande';

        return [
            alinharCentro ? '\x1B\x61\x01' : '\x1B\x61\x00',
            tamanhoGrande ? '\x1D\x21\x11' : '\x1D\x21\x00',
            `${texto}\n`,
            '\x1D\x21\x00',
            '\x1B\x61\x00',
        ].join('');
    };

    const linhas = [
        '\x1B@',
        '\x1B\x61\x01',
        '\x1B!\x18',
        `${payload.titulo ?? 'ARDC Santana'}\n`,
        '\x1B!\x00',
        payload.subtitulo ? `${payload.subtitulo}\n` : '',
        '\x1B\x61\x00',
        '\n',
        ...(payload.linhas ?? []).map(linhaEscpos),
        '\n\n\n',
        payload.cortar === false ? '' : '\x1D\x56\x00',
    ];

    return Buffer.from(linhas.join(''), 'binary');
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
