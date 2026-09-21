#!/bin/bash
# ================================================
# Setup do Agente de Impressao - Raspberry Pi
# ARDC Santana
#
# Uso (dentro da pasta local-printer-agent copiada para o Pi):
#   bash setup-pi.sh
# Instala o Node, copia o agent.mjs desta pasta para ~/printer-agent,
# cria o .env e poe o agente a arrancar sozinho (systemd).
# Voltar a correr o script atualiza o agente e mantem o .env existente.
# ================================================

set -e

ORIGEM="$(cd "$(dirname "$0")" && pwd)"
DESTINO="$HOME/printer-agent"

echo ""
echo "================================================"
echo "  Setup Agente de Impressao - ARDC Santana"
echo "================================================"
echo ""

if [ ! -f "$ORIGEM/agent.mjs" ]; then
    echo "[!] Nao encontrei o agent.mjs ao lado deste script."
    echo "    Copia a pasta local-printer-agent inteira para o Raspberry e corre o script la dentro."
    exit 1
fi

# --- 1. Node.js 20 (via nvm) ---
echo "[1/5] Node.js..."
export NVM_DIR="$HOME/.nvm"
if [ ! -d "$NVM_DIR" ]; then
    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
fi
# shellcheck disable=SC1091
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
nvm install 20 >/dev/null
nvm alias default 20 >/dev/null
nvm use 20 >/dev/null
NODE_BIN="$(nvm which 20)"
echo "    Node $("$NODE_BIN" --version)"

# --- 2. Copiar o agente ---
echo ""
echo "[2/5] Copiar o agente para $DESTINO..."
mkdir -p "$DESTINO"
cp "$ORIGEM/agent.mjs" "$DESTINO/agent.mjs"

# --- 3. .env ---
echo ""
echo "[3/5] Configuracao (.env)..."
if [ -f "$DESTINO/.env" ]; then
    echo "    Ja existe $DESTINO/.env — mantido. (Para refazer: apaga-o e corre de novo.)"
else
    read -r -p "    Endereco do site [https://ardcsantana.ateneya.com]: " APP_URL
    APP_URL="${APP_URL:-https://ardcsantana.ateneya.com}"

    TOKEN=""
    while [ -z "$TOKEN" ]; do
        read -r -s -p "    Token do agente (PRINT_AGENT_TOKEN do .env do servidor): " TOKEN
        echo ""
    done

    echo "    Nome do posto: tem de ser igual ao campo 'Posto (agente)' das impressoras"
    echo "    no backoffice. Se esse campo estiver vazio (—), deixa em branco."
    read -r -p "    Nome do posto [em branco]: " AGENTE

    cat > "$DESTINO/.env" << ENV_EOF
APP_URL=$APP_URL
PRINT_AGENT_TOKEN=$TOKEN
POLL_SECONDS=3
PRINT_DELAY_MS=200
PRINT_CODEPAGE=cp860
AGENTE=$AGENTE
ENV_EOF
    chmod 600 "$DESTINO/.env"
    echo "    .env criado."
fi

# --- 4. Servico systemd ---
echo ""
echo "[4/5] Arranque automatico (systemd)..."
sudo tee /etc/systemd/system/printer-agent.service > /dev/null << EOF
[Unit]
Description=Agente de Impressao ARDC Santana
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=$USER
WorkingDirectory=$DESTINO
ExecStart=$NODE_BIN $DESTINO/agent.mjs
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable printer-agent >/dev/null
sudo systemctl restart printer-agent

# --- 5. Resumo ---
echo ""
echo "[5/5] Rede deste Raspberry:"
hostname -I | tr ' ' '\n' | grep -v '^$' | sed 's/^/    IP: /'
echo "    (Tem de estar na mesma rede das impressoras, ex: 10.53.218.x, e ter internet.)"

echo ""
echo "================================================"
echo "  CONCLUIDO! O agente ja esta a correr."
echo ""
echo "  Testar uma impressora (sem passar pelo site):"
echo "    $NODE_BIN $DESTINO/agent.mjs --teste 10.53.218.205"
echo ""
echo "  Ver o que o agente esta a fazer:"
echo "    sudo journalctl -u printer-agent -f"
echo ""
echo "  Outros:"
echo "    sudo systemctl status printer-agent"
echo "    sudo systemctl restart printer-agent"
echo "    nano $DESTINO/.env   (depois: restart)"
echo "================================================"
echo ""
