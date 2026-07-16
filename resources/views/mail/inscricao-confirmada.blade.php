<p>Olá {{ $inscricao->nome }},</p>

<p>A tua inscrição no evento <strong>{{ $inscricao->evento->titulo }}</strong> está confirmada! 🎉</p>

<p><strong>Detalhes da inscrição:</strong></p>
<ul>
    <li>Evento: {{ $inscricao->evento->titulo }}</li>
    @if ($inscricao->evento->data_inicio)
        <li>Data: {{ $inscricao->evento->data_inicio->format('d/m/Y') }}@if ($inscricao->evento->periodo) · {{ $inscricao->evento->periodo }}@endif</li>
    @endif
    @if ($inscricao->evento->localizacao)
        <li>Local: {{ $inscricao->evento->localizacao }}</li>
    @endif
    <li>Nome: {{ $inscricao->nome }}</li>
    <li>Nº de pessoas: {{ $inscricao->num_pessoas }}</li>
    @if ($inscricao->opcao)
        <li>Opção: {{ $inscricao->opcao }}</li>
    @endif
    @if ($inscricao->num_criancas)
        <li>Crianças: {{ $inscricao->num_criancas }}@if ($inscricao->idades_criancas) ({{ $inscricao->idades_criancas }})@endif</li>
    @endif
    @if ($inscricao->valor_estimado !== null)
        <li>Valor: {{ number_format($inscricao->valor_estimado, 2, ',', '.') }} €
            @if ($inscricao->pagamento_estado === 'pago')
                — <strong>pago online ✅</strong>
            @else
                — pagamento no dia do evento
            @endif
        </li>
    @endif
</ul>

<p>Se precisares de alterar ou cancelar a inscrição, responde a este email.</p>

<p>Até já,<br>ARDC Santana</p>
