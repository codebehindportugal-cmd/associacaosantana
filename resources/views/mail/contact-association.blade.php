<p>Foi recebido um novo contacto pelo site da ARDC Santana.</p>

<p><strong>Nome:</strong> {{ $data['name'] }}</p>
<p><strong>Email:</strong> {{ $data['email'] }}</p>
<p><strong>Telefone:</strong> {{ $data['phone'] }}</p>

<p><strong>Mensagem:</strong></p>
<p>{!! nl2br(e($data['message'])) !!}</p>
