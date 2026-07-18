<p>Olá {{ $reserva->nome }},</p>

<p>A tua reserva no restaurante da <strong>ARDC Santana</strong> está confirmada! 🎉</p>

<p><strong>Detalhes da reserva:</strong></p>
<ul>
    <li>Data: {{ \Carbon\Carbon::parse($reserva->data)->format('d/m/Y') }}</li>
    <li>Hora: {{ substr($reserva->hora, 0, 5) }}</li>
    <li>Nº de pessoas: {{ $reserva->pessoas }}</li>
    @if ($reserva->observacoes)
        <li>Observações: {{ $reserva->observacoes }}</li>
    @endif
</ul>

<p>Se precisares de alterar ou cancelar a reserva, responde a este email ou liga-nos diretamente.</p>

<p>Até já,<br>ARDC Santana</p>
