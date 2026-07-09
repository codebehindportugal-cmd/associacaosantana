<p>Olá {{ $pedido->nome }},</p>

<p>Recebemos a sua proposta de patrocínio em nome de {{ $pedido->empresa }}. Obrigado pelo interesse em apoiar a Festa de Santa Ana.</p>

<p><strong>Resumo enviado:</strong></p>
<p>{!! nl2br(e($pedido->mensagem ?: 'Sem mensagem adicional.')) !!}</p>

<p>A associação irá entrar em contacto brevemente para combinar os detalhes de contribuição e visibilidade.</p>

<p>Obrigado,<br>ARDC Santana</p>
