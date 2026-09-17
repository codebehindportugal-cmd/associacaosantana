# associacaosantana — CLAUDE.md

## O que é este projeto
Site + backoffice + sistema POS da **ARDC Santana** (Associação Recreativa e Desportiva de Cantanhede / Santana). Laravel 12 + Inertia.js + Vue 3 + Tailwind CSS. Base de dados MySQL (`santana`).

## Stack
- **Backend:** Laravel 12, PHP 8.2+
- **Frontend:** Vue 3 + Inertia.js v2 + Tailwind CSS v3 + Vite
- **Autenticação:** Laravel Breeze (backoffice) + sessões POS próprias (`EnsurePosSession`)
- **Pagamentos:** Viva Smart Checkout (inscrições em eventos)
- **Push notifications:** Web Push (VAPID) para reservas de restaurante
- **Deploy:** `push.bat` (rsync para VPS) — **NUNCA usar `--delete`**

## Estrutura principal

### Site público (`/`)
- Home, sobre-nós, patrocínios, contacto, política de privacidade
- `/reserva-salao` — formulário público de pré-reserva do salão (`SalaoController`)
- `/inscricoes` — inscrições em eventos (`InscricaoController`) com pagamento online opcional (Viva)
- `/reserva/{token}` — página do cliente para notificações push da reserva
- `/cliente/{token}` — mesa do cliente (pedir ao bar/restaurante)
- `/ecra-patrocinios` — ecrã de patrocinadores para TV

### Backoffice (`/dashboard`, middleware `auth + verified`)
- Sócios e cotas (`SocioController`, `CotaController`)
- Eventos e inscrições (`EventoController`, `InscricaoController`)
- Alugueres do salão (`AluguerController`, `AluguerOpcaoController`)
- Mesas e sala (`MesaController`, `ZonaMapaController`)
- Patrocinadores (`SponsorAdminController`)
- Contas bancárias, caixa diária, contas da festa (`ContasBancariaController`, `CaixaDiariaController`, `FestaContaController`)
- Faturas de compra (`FaturaCompraController`)

### Sistema POS (terminais físicos, sem login normal)
- Login próprio: `POST /pos/login` → sessão com `tipo` + `numero_terminal`
- Middleware `EnsurePosSession` valida o tipo e redireciona para a área certa:
  - `restaurante` → `/pos-rest/*` — gestão de mesas e pedidos
  - `reservas` → `/pos-reservas/*` — reservas do restaurante
  - `bar` → `/pos-bar/*` — pedidos do bar
  - `cotas` → `/pos-cotas/*` — pagamento de cotas
- `PosPainelController` — painel geral do POS

### Modelos chave
- `Evento`, `EventoInscricao` — eventos públicos com inscrições
- `Aluguer`, `AluguerOpcao` — aluguer do salão (pivot: `aluguer_aluguer_opcao`, cascade delete)
- `Reserva` — reservas de mesa no restaurante (campo `nome_reserva` em `mesas`)
- `Mesa` — mesas do restaurante (campos: `numero`, `estado`, `nome_reserva`, `mesa_principal_id`)
- `Socio`, `Cota` — gestão de sócios e quotas
- `Pedido`, `PedidoItem` — pedidos do restaurante/bar

## Regras de segurança — OBRIGATÓRIAS
- **NÃO usar `--delete`** no rsync/SCP de deploy
- **NÃO eliminar ficheiros** do projeto (registos de base de dados podem ser apagados)
- **NÃO alterar conteúdo** do site público associacaosantana — apenas design e animações
- **"cada patrocinador dá o que quiser"** — sem tiers de patrocínio
- `mail.contact_to` = email da associação; `mail.reply_to` = reply-to para clientes

## Padrões de código
- Controllers devolvem `Inertia::render('Pagina/Componente', [...])` ou `RedirectResponse`
- Emails: `Mail::raw()` para associação, `Mailable` class para cliente (ver `InscricaoConfirmadaMail`)
- Todos os emails em `try/catch` com `Log::warning()` para não bloquear o fluxo
- Formulários Vue usam `useForm` do Inertia; submissão com `form.post/patch/delete`
- POS pages usam fundo escuro (`bg-gray-900 text-white`), backoffice usa fundo claro

## Deploy
```
push.bat          # deploy para VPS (rsync SEM --delete)
php artisan migrate   # correr após adicionar migrations
npm run build     # build do frontend
```
