#!/bin/bash
# ================================================
# Setup do Agente de Impressão - Raspberry Pi
# ARDC Santana
# ================================================

set -e

echo ""
echo "================================================"
echo "  Setup Agente de Impressão - ARDC Santana"
echo "================================================"
echo ""

# --- 1. Instalar NVM + Node.js ---
echo "[1/4] Instalar Node.js via nvm..."
if [ ! -d "$HOME/.nvm" ]; then
    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
fi

export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

nvm install 20
nvm use 20
nvm alias default 20

echo "    Node $(node --version) instalado."

# --- 2. Criar pasta do agente ---
echo ""
echo "[2/4] Criar pasta do agente..."
mkdir -p ~/printer-agent
cd ~/printer-agent

# --- 3. Criar agent.mjs ---
echo ""
echo "[3/4] Criar ficheiros do agente..."

cat > agent.mjs << 'AGENT_EOF'
import net from 'node:net';

const APP_URL           = process.env.APP_URL;
const PRINT_AGENT_TOKEN = process.env.PRINT_AGENT_TOKEN;
const POLL_SECONDS      = Number(process.env.POLL_SECONDS  ?? 3);
const PRINT_DELAY_MS    = Number(process.env.PRINT_DELAY_MS ?? 200);
const PRINT_CODEPAGE    = (process.env.PRINT_CODEPAGE ?? 'cp860').toLowerCase();

if (!APP_URL || !PRINT_AGENT_TOKEN) {
    console.error('Configura APP_URL e PRINT_AGENT_TOKEN no ficheiro .env antes de iniciar.');
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
    if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);
    return response.json();
};

const codepageCommands = {
    cp437: [0x1b, 0x74, 0x00],
    cp860: [0x1b, 0x74, 0x03],
    cp858: [0x1b, 0x74, 0x13],
};

const cp860 = new Map([
    ['Ç',0x80],['ü',0x81],['é',0x82],['â',0x83],['ã',0x84],['à',0x85],['Á',0x86],['ç',0x87],
    ['ê',0x88],['Ê',0x89],['è',0x8a],['Í',0x8b],['Ô',0x8c],['ì',0x8d],['Ã',0x8e],['Â',0x8f],
    ['É',0x90],['À',0x91],['È',0x92],['ô',0x93],['õ',0x94],['Ò',0x95],['Ú',0x96],['ù',0x97],
    ['Ì',0x98],['Õ',0x99],['Ü',0x9a],['Ù',0x9d],['Ó',0x9f],['á',0xa0],['í',0xa1],['ó',0xa2],
    ['ú',0xa3],['ñ',0xa4],['Ñ',0xa5],['ª',0xa6],['º',0xa7],
]);

const bytes = (...v) => Buffer.from(v);
const limpar = (v) => String(v ?? '').replace(/€/g,'EUR').replace(/[–—]/g,'-').replace(/[""]/g,'"').replace(/['']/g,"'");

const texto = (valor) => {
    const r = [];
    for (const char of limpar(valor)) {
        const c = char.charCodeAt(0);
        if (c <= 0x7f) { r.push(c); continue; }
        if (PRINT_CODEPAGE === 'cp860' && cp860.has(char)) { r.push(cp860.get(char)); continue; }
        const ascii = char.normalize('NFD').replace(/[̀-ͯ]/g,'');
        for (const fb of ascii) { const fc = fb.charCodeAt(0); r.push(fc <= 0x7f ? fc : 0x3f); }
    }
    return Buffer.from(r);
};

const textoLinha = (v) => Buffer.concat([texto(v), bytes(0x0a)]);

const escpos = (job) => {
    const p = job.payload ?? {};
    const linha = (l) => {
        if (typeof l === 'string') return textoLinha(l);
        const centro = l?.alinhamento === 'centro';
        const grande = l?.tamanho === 'grande';
        return Buffer.concat([
            centro ? bytes(0x1b,0x61,0x01) : bytes(0x1b,0x61,0x00),
            grande ? bytes(0x1d,0x21,0x11) : bytes(0x1d,0x21,0x00),
            textoLinha(l?.texto ?? ''),
            bytes(0x1d,0x21,0x00), bytes(0x1b,0x61,0x00),
        ]);
    };
    return Buffer.concat([
        bytes(0x1b,0x40),
        bytes(...(codepageCommands[PRINT_CODEPAGE] ?? codepageCommands.cp860)),
        bytes(0x1b,0x61,0x01), bytes(0x1b,0x21,0x18),
        textoLinha(p.titulo ?? 'ARDC Santana'),
        bytes(0x1b,0x21,0x00),
        p.subtitulo ? textoLinha(p.subtitulo) : Buffer.alloc(0),
        bytes(0x1b,0x61,0x00), bytes(0x0a),
        ...((p.linhas ?? []).map(linha)),
        bytes(0x0a,0x0a,0x0a,0x0a,0x0a),
        p.cortar === false ? Buffer.alloc(0) : bytes(0x1d,0x56,0x00),
    ]);
};

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const imprimir = (job) => new Promise((resolve, reject) => {
    let done = false;
    const ok  = () => { if (!done) { done = true; resolve(); } };
    const err = (e) => { if (!done) { done = true; reject(e); } };
    const s = net.createConnection({ host: job.printer.host, port: Number(job.printer.porta ?? 9100), timeout: 6000 });
    s.on('connect', () => { s.setNoDelay(true); s.write(escpos(job), () => s.end()); });
    s.on('error', err);
    s.on('timeout', () => { s.destroy(); err(new Error('Timeout a ligar à impressora.')); });
    s.on('close', ok);
});

const ciclo = async () => {
    try {
        const { jobs } = await api('jobs');
        for (const job of jobs ?? []) {
            try {
                await imprimir(job);
                await api(`jobs/${job.id}/done`, { method: 'POST', body: '{}' });
                console.log(`[${new Date().toLocaleTimeString()}] Impresso job #${job.id} em ${job.printer.nome}`);
                await sleep(PRINT_DELAY_MS);
            } catch (e) {
                await api(`jobs/${job.id}/fail`, { method: 'POST', body: JSON.stringify({ error: e.message }) });
                console.error(`[${new Date().toLocaleTimeString()}] Falhou job #${job.id}: ${e.message}`);
            }
        }
    } catch (e) {
        console.error(`[${new Date().toLocaleTimeString()}] Erro no agente: ${e.message}`);
    }
};

console.log(`Agente iniciado. A verificar jobs a cada ${POLL_SECONDS}s...`);
setInterval(ciclo, POLL_SECONDS * 1000);
ciclo();
AGENT_EOF

# --- 4. Criar .env ---
cat > .env << 'ENV_EOF'
APP_URL=https://ardcsantana.ateneya.com
PRINT_AGENT_TOKEN=trocar-por-token-seguro
POLL_SECONDS=3
PRINT_DELAY_MS=200
PRINT_CODEPAGE=cp860
ENV_EOF

# --- 5. Instalar como serviço systemd ---
echo ""
echo "[4/4] Configurar arranque automático..."

NODE_PATH=$(which node)

sudo tee /etc/systemd/system/printer-agent.service > /dev/null << EOF
[Unit]
Description=Agente de Impressao ARDC Santana
After=network.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$HOME/printer-agent
ExecStart=$NODE_PATH --env-file=.env agent.mjs
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable printer-agent
sudo systemctl start printer-agent

echo ""
echo "================================================"
echo "  CONCLUIDO!"
echo ""
echo "  IMPORTANTE: edita o ficheiro .env:"
echo "  nano ~/printer-agent/.env"
echo "  -> Muda PRINT_AGENT_TOKEN pelo token real"
echo ""
echo "  Comandos úteis:"
echo "  sudo systemctl status printer-agent"
echo "  sudo journalctl -u printer-agent -f"
echo "  sudo systemctl restart printer-agent"
echo "================================================"
echo ""
