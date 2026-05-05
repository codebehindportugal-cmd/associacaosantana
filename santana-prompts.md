# Associação de Santana — Prompts para COMPLETAR o projeto

> O projeto já tem: Mesas, Pedidos, Fechar Conta, Talão, Ecrãs de Secção, Sócios, Cotas, Users, Produtos, Reservas.
> Cola **um PROMPT de cada vez**. Ordem: A → B → C → D

---

## PROMPT A — Módulo Bar (Migration + Model + Controller)

```
No projeto Laravel existente (Associação de Santana), adiciona o módulo de Bar.

O bar tem DOIS modos de funcionamento:
- Modo 1 "Pré-pagamento": cliente paga primeiro, imprime talão com senha/número, depois prepara
- Modo 2 "Conta": regista itens e paga no final (igual ao restaurante)

1. Cria migration: add_tipo_to_pedidos_table
   Adiciona à tabela pedidos:
   - tipo (enum: restaurante, bar_conta, bar_prepago, default: restaurante)
   - numero_senha (integer, nullable) — número sequencial do dia para pré-pagamento
   - pago_antecipado (boolean, default: false)

2. Cria migration: create_configuracoes_table
   - chave (string, unique), valor (string), descricao (string, nullable)
   Insere linha: chave=ultima_senha_bar, valor=0

3. Atualiza Model Pedido:
   - Adiciona 'tipo', 'numero_senha', 'pago_antecipado' ao fillable
   - Scope restaurante(): where tipo = restaurante
   - Scope barConta(): where tipo = bar_conta
   - Scope barPrepago(): where tipo = bar_prepago

4. Cria BarController com:
   - index(): lista pedidos do bar do dia (bar_conta + bar_prepago), com filtro tipo e estado
     Retorna Inertia::render('Bar/Index', [...])
   - novaContaBar(): formulário nova conta (Modo 2)
     Retorna Inertia::render('Bar/NovaContaBar', ['produtos' => Produto::with('categoria')->disponiveis()->get()])
   - novoPrepago(): formulário pré-pagamento (Modo 1)
     Retorna Inertia::render('Bar/NovoPrepago', ['produtos' => Produto::with('categoria')->disponiveis()->get()])
   - storePrepago(Request $request): valida itens, calcula total, regista pagamento
     Incrementa ultima_senha_bar em Configuracoes
     Cria Pedido com tipo=bar_prepago, estado=pronto, pago_antecipado=true, numero_senha=N
     Cria PedidoItems
     Redireciona para Bar/TalaoSenha com os dados (imprime automaticamente)
   - storeContaBar(Request $request): cria Pedido tipo=bar_conta, estado=pendente
     Redireciona para bar.show
   - show(Pedido $pedido): detalhe da conta bar, adicionar itens, fechar conta
     Retorna Inertia::render('Bar/Show', ['pedido' => $pedido->load('items.produto.categoria'), 'produtos' => ...])
   - fecharContaBar(Request $request, Pedido $pedido): mesmo comportamento do fecharConta do PedidoController
     Redireciona para Bar/TalaoSenha após fechar

5. Adiciona rotas em routes/web.php (dentro do grupo auth):
   Route::get('bar', [BarController::class, 'index'])->name('bar.index');
   Route::get('bar/nova-conta', [BarController::class, 'novaContaBar'])->name('bar.nova-conta');
   Route::post('bar/nova-conta', [BarController::class, 'storeContaBar'])->name('bar.store-conta');
   Route::get('bar/prepago', [BarController::class, 'novoPrepago'])->name('bar.prepago');
   Route::post('bar/prepago', [BarController::class, 'storePrepago'])->name('bar.store-prepago');
   Route::get('bar/{pedido}', [BarController::class, 'show'])->name('bar.show');
   Route::patch('bar/{pedido}/fechar', [BarController::class, 'fecharContaBar'])->name('bar.fechar');

   Rota pública para ecrã do bar (sem auth):
   Route::get('/secao/bar', [SecaoController::class, 'bar'])->name('secao.bar');

6. Adiciona método bar() ao SecaoController existente:
   Mostra pedidos de bar_prepago com estado=pronto agrupados por numero_senha
   (ecrã de chamada de senhas no bar)
   Retorna Inertia::render('Secao/Ecra', ['titulo' => 'BAR', 'itemsPorMesa' => ...])

Executa: php artisan migrate
```

---

## PROMPT B — Módulo Bar (Páginas Vue)

```
No projeto Laravel (Associação de Santana) com Inertia + Vue + Tailwind, cria as páginas do módulo Bar.
IMPORTANTE: Todas as páginas do bar devem ser otimizadas para tablet e telemóvel (botões grandes, fonte legível, touch-friendly). NÃO usar sidebar — usar header simples com logo e botão voltar.

1. resources/js/Pages/Bar/Index.vue
   - Header com "🍺 BAR" e botões grandes: "Nova Conta" (azul) e "Pré-Pago" (verde)
   - Duas colunas (tabs em mobile): "Contas Abertas" | "Pré-Pagos Hoje"
   - Contas abertas: cards com número do pedido, hora, total parcial, botão "Ver Conta"
   - Pré-pagos: cards com número de senha, hora, total pago, estado (pronto/entregue)
   - Auto-refresh a cada 20 segundos

2. resources/js/Pages/Bar/NovoPrepago.vue
   - Modo pré-pagamento (paga antes de preparar)
   - Layout 2 colunas (stacked em mobile):
     * Esquerda/topo: grade de produtos por categoria (botões grandes com nome e preço)
     * Direita/baixo: carrinho com itens selecionados, quantidade editável, total
   - Botão "+" e "-" para quantidade em cada item do carrinho
   - Total grande e bem visível
   - Campos: valor recebido, troco calculado automaticamente
   - Botão grande verde "COBRAR E IMPRIMIR SENHA" — submete e vai para TalaoSenha
   - Nota visível: "O cliente paga ANTES de levantar"

3. resources/js/Pages/Bar/NovaContaBar.vue
   - Modo conta (paga no final)
   - Mesmo layout de seleção de produtos do NovoPrepago mas sem campos de pagamento
   - Campo opcional: nome/identificação da conta (ex: "Mesa 3", "João")
   - Botão grande azul "ABRIR CONTA"

4. resources/js/Pages/Bar/Show.vue
   - Detalhe de uma conta de bar aberta
   - Lista de itens já adicionados com subtotal
   - Grade de produtos para adicionar mais (botões grandes)
   - Total atual bem visível
   - Formulário de fechar conta: valor recebido, troco automático
   - Botão grande "FECHAR E IMPRIMIR"
   - Botão "Adicionar Items" que mostra/oculta a grade de produtos

5. resources/js/Pages/Bar/TalaoSenha.vue
   - Talão de impressão para pré-pagamento
   - Fundo branco, formatado para impressora de talões (max-width: 300px, centrado)
   - Cabeçalho: "Associação de Santana — BAR"
   - NÚMERO DA SENHA em destaque grande (ex: "#42")
   - Lista de itens pedidos com quantidade e preço
   - Total, valor recebido, troco
   - Data e hora
   - "Obrigado!" no rodapé
   - Botão "Imprimir" e botão "Novo Pedido" (volta para bar.prepago)
   - Auto-impressão no onMounted() com setTimeout 300ms

6. Adiciona link "BAR" no AppLayout.vue (sidebar/menu) com ícone 🍺
   Visível para roles: admin, gerente, staff_bar
```

---

## PROMPT C — Relatórios e Estatísticas (Gerente)

```
No projeto Laravel (Associação de Santana), cria o módulo de Relatórios para gerente.
IMPORTANTE: Estas páginas são usadas em COMPUTADOR (ecrã grande). Usar layout completo com sidebar.
Proteger com middleware: permission:relatorios.ver

1. Cria RelatorioController com métodos:

   index(): dashboard de relatórios
   - Retorna Inertia::render('Relatorios/Index', com dados do dia atual):
     * total_vendas_hoje (soma total de pedidos entregues hoje)
     * total_pedidos_hoje (count)
     * media_por_pedido
     * vendas_restaurante_hoje / vendas_bar_hoje / vendas_prepago_hoje
     * top_produtos_hoje (top 5 mais vendidos hoje por quantidade)
     * doacoes_hoje (soma campo doacao)

   porPeriodo(Request $request): relatório por intervalo de datas
   - Parâmetros: data_inicio, data_fim, tipo (restaurante/bar/todos)
   - Retorna Inertia::render('Relatorios/PorPeriodo', com):
     * vendas_por_dia (array com data e total — para gráfico de linha)
     * total_periodo, total_pedidos, media_diaria
     * vendas_por_tipo (restaurante vs bar_conta vs bar_prepago)
     * top_produtos (top 10 mais vendidos no período)
     * top_categorias (vendas por categoria/secção)
     * total_doacoes

   exportarPDF(Request $request): gera PDF do relatório do período
   - Usa barryvdh/laravel-dompdf
   - Inclui tabela de vendas por dia, resumo por tipo, top produtos

2. Adiciona rotas (dentro do grupo auth):
   Route::get('relatorios', [RelatorioController::class, 'index'])->name('relatorios.index');
   Route::get('relatorios/periodo', [RelatorioController::class, 'porPeriodo'])->name('relatorios.periodo');
   Route::get('relatorios/exportar', [RelatorioController::class, 'exportarPDF'])->name('relatorios.pdf');

3. Cria resources/js/Pages/Relatorios/Index.vue
   - Layout desktop com sidebar (AppLayout)
   - Secção "HOJE — [data atual]"
   - Cards de resumo: Total Vendido, Nº Pedidos, Média/Pedido, Doações
   - Sub-cards: Restaurante | Bar Conta | Bar Pré-pago (valores do dia)
   - Tabela "Top 5 Produtos Hoje" com quantidade e valor
   - Link para ver relatório por período
   - Botão "Relatório Completo" que vai para relatorios.periodo com hoje como data

4. Cria resources/js/Pages/Relatorios/PorPeriodo.vue
   - Filtros no topo: data_inicio, data_fim, tipo — botão "Filtrar"
   - Cards de resumo do período: Total, Nº Pedidos, Média Diária, Total Doações
   - Gráfico de barras simples (HTML/CSS puro com Tailwind, sem biblioteca):
     * Barras verticais representando vendas por dia
     * Cada barra tem altura proporcional ao máximo do período
     * Label com data e valor abaixo/acima de cada barra
   - Tabela "Vendas por Tipo": Restaurante, Bar Conta, Bar Pré-pago (valor e %)
   - Tabela "Top Produtos": nome, categoria, quantidade, total €
   - Tabela "Por Categoria/Secção": secção, total €, % do total
   - Botão "Exportar PDF"

5. Cria resources/views/pdf/relatorio-periodo.blade.php:
   - Template HTML para PDF
   - Cabeçalho: "Associação de Santana — Relatório de Vendas"
   - Período e data de geração
   - Tabela resumo, tabela por dia, top produtos

6. Adiciona link "Relatórios" no AppLayout.vue (sidebar)
   - Visível apenas para roles: admin, gerente
   - Ícone 📊
```

---

## PROMPT D — Responsive Mobile/Tablet + Ajustes Finais

```
No projeto Laravel (Associação de Santana) com Vue + Inertia + Tailwind, faz os seguintes ajustes de responsividade e UX:

REGRA GERAL:
- Gerente (relatórios, dashboard, gestão) → ecrã de computador, layout com sidebar
- Contas/tesoureiro (sócios, cotas) → ecrã de computador, layout com sidebar
- Bar, Ecrãs de Secção, Pedidos do dia → tablet ou telemóvel, sem sidebar, botões grandes

1. Atualiza AppLayout.vue:
   - Sidebar só visível em md: (desktop)
   - Em mobile: sidebar vira bottom navigation bar com ícones e labels curtas
   - Bottom nav com: Dashboard, Mesas, Pedidos, Bar (se tiver permissão), Menu (abre drawer com resto)
   - Drawer lateral (slide-in da esquerda) em mobile com todos os links
   - Botão hamburger no header para abrir drawer
   - Texto do sidebar mais pequeno em tablet, normal em desktop

2. Atualiza Pages/Mesas/Index.vue:
   - Cards de mesa maiores em mobile (min 80px x 80px), touch-friendly
   - Modal de ação da mesa ocupa full-screen em mobile
   - Botões de ação grandes (py-4, text-base)

3. Atualiza Pages/Pedidos/Show.vue:
   - Grade de produtos: 2 colunas em mobile, 3 em tablet, 4 em desktop
   - Botões de produto grandes com padding generoso
   - Secção de fechar conta fixada no bottom em mobile (sticky bottom-0)
   - Lista de itens do pedido com botão remover grande

4. Atualiza Pages/Secao/Ecra.vue:
   - Já é full-screen — garantir que o auto-refresh usa setInterval (não setTimeout) para repetir
   - Cards de mesa com fonte mínima 1.2rem
   - Botão PRONTO com min-height 60px para touch
   - Mostrar hora do último refresh no canto inferior direito

5. Atualiza Pages/Dashboard/Index.vue:
   - Cards de resumo em grid 2x2 em mobile, 4x1 em desktop
   - Adiciona card "Bar Hoje" com total de vendas do bar do dia

6. Adiciona ao AppLayout.vue o link para ecrãs de secção abrindo em nova aba:
   - Secção Bebidas (nova aba)
   - Secção Cozinha (nova aba)
   - Secção Bar — senhas (nova aba)
   Estes links visíveis para admin e gerente no menu desktop

7. Cria componente resources/js/Components/BotaoGrande.vue:
   - Botão reutilizável otimizado para touch
   - Props: label, cor (verde/azul/vermelho/cinza), icone (emoji), disabled, loading
   - Tamanho mínimo: 48px height, padding generoso
   - Usar em Bar e Pedidos

8. Executa no terminal:
   npm run build
```

---

> ✅ Após os 4 prompts o projeto está completo!
>
> **Resumo do que fica feito:**
> - Bar com pré-pagamento (talão + senha) e conta corrente
> - Relatórios com gráfico de vendas por dia e exportação PDF
> - Interface responsiva: PC para gerente/contas, tablet/mobile para bar e secções


# Associação de Santana — PROMPT: Sistema de Prioridade de Pedidos

> Cola este prompt no Claude do VS Code.

---

## PROMPT — Prioridade de Pedidos nas Secções

```
No projeto Laravel (Associação de Santana), adiciona um sistema de prioridade
para pedidos de mesas que estão a terminar e pedem algo adicional.

CONTEXTO: Quando uma mesa já tem a conta quase fechada e pede mais um produto
(ex: mais um frango), esse pedido deve aparecer em DESTAQUE (topo + cor vermelha)
nos ecrãs de secção para ser preparado com urgência.

---

1. Migration: add_prioridade_to_pedido_items_table
   Adiciona à tabela pedido_items:
   - prioridade (boolean, default: false)

2. Atualiza Model PedidoItem:
   - Adiciona 'prioridade' ao fillable
   - Scope urgentes(): where prioridade = true, where estado = pendente

3. Atualiza PedidoItemController@store:
   - Recebe campo opcional 'prioridade' (boolean) no request
   - Valida: 'prioridade' => ['boolean']
   - Guarda o valor no PedidoItem

4. Atualiza PedidoItemController@update:
   - Permite também atualizar campo 'prioridade' (toggle urgência)

5. Atualiza SecaoController (método privado ecra()):
   - Ordena os grupos de itens: prioridade=true aparecem PRIMEIRO
   - Dentro de cada grupo de mesa, itens com prioridade=true no topo
   - Passa flag 'tem_urgentes' para a view (true se houver algum item urgente)

6. Atualiza Pages/Pedidos/Show.vue:
   - Em cada produto na lista de itens do pedido, adiciona botão/toggle "⚡ Urgente"
   - Ao clicar: faz PATCH para pedido-items/{id} com { prioridade: true/false }
   - Itens marcados como urgentes mostram badge "⚡ URGENTE" em vermelho
   - No formulário de adicionar produto, adiciona checkbox "⚡ Marcar como urgente"
     (visível e fácil de ativar num tablet)

7. Atualiza Pages/Secao/Ecra.vue:
   - Se 'tem_urgentes' for true: mostra banner pulsante no topo
     "⚡ ATENÇÃO — HÁ PEDIDOS URGENTES" (fundo vermelho, texto branco, animação pulse)
   - Cards de mesas com itens urgentes:
     * Borda vermelha grossa (border-red-500 border-4)
     * Badge "⚡ URGENTE" no canto superior direito do card
     * Fundo do card ligeiramente vermelho (bg-red-950)
   - Itens urgentes dentro do card têm fundo vermelho escuro e texto em negrito
   - Cards urgentes aparecem SEMPRE no topo da página antes dos normais
   - O botão "PRONTO" nos itens urgentes é maior e mais destacado (bg-red-500)

8. Atualiza AppLayout.vue (sidebar/menu):
   - Adiciona badge com contagem de itens urgentes pendentes no link dos Pedidos
   - Ex: "Pedidos (3⚡)" se houver 3 itens urgentes
   - Badge vermelho, atualiza via polling a cada 30s

9. Executa: php artisan migrate
   Depois: npm run build
```

---

> ✅ Resultado: itens urgentes aparecem no TOPO dos ecrãs de secção com destaque
> vermelho e animação, garantindo que a cozinha/bar os prepara primeiro.