Implementa um sistema de self-order para clientes via QR code neste projeto Laravel/Inertia/Vue.js.

## Contexto
Sistema POS de restaurante. O fluxo atual é 100% gerido pelo empregado via /pos-rest. 
Quero adicionar a opção de o cliente fazer self-order pelo telemóvel após o empregado abrir o pedido.
As duas opções devem coexistir: o empregado pode continuar a fazer tudo como antes, OU mostrar o QR ao cliente.

## O que fazer

### 1. Migração
Adicionar campo `cliente_token` à tabela `pedidos`:
- string de 64 chars, nullable, unique
- Gerar automaticamente via UUID quando o pedido é criado (no Model, boot method)

### 2. Rotas públicas (sem middleware pos.auth)
Em routes/web.php adicionar:
- GET  /cliente/{token}        → ClientePedidoController@show    (name: cliente.mesa)
- POST /cliente/{token}/item   → ClientePedidoController@addItem  (name: cliente.item)
- GET  /cliente/{token}/confirmacao → ClientePedidoController@confirmacao (name: cliente.confirmacao)

### 3. ClientePedidoController
Criar app/Http/Controllers/ClientePedidoController.php:
- show(): buscar pedido pelo cliente_token, carregar produtos disponíveis agrupados por categoria, retornar view Inertia 'Cliente/Mesa'
- addItem(): validar token, verificar que pedido está em estado 'pendente' ou 'preparacao', adicionar item usando a mesma lógica do PosRestController::guardarItemPedido(), recalcular total, disparar PrintJobService para impressão na cozinha, retornar redirect back()
- confirmacao(): mostrar página de confirmação com os itens adicionados pelo cliente nessa sessão

Regras de segurança no controller:
- Não expor preços nem totais ao cliente
- Não permitir adicionar itens se pedido estiver 'pronto', 'entregue' ou 'cancelado'
- Limitar quantidade máxima por item a 10
- Usar session para tracking dos itens adicionados pelo cliente nesta sessão

### 4. Página Vue do cliente (resources/js/Pages/Cliente/Mesa.vue)
Interface mobile-first simples:
- Header com nome da mesa e logo/nome do restaurante
- Produtos agrupados por categoria (tabs ou accordion)
- Cada produto: nome, descrição (se existir), botão "+" e "-" para quantidade, botão "Adicionar"
- Sem preços visíveis
- Sem acesso ao total, sem fechar conta
- Feedback visual ao adicionar (toast/mensagem de sucesso)
- Se pedido não está disponível (fechado/cancelado), mostrar mensagem amigável
- Design limpo, Tailwind CSS, compatível com telemóvel

### 5. Página de confirmação (resources/js/Pages/Cliente/Confirmacao.vue)
- Lista dos itens adicionados nesta sessão
- Mensagem "O seu pedido foi enviado para a cozinha"
- Botão para voltar a adicionar mais itens

### 6. QR Code no POS — PosRest/Mesa.vue
Na página de mesa do empregado, adicionar uma secção "Self-Order do Cliente":
- Gerar QR code do link /cliente/{pedido.cliente_token}
- Usar a biblioteca qrcode (já verificar se está no package.json, senão instalar com: npm install qrcode)
- Botão "Mostrar QR ao cliente" que abre modal com o QR code em tamanho grande
- Link copiável abaixo do QR
- Só mostrar se o pedido estiver ativo (pendente/preparacao)

### 7. Atualizar o Model Pedido
Em app/Models/Pedido.php:
- No boot(), gerar cliente_token automaticamente: Str::uuid() se não estiver definido

## Notas importantes
- Manter 100% da lógica existente intacta — não alterar PosRestController nem rotas existentes
- Usar PrintJobService igual ao PosRestController para impressão na cozinha
- A secao do item deve ser determinada igual ao PosRestController (normalizarSecao)
- Verificar se o package `qrcode` já existe antes de instalar
- Cria a migração com timestamp atual
- Não expor o cliente_token em listagens ou APIs desnecessárias