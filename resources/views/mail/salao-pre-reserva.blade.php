<p>Olá {{ $aluguer->nome_cliente }},</p>

<p>Recebemos a tua pré-reserva do salão da ARDC Santana! Entraremos em contacto brevemente para confirmar a disponibilidade e os detalhes.</p>

<p><strong>Detalhes da pré-reserva:</strong></p>
<ul>
    <li>Nome: {{ $aluguer->nome_cliente }}</li>
    <li>Datas: {{ $aluguer->data_inicio->format('d/m/Y') }} → {{ $aluguer->data_fim->format('d/m/Y') }} ({{ $aluguer->numero_dias }} {{ $aluguer->numero_dias === 1 ? 'dia' : 'dias' }})</li>
    @if ($aluguer->opcoes->count())
        <li>Opções solicitadas: {{ $aluguer->opcoes->pluck('nome')->join(', ') }}</li>
    @endif
    @if ($aluguer->notas)
        <li>Notas: {{ $aluguer->notas }}</li>
    @endif
</ul>

<p>Se tiveres alguma questão, responde a este email ou contacta-nos pelo telefone.</p>

<p>Até já,<br>ARDC Santana</p>
