# Associação de Santana — POS Restaurante + POS Cotas
> Cola **um PROMPT de cada vez**. Ordem: REST-1 → REST-2 → COTAS-1 → COTAS-2

---

## PROMPT REST-1 — POS Restaurante (Backend)

```
No projeto Laravel (Associação de Santana), cria um sistema POS isolado
para o restaurante, igual ao do bar mas adaptado para mesas e restauração.

Os dispositivos do restaurante (tablet dos empregados) só acedem ao POS
do restaurante — nada mais. Usa o mesmo sistema de PosSession já existente.

1. Atualiza migration/seeder PosSesionSeeder — adiciona terminais de restaurante:
   - Restaurante 1: PIN 1111, localizacao="Restaurante — Empregado 1", tipo="restaurante"
   - Restaurante 2: PIN 2222, localizacao="Restaurante — Empregado 2", tipo="restaurante"
   Se a tabela pos_sessions não tiver coluna 'tipo', cria migration:
   add_tipo_to_pos_sessions: tipo (enum: bar, restaurante, default: bar)
   Executa: php artisan migrate

2. Adiciona rotas POS Restaurante em routes/web.php (FORA do grupo auth):
   Route::middleware('pos.auth')->prefix('pos-rest')->name('pos.rest.')->group(function () {
       Route::get('/', [PosRestController::class, 'index'])->name('index');
       Route::get('/mesas', [PosRestController::class, 'mesas'])->name('mesas');
       Route::get('/mesa/{mesa}', [PosRestController::class, 'mesa'])->name('mesa');
       Route::post('/mesa/{mesa}/pedido', [PosRestController::class, 'novoPedido'])->name('pedido.novo');
       Route::post('/pedido/{pedido}/item', [PosRestController::class, 'adicionarItem'])->name('pedido.item');
       Route::delete('/pedido/{pedido}/item/{item}', [PosRestController::class, 'removerItem'])->name('pedido.item.remover');
       Route::patch('/pedido/{pedido}/item/{item}/urgente', [PosRestController::class, 'toggleUrgente'])->name('pedido.item.urgente');
       Route::patch('/pedido/{pedido}/fechar', [PosRestController::class, 'fecharConta'])->name('pedido.fechar');
       Route::get('/pedido/{pedido}/talao', [PosRestController::class, 'talao'])->name('pedido.talao');
       Route::patch('/pedido/{pedido}/estado', [PosRestController::class, 'atualizarEstado'])->name('pedido.estado');
       Route::get('/historico', [PosRestController::class, 'historico'])->name('historico');
   });
   // Login partilhado com o bar — usa o mesmo /pos/login

3. Cria app/Http/Controllers/PosRestController.php:

   index():
   - Retorna Inertia::render('PosRest/Index', [
       'posNome' => session('pos_nome'),
       'vendasHoje' => Pedido::whereDate('created_at', today())->where('tipo','restaurante')->sum('total'),
       'mesasLivres' => Mesa::ativas()->where('estado','livre')->count(),
       'mesasOcupadas' => Mesa::ativas()->where('estado','ocupada')->count(),
     ])

   mesas():
   - $mesas = Mesa::ativas()->with(['pedidos' => fn($q) => $q->whereIn('estado',['pendente','preparacao','pronto'])->with('items')])->get()
   - Retorna Inertia::render('PosRest/Mesas', ['mesas' => $mesas])

   mesa(Mesa $mesa):
   - $pedido = $mesa->pedidos()->whereIn('estado',['pendente','preparacao','pronto'])->with('items.produto.categoria')->first()
   - $produtos = Produto::with('categoria')->disponiveis()->get()->groupBy(fn($p) => $p->categoria->nome)
   - Retorna Inertia::render('PosRest/Mesa', compact('mesa','pedido','produtos'))

   novoPedido(Request $request, Mesa $mesa):
   - Cria Pedido: tipo=restaurante, estado=pendente, mesa_id, user_id=null, pos_id=session('pos_id')
   - $mesa->update(['estado' => 'ocupada'])
   - Redireciona para pos.rest.mesa

   adicionarItem(Request $request, Pedido $pedido):
   - Valida: produto_id, quantidade, prioridade(boolean,nullable)
   - Determina secao a partir da categoria do produto
   - Cria PedidoItem com secao e prioridade
   - back()

   removerItem(Pedido $pedido, PedidoItem $item):
   - $item->delete()
   - back()

   toggleUrgente(Pedido $pedido, PedidoItem $item):
   - $item->update(['prioridade' => !$item->prioridade])
   - back()

   fecharConta(Request $request, Pedido $pedido):
   - Valida: metodo_pagamento, valor_recebido, troco
   - Calcula total_calculado
   - Atualiza pedido: estado=entregue, total, valor_recebido, troco, doacao, metodo_pagamento
   - $pedido->mesa->update(['estado' => 'livre'])
   - Redireciona para pos.rest.pedido.talao

   talao(Pedido $pedido):
   - Retorna Inertia::render('PosRest/Talao', ['pedido' => $pedido->load('mesa','items.produto')])

   atualizarEstado(Request $request, Pedido $pedido):
   - Valida estado
   - $pedido->update(['estado' => $request->estado])
   - back()

   historico():
   - $pedidos = Pedido::where('pos_id', session('pos_id'))
       ->whereDate('created_at', today())
       ->with('mesa','items.produto')->latest()->get()
   - Retorna Inertia::render('PosRest/Historico', compact('pedidos'))

4. Atualiza Middleware EnsurePosSession:
   - Verifica também o tipo do terminal:
     * Rotas /pos-rest/* só permitem pos_sessions com tipo=restaurante
     * Rotas /pos/* só permitem pos_sessions com tipo=bar
   - Se tipo errado: abort(403, 'Terminal não autorizado para esta área')

5. Executa: php artisan db:seed --class=PosSessionSeeder
```

---

## PROMPT REST-2 — POS Restaurante (Interface Vue)

```
No projeto Vue + Inertia + Tailwind (Associação de Santana), cria as páginas
do POS do Restaurante. Usa o mesmo design do POS do Bar (fundo escuro, botões
grandes, touch-friendly) mas adaptado para gestão de mesas.

DESIGN: Igual ao POS Bar — bg-gray-900, texto branco, botões grandes.
Usa o PosLayout.vue já existente.

1. resources/js/Pages/PosRest/Index.vue — Home do terminal de restaurante
   - Fundo bg-gray-900
   - Topo: nome do terminal + hora atual (atualiza cada segundo) + botão LOGOUT
   - Cards de resumo:
     * Mesas Livres (verde)
     * Mesas Ocupadas (vermelho)
     * Vendas Hoje (azul, valor em €)
   - Botão grande central: [🍽️ VER MESAS] verde
   - Botão secundário: [📋 HISTÓRICO DO DIA] azul
   - Auto-refresh a cada 30 segundos

2. resources/js/Pages/PosRest/Mesas.vue — Mapa de mesas (estilo POS)
   - Fundo bg-gray-900
   - Título "MESAS" + botão voltar (canto superior esquerdo)
   - Grade de mesas em cards quadrados (touch-friendly, min 90x90px):
     * Verde = LIVRE → ao clicar vai para pos.rest.mesa (pode abrir pedido)
     * Vermelho = OCUPADA → ao clicar vai para pos.rest.mesa (ver/editar pedido)
     * Amarelo = RESERVADA
   - Cada card mostra:
     * Número da mesa (grande, centro)
     * Capacidade (pequeno, baixo)
     * Se ocupada: total parcial do pedido ativo
     * Se ocupada: há quanto tempo está ocupada (ex: "32min")
   - Separação visual por localização (Sala / Exterior / Bar)
   - Auto-refresh a cada 20 segundos

3. resources/js/Pages/PosRest/Mesa.vue — Ecrã da mesa (POS principal)
   Layout 2 colunas igual ao Pos/Novo.vue:

   LADO ESQUERDO (60%): Produtos
   - Tabs por categoria no topo (scroll horizontal)
   - Grade de produtos 3 colunas — botões grandes com NOME + PREÇO
   - Cada produto tem cor por secção:
     * Bebidas = azul
     * Acompanhamentos/Cozinha = laranja
     * Sobremesas = roxo
   - Ao clicar no produto: adiciona ao pedido (feedback visual)
   - Se não houver pedido aberto: aparece overlay "Abrir pedido nesta mesa?"
     com botão [ABRIR PEDIDO] verde

   LADO DIREITO (40%): Pedido atual
   - Header: "MESA X" em grande
   - Se sem pedido: mensagem "Mesa livre — clique num produto para iniciar"
   - Se com pedido: lista de itens:
     * Nome do produto
     * Botões [-] e [+] para quantidade
     * Preço unitário × quantidade = subtotal (font-mono)
     * Botão [⚡] toggle urgente — se ativo fica vermelho com animação pulse
     * Botão [✕] remover item
   - Itens urgentes marcados com borda vermelha e ícone ⚡
   - TOTAL em grande (text-3xl font-bold text-green-400 font-mono)
   - Estado atual do pedido com badge colorido
   - Botões de ação no fundo (só aparecem se houver pedido):
     * [FECHAR CONTA] verde grande — abre overlay de pagamento
     * [CANCELAR PEDIDO] vermelho pequeno
   - Overlay de pagamento (modal full-screen):
     * TOTAL em destaque grande
     * Botões método: 💵 Dinheiro | 📱 MBWay | 💳 Multibanco
     * Campo "Recebido" com teclado numérico integrado (botões 1-9, 0, ←, . )
     * Troco calculado automaticamente em verde
     * Botão [✅ CONFIRMAR PAGAMENTO] verde grande
     * Botão [✕ CANCELAR] cinza

4. resources/js/Pages/PosRest/Talao.vue — Talão do restaurante
   Igual ao Pos/Talao.vue mas com:
   - Header: "Associação de Santana — RESTAURANTE"
   - Linha "Mesa: X" em vez de senha
   - Lista de itens com secção (Cozinha / Bebidas / Sobremesas)
   - Total, Recebido, Troco, Método de pagamento
   - Auto-print no onMounted
   - Botões (print:hidden):
     * [🖨️ IMPRIMIR]
     * [VER MESAS] → pos.rest.mesas
     * [ECRÃ PRINCIPAL] → pos.rest.index

5. resources/js/Pages/PosRest/Historico.vue
   - Lista de pedidos do dia deste terminal
   - Cada item: hora, número mesa, itens (resumo), total, método pagamento
   - Total do dia no fundo
   - Botão [VOLTAR]

6. Atualiza resources/js/Pages/Pos/Login.vue:
   - Terminais de bar (tipo=bar) mostram ícone 🍺
   - Terminais de restaurante (tipo=restaurante) mostram ícone 🍽️
   - Ao entrar num terminal de restaurante: redireciona para /pos-rest
   - Ao entrar num terminal de bar: redireciona para /pos
   - Adiciona label com tipo visível nos cards de terminal

7. Executa: npm run build
```

---

## PROMPT COTAS-1 — POS Cotas (Backend)

```
No projeto Laravel (Associação de Santana), cria um sistema POS isolado
para a gestão de cotas dos sócios. Acesso por PIN como o bar e restaurante.

Os dispositivos de cotas (tablet/computador do tesoureiro) só acedem
ao POS de cotas — nada mais.

1. Atualiza PosSesionSeeder — adiciona terminal de cotas:
   - Cotas: PIN 9999, localizacao="Tesouraria", tipo="cotas"
   Se necessário adiciona 'cotas' ao enum tipo da tabela pos_sessions.
   Migration: update_tipo_enum_on_pos_sessions (adiciona 'cotas' ao enum)
   Executa: php artisan migrate

2. Adiciona rotas POS Cotas em routes/web.php (FORA do grupo auth):
   Route::middleware('pos.auth')->prefix('pos-cotas')->name('pos.cotas.')->group(function () {
       Route::get('/', [PosCotasController::class, 'index'])->name('index');
       Route::get('/socio/pesquisa', [PosCotasController::class, 'pesquisa'])->name('socio.pesquisa');
       Route::get('/socio/{socio}', [PosCotasController::class, 'socio'])->name('socio');
       Route::post('/socio/{socio}/pagar', [PosCotasController::class, 'registarPagamento'])->name('pagar');
       Route::post('/socio/novo', [PosCotasController::class, 'novoSocio'])->name('socio.novo');
       Route::get('/recibo/{cota}', [PosCotasController::class, 'recibo'])->name('recibo');
       Route::get('/em-atraso', [PosCotasController::class, 'emAtraso'])->name('em-atraso');
       Route::get('/resumo-dia', [PosCotasController::class, 'resumoDia'])->name('resumo-dia');
   });

3. Cria app/Http/Controllers/PosCotasController.php:

   index():
   - $sociosEmAtraso = Socio::emAtraso()->count()
   - $cobradosHoje = Cota::whereDate('data_pagamento', today())->sum('valor')
   - $cotasHoje = Cota::whereDate('data_pagamento', today())->count()
   - Retorna Inertia::render('PosCotas/Index', compact('sociosEmAtraso','cobradosHoje','cotasHoje'))

   pesquisa(Request $request):
   - Pesquisa por nome, numero_socio ou telefone
   - $socios = Socio::where('nome','like',"%{$request->q}%")
       ->orWhere('numero_socio','like',"%{$request->q}%")
       ->orWhere('telefone','like',"%{$request->q}%")
       ->with(['cotas' => fn($q) => $q->latest()->limit(12)])
       ->limit(10)->get()
   - Cada sócio tem accessor cota_em_dia e meses_em_atraso
   - Retorna Inertia::render('PosCotas/Pesquisa', ['socios' => $socios, 'query' => $request->q])

   socio(Socio $socio):
   - $cotasRecentes = $socio->cotas()->latest()->limit(24)->get()
   - $mesosEmAtraso = calculado (quantos meses sem cota paga)
   - $valorEmDivida = $mesesEmAtraso * 5 (valor mensal)
   - Retorna Inertia::render('PosCotas/Socio', compact('socio','cotasRecentes','mesesEmAtraso','valorEmDivida'))

   registarPagamento(Request $request, Socio $socio):
   - Valida:
     tipo (enum: mensal, anual)
     meses (array, nullable — quais meses pagar se tipo=mensal)
     ano (integer)
     valor (numeric)
     metodo_pagamento (enum: dinheiro, mbway, transferencia)
     valor_recebido (numeric, nullable)
   - Cria Cota(s) para cada mês selecionado (ou uma cota anual)
   - Estado = pago, data_pagamento = today()
   - Redireciona para pos.cotas.recibo com a última cota criada

   novoSocio(Request $request):
   - Valida: numero_socio(unique), nome, email(nullable), telefone(nullable)
   - Cria Socio com data_inscricao = today()
   - Redireciona para pos.cotas.socio

   recibo(Cota $cota):
   - Retorna Inertia::render('PosCotas/Recibo', ['cota' => $cota->load('socio')])

   emAtraso():
   - $socios = Socio::emAtraso()->ativos()
       ->with(['cotas' => fn($q) => $q->emAtraso()])
       ->orderBy('nome')->get()
   - Retorna Inertia::render('PosCotas/EmAtraso', ['socios' => $socios])

   resumoDia():
   - Resumo de cobranças do dia
   - $cotas = Cota::whereDate('data_pagamento', today())->with('socio')->latest()->get()
   - Total, por método, lista detalhada
   - Retorna Inertia::render('PosCotas/ResumoDia', ['cotas' => $cotas])

4. Atualiza Middleware EnsurePosSession:
   - Rotas /pos-cotas/* só permitem tipo=cotas
   - Acesso negado com abort(403) para outros tipos

5. Atualiza PosSession Login: ao entrar com tipo=cotas redireciona para /pos-cotas

6. Executa: php artisan migrate && php artisan db:seed --class=PosSessionSeeder
```

---

## PROMPT COTAS-2 — POS Cotas (Interface Vue)

```
No projeto Vue + Inertia + Tailwind (Associação de Santana), cria as páginas
do POS de Cotas. Mesmo design escuro do POS Bar/Restaurante mas adaptado para
gestão de cotas. Usa PosLayout.vue existente.

NOTA: Este ecrã pode ser usado em computador E tablet.
Design responsivo mas mantendo a estética POS.

1. resources/js/Pages/PosCotas/Index.vue — Home da tesouraria POS
   - Fundo bg-gray-900
   - Topo: "💳 TESOURARIA — Associação de Santana" + hora + botão LOGOUT
   - Cards de resumo do dia:
     * 💰 Cobrado Hoje (valor em €, verde)
     * 📋 Cotas Registadas Hoje (número, azul)
     * ⚠️ Sócios em Atraso (número, vermelho — com link)
   - Botão grande central: [🔍 PESQUISAR SÓCIO] azul
   - Botões secundários em grid 2x2:
     * [📋 LISTA EM ATRASO] vermelho
     * [👤 NOVO SÓCIO] verde
     * [📊 RESUMO DO DIA] cinza
     * [🖨️ IMPRIMIR RESUMO] cinza
   - Auto-refresh a cada 60 segundos

2. resources/js/Pages/PosCotas/Pesquisa.vue — Pesquisa de sócio
   - Fundo bg-gray-900
   - Barra de pesquisa grande no topo (touch-friendly, auto-focus)
   - Teclado virtual (A-Z + 0-9 + espaço + apagar) — botões grandes
   - Resultados em cards abaixo:
     * Nome do sócio (grande)
     * Número de sócio
     * Estado da cota: badge VERDE "✅ EM DIA" ou VERMELHO "⚠️ X meses em atraso"
     * Ao clicar no card: vai para PosCotas/Socio
   - Se sem resultados: "Nenhum sócio encontrado" + botão [+ NOVO SÓCIO]
   - Pesquisa em tempo real (debounce 300ms) conforme digita no teclado virtual

3. resources/js/Pages/PosCotas/Socio.vue — Perfil do sócio (ecrã de cobrança)
   Layout 2 colunas (stacked em mobile):

   LADO ESQUERDO: Informação do sócio
   - Card com fundo bg-gray-800:
     * Nome em grande
     * Número de sócio
     * Telefone, email
   - Badge de estado grande:
     * VERDE "✅ COTA EM DIA" se está em dia
     * VERMELHO "⚠️ X MESES EM ATRASO — X€ em dívida" se em atraso
   - Histórico das últimas 12 cotas em tabela simples:
     * Mês/Ano | Tipo | Valor | Estado (badge) | Método

   LADO DIREITO: Registar pagamento
   - Título "REGISTAR PAGAMENTO"
   - Toggle grande: [MENSAL] | [ANUAL]
   - Se MENSAL:
     * Grid de checkboxes dos meses (Jan-Dez do ano atual)
     * Meses já pagos aparecem a verde e desativados
     * Meses em atraso aparecem a vermelho e pré-selecionados
     * Meses futuros aparecem a cinza
     * Total calculado automaticamente (nº meses × 5€)
   - Se ANUAL:
     * Selector de ano
     * Valor: 50€ fixo
   - Método de pagamento: 💵 Dinheiro | 📱 MBWay | 🏦 Transferência
   - Se Dinheiro: campo "Recebido" + troco calculado
   - Valor total em grande (font-mono, verde)
   - Botão [✅ REGISTAR PAGAMENTO] verde grande
   - Botão [← VOLTAR À PESQUISA] cinza

4. resources/js/Pages/PosCotas/Recibo.vue — Recibo de pagamento
   - Formato recibo de impressão (max-width 350px, centrado, fundo branco)
   - Cabeçalho: "Associação de Santana" + "RECIBO DE PAGAMENTO DE COTA"
   - Número do recibo (id da cota)
   - Nome do sócio + número de sócio
   - Período pago (ex: "Janeiro 2025 a Março 2025" ou "Anual 2025")
   - Valor pago
   - Método de pagamento
   - Data e hora
   - "Obrigado pela sua contribuição!"
   - Fora da área de impressão (print:hidden):
     * Botão [🖨️ IMPRIMIR RECIBO]
     * Botão [OUTRO SÓCIO] → pos.cotas.index
     * Botão [MESMO SÓCIO] → pos.cotas.socio (volta ao perfil)
   - Auto-print no onMounted com setTimeout 300ms

5. resources/js/Pages/PosCotas/EmAtraso.vue — Lista de sócios em atraso
   - Fundo bg-gray-900
   - Título "⚠️ SÓCIOS COM COTAS EM ATRASO"
   - Total em atraso no topo (valor total em dívida, vermelho)
   - Lista de sócios em cards:
     * Nome + Número de sócio
     * Meses em atraso (ex: "3 meses")
     * Valor em dívida (vermelho, font-mono)
     * Botão [COBRAR] verde → vai para PosCotas/Socio
   - Ordenação por meses em atraso (mais grave primeiro)
   - Botão [← VOLTAR] no topo

6. resources/js/Pages/PosCotas/ResumoDia.vue — Resumo do dia
   - Fundo bg-gray-900
   - Título "📊 RESUMO DO DIA — [data]"
   - Cards de totais:
     * Total cobrado, Nº cotas, Por método (Dinheiro/MBWay/Transferência)
   - Tabela de cobranças do dia:
     * Hora | Nome sócio | Período | Valor | Método
   - Botão [🖨️ IMPRIMIR RESUMO] — usa CSS print
   - Botão [← VOLTAR]

7. Cria resources/js/Pages/PosCotas/NovoSocio.vue — formulário novo sócio
   - Fundo bg-gray-800 (card centrado)
   - Campos grandes touch-friendly:
     * Número de sócio (gerado automaticamente mas editável)
     * Nome (obrigatório)
     * Telefone
     * Email
   - Botão [✅ CRIAR SÓCIO] verde
   - Botão [← CANCELAR] cinza

8. Atualiza resources/js/Pages/Pos/Login.vue:
   - Terminal de cotas (tipo=cotas) mostra ícone 💳
   - Ao entrar: redireciona para /pos-cotas

9. Executa: npm run build
```

---

> ✅ Resultado final — 3 sistemas POS completamente isolados:
>
> | Terminal       | URL         | PIN  | Acesso               |
> |----------------|-------------|------|----------------------|
> | Bar Interior   | /pos        | 1234 | Só POS Bar           |
> | Bar Exterior   | /pos        | 5678 | Só POS Bar           |
> | Restaurante 1  | /pos-rest   | 1111 | Só POS Restaurante   |
> | Restaurante 2  | /pos-rest   | 2222 | Só POS Restaurante   |
> | Tesouraria     | /pos-cotas  | 9999 | Só POS Cotas         |
>
> Todos entram pelo mesmo /pos/login — o sistema redireciona automaticamente
> para o POS correto conforme o tipo do terminal.
