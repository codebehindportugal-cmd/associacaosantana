import net from 'node:net';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { randomUUID } from 'node:crypto';

const APP_URL = process.env.APP_URL;
const PRINT_AGENT_TOKEN = process.env.PRINT_AGENT_TOKEN;
const POLL_SECONDS = Number(process.env.POLL_SECONDS ?? 3);
const PRINT_DELAY_MS = Number(process.env.PRINT_DELAY_MS ?? 200);
const PRINT_CODEPAGE = (process.env.PRINT_CODEPAGE ?? 'cp860').toLowerCase();
// Identifica este computador. Com varios postos, cada agente so vai buscar
// os trabalhos das impressoras que lhe estao atribuidas no backoffice.
const AGENTE = (process.env.AGENTE ?? '').trim();

if (!APP_URL || !PRINT_AGENT_TOKEN) {
    console.error('Configura APP_URL e PRINT_AGENT_TOKEN antes de iniciar o agente.');
    process.exit(1);
}

const endpoint = (caminho) => {
    const url = `${APP_URL.replace(/\/$/, '')}/api/print-agent/${caminho}`;

    return AGENTE ? `${url}${url.includes('?') ? '&' : '?'}agente=${encodeURIComponent(AGENTE)}` : url;
};

const api = async (caminho, options = {}) => {
    const response = await fetch(endpoint(caminho), {
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

    // ESC p 0 t1 t2 — abre gaveta de dinheiro (pin 2, 50ms on, 500ms off)
    const abrirCaixa = payload.abrir_caixa ? bytes(0x1b, 0x70, 0x00, 0x19, 0xfa) : Buffer.alloc(0);

    return Buffer.concat([
        abrirCaixa,
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
        bytes(0x0a, 0x0a, 0x0a, 0x0a, 0x0a),
        payload.cortar === false ? Buffer.alloc(0) : bytes(0x1d, 0x56, 0x00),
    ]);
};

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const imprimirRede = (job, dados) => new Promise((resolve, reject) => {
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
        socket.write(dados, () => socket.end());
    });

    socket.on('error', falhar);
    socket.on('timeout', () => {
        socket.destroy();
        falhar(new Error('Timeout a ligar a impressora.'));
    });
    socket.on('close', concluir);
});

// ---------------------------------------------------------------------------
// Impressora USB
//
// Windows: a impressora tem de estar instalada (Dispositivos e Impressoras) e
// o campo "dispositivo" e o nome exacto com que la aparece. Os bytes ESC/POS
// seguem em modo RAW pelo winspool, via PowerShell — o mesmo caminho que ja
// usamos para abrir a gaveta do dinheiro.
//
// Linux/Raspberry: "dispositivo" e o ficheiro do sistema, normalmente
// /dev/usb/lp0.
// ---------------------------------------------------------------------------
const PS_RAW_PRINT = [
    'param([string]$Printer, [string]$Path)',
    "$ErrorActionPreference = 'Stop'",
    'Add-Type -TypeDefinition @"',
    'using System;',
    'using System.Runtime.InteropServices;',
    'public class RawPrinterHelper {',
    '    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]',
    '    public class DOCINFOW {',
    '        [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;',
    '        [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;',
    '        [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;',
    '    }',
    '    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]',
    '    public static extern bool OpenPrinter(string pPrinterName, out IntPtr phPrinter, IntPtr pDefault);',
    '    [DllImport("winspool.drv", SetLastError = true)]',
    '    public static extern bool ClosePrinter(IntPtr hPrinter);',
    '    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]',
    '    public static extern bool StartDocPrinter(IntPtr hPrinter, int level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOW di);',
    '    [DllImport("winspool.drv", SetLastError = true)]',
    '    public static extern bool EndDocPrinter(IntPtr hPrinter);',
    '    [DllImport("winspool.drv", SetLastError = true)]',
    '    public static extern bool StartPagePrinter(IntPtr hPrinter);',
    '    [DllImport("winspool.drv", SetLastError = true)]',
    '    public static extern bool EndPagePrinter(IntPtr hPrinter);',
    '    [DllImport("winspool.drv", SetLastError = true)]',
    '    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);',
    '    public static void Send(string printerName, byte[] dados) {',
    '        IntPtr hPrinter;',
    '        if (!OpenPrinter(printerName, out hPrinter, IntPtr.Zero)) {',
    '            throw new Exception("Nao foi possivel abrir a impressora: " + printerName);',
    '        }',
    '        try {',
    '            DOCINFOW di = new DOCINFOW();',
    '            di.pDocName = "Talao";',
    '            di.pDataType = "RAW";',
    '            if (!StartDocPrinter(hPrinter, 1, di)) { throw new Exception("StartDocPrinter falhou."); }',
    '            try {',
    '                if (!StartPagePrinter(hPrinter)) { throw new Exception("StartPagePrinter falhou."); }',
    '                IntPtr ponteiro = Marshal.AllocCoTaskMem(dados.Length);',
    '                try {',
    '                    Marshal.Copy(dados, 0, ponteiro, dados.Length);',
    '                    int escritos;',
    '                    if (!WritePrinter(hPrinter, ponteiro, dados.Length, out escritos)) {',
    '                        throw new Exception("WritePrinter falhou.");',
    '                    }',
    '                } finally {',
    '                    Marshal.FreeCoTaskMem(ponteiro);',
    '                }',
    '                EndPagePrinter(hPrinter);',
    '            } finally {',
    '                EndDocPrinter(hPrinter);',
    '            }',
    '        } finally {',
    '            ClosePrinter(hPrinter);',
    '        }',
    '    }',
    '}',
    '"@',
    '[RawPrinterHelper]::Send($Printer, [System.IO.File]::ReadAllBytes($Path))',
].join('\n');

let caminhoScriptPs = null;

const scriptPowerShell = async () => {
    if (!caminhoScriptPs) {
        caminhoScriptPs = path.join(os.tmpdir(), 'ardc-raw-print.ps1');
        await fs.writeFile(caminhoScriptPs, PS_RAW_PRINT, 'utf8');
    }

    return caminhoScriptPs;
};

const correr = (comando, args) => new Promise((resolve, reject) => {
    execFile(comando, args, { timeout: 20000, windowsHide: true }, (error, stdout, stderr) => {
        if (error) {
            reject(new Error(String(stderr || stdout || error.message).trim().split('\n')[0]));

            return;
        }

        resolve(stdout);
    });
});

const imprimirUsb = async (job, dados) => {
    const dispositivo = job.printer.dispositivo;

    if (!dispositivo) {
        throw new Error('Impressora USB sem dispositivo definido no backoffice.');
    }

    if (process.platform !== 'win32') {
        await fs.writeFile(dispositivo, dados);

        return;
    }

    const ficheiro = path.join(os.tmpdir(), `ardc-talao-${randomUUID()}.bin`);
    await fs.writeFile(ficheiro, dados);

    try {
        await correr('powershell.exe', [
            '-NoProfile',
            '-NonInteractive',
            '-ExecutionPolicy', 'Bypass',
            '-File', await scriptPowerShell(),
            '-Printer', dispositivo,
            '-Path', ficheiro,
        ]);
    } finally {
        await fs.unlink(ficheiro).catch(() => {});
    }
};

const imprimir = async (job) => {
    const dados = escpos(job);

    if ((job.printer.tipo ?? 'rede') === 'usb') {
        await imprimirUsb(job, dados);

        return;
    }

    await imprimirRede(job, dados);
};

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

console.log(AGENTE
    ? `Agente "${AGENTE}" a arrancar. So trata das impressoras atribuidas a este posto.`
    : 'Agente sem AGENTE definido. Trata das impressoras sem posto atribuido.');

setInterval(ciclo, POLL_SECONDS * 1000);
ciclo();
