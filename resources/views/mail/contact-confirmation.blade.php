<p>Olá {{ $data['name'] }},</p>

<p>Recebemos a tua mensagem e a associação irá responder assim que possível.</p>

<p><strong>A tua mensagem:</strong></p>
<p>{!! nl2br(e($data['message'])) !!}</p>

<p>Obrigado,<br>ARDC Santana</p>
