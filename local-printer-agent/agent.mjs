import net from 'node:net';

const APP_URL = process.env.APP_URL;
const PRINT_AGENT_TOKEN = process.env.PRINT_AGENT_TOKEN;
const POLL_SECONDS = Number(process.env.POLL_SECONDS ?? 3);

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
    const linhas = [
        '\x1B@',
        '\x1B!\x18',
        `${payload.titulo ?? 'ARDC Santana'}\n`,
        '\x1B!\x00',
        payload.subtitulo ? `${payload.subtitulo}\n` : '',
        '\n',
        ...(payload.linhas ?? []).map((linha) => `${linha}\n`),
        '\n\n\n',
        payload.cortar === false ? '' : '\x1D\x56\x00',
    ];

    return Buffer.from(linhas.join(''), 'binary');
};

const imprimir = (job) => new Promise((resolve, reject) => {
    const socket = net.createConnection({
        host: job.printer.host,
        port: Number(job.printer.porta ?? 9100),
        timeout: 6000,
    });

    socket.on('connect', () => {
        socket.write(escpos(job));
        socket.end();
    });

    socket.on('error', reject);
    socket.on('timeout', () => {
        socket.destroy();
        reject(new Error('Timeout a ligar a impressora.'));
    });
    socket.on('close', resolve);
});

const ciclo = async () => {
    try {
        const { jobs } = await api('jobs');

        for (const job of jobs ?? []) {
            try {
                await imprimir(job);
                await api(`jobs/${job.id}/done`, { method: 'POST', body: '{}' });
                console.log(`Impresso job #${job.id} em ${job.printer.nome}`);
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
