<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 22px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td, th { border: 1px solid #ddd; padding: 6px; text-align: left; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Associação de Santana - Relatório de Vendas</h1>
    <p>Período: {{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }} · Gerado em {{ now()->format('d/m/Y H:i') }}</p>

    <table><tr><th>Total</th><th>Nº Pedidos</th></tr><tr><td>{{ number_format($total, 2, ',', '.') }}€</td><td>{{ $total_pedidos }}</td></tr></table>

    <h2>Vendas por dia</h2>
    <table><tr><th>Data</th><th class="right">Total</th></tr>@foreach($vendas_por_dia as $dia)<tr><td>{{ $dia['data'] }}</td><td class="right">{{ number_format($dia['total'], 2, ',', '.') }}€</td></tr>@endforeach</table>

    <h2>Resumo por tipo</h2>
    <table><tr><th>Tipo</th><th class="right">Total</th></tr>@foreach($vendas_por_tipo as $linha)<tr><td>{{ $linha['tipo'] }}</td><td class="right">{{ number_format($linha['total'], 2, ',', '.') }}€</td></tr>@endforeach</table>

    <h2>Dinheiro do Bar por ponto</h2>
    <table><tr><th>Ponto</th><th>Pedidos</th><th class="right">Total</th></tr>@foreach($vendas_bar_por_ponto as $linha)<tr><td>{{ $linha['ponto'] }}</td><td>{{ $linha['pedidos'] }}</td><td class="right">{{ number_format($linha['total'], 2, ',', '.') }}€</td></tr>@endforeach</table>

    <h2>Caixa e fundo de maneio</h2>
    <table><tr><th>Ponto</th><th>Dias</th><th>Fechados</th><th class="right">Fundo</th><th class="right">Vendas</th><th class="right">Esperado</th><th class="right">Contado</th><th class="right">Diferença</th></tr>@foreach($caixas_por_ponto as $linha)<tr><td>{{ $linha['ponto'] }}</td><td>{{ $linha['dias_abertos'] }}</td><td>{{ $linha['dias_fechados'] ?? 0 }}</td><td class="right">{{ number_format($linha['fundo_maneio'], 2, ',', '.') }}€</td><td class="right">{{ number_format($linha['vendas'], 2, ',', '.') }}€</td><td class="right">{{ number_format($linha['esperado_caixa'], 2, ',', '.') }}€</td><td class="right">{{ number_format($linha['valor_contado'] ?? 0, 2, ',', '.') }}€</td><td class="right">{{ number_format($linha['diferenca'] ?? 0, 2, ',', '.') }}€</td></tr>@endforeach</table>

    <h2>Top produtos</h2>
    <table><tr><th>Produto</th><th>Categoria</th><th>Qtd</th><th class="right">Total</th></tr>@foreach($top_produtos as $produto)<tr><td>{{ $produto->nome }}</td><td>{{ $produto->categoria }}</td><td>{{ $produto->quantidade }}</td><td class="right">{{ number_format($produto->total, 2, ',', '.') }}€</td></tr>@endforeach</table>
</body>
</html>
