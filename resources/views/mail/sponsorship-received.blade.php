<p>Foi recebido um novo pedido de patrocínio pelo site da ARDC Santana.</p>

<p><strong>Nome:</strong> {{ $pedido->nome }}</p>
<p><strong>Empresa:</strong> {{ $pedido->empresa }}</p>
<p><strong>Email:</strong> {{ $pedido->email }}</p>
<p><strong>Telefone:</strong> {{ $pedido->telefone ?: 'Não indicado' }}</p>

<p><strong>Mensagem/proposta:</strong></p>
<p>{!! nl2br(e($pedido->mensagem ?: 'Sem mensagem adicional.')) !!}</p>

<p>Estado inicial: {{ $pedido->estado }}</p>
