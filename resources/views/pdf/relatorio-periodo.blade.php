<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin: 18px 0 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background: #f3f4f2; }
        .right { text-align: right; }
        .total td { font-weight: bold; background: #f3f4f2; }
        .meta { color: #555; margin: 0; }
    </style>
</head>
<body>
    @php($eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €')
    <h1>Associação de Santana - Relatório de Vendas</h1>
    <p class="meta">Período: {{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }} · Tipo: {{ $tipo }} · Gerado em {{ now()->format('d/m/Y H:i') }}</p>

    @if(in_array('resumo', $seccoes))
        <table><tr><th>Total</th><th>Nº Pedidos</th></tr><tr><td>{{ $eur($total) }}</td><td>{{ $total_pedidos }}</td></tr></table>
    @endif

    @if(in_array('dias', $seccoes))
        <h2>Vendas por dia</h2>
        <table><tr><th>Data</th><th class="right">Total</th></tr>@foreach($vendas_por_dia as $dia)<tr><td>{{ \Illuminate\Support\Carbon::parse($dia['data'])->format('d/m/Y') }}</td><td class="right">{{ $eur($dia['total']) }}</td></tr>@endforeach</table>
    @endif

    @if(in_array('tipos', $seccoes))
        <h2>Resumo por tipo</h2>
        <table><tr><th>Tipo</th><th class="right">Total</th></tr>@foreach($vendas_por_tipo as $linha)<tr><td>{{ ['restaurante' => 'Restaurante', 'bar_conta' => 'Bar Conta', 'bar_prepago' => 'Bar Pré-pago'][$linha['tipo']] ?? $linha['tipo'] }}</td><td class="right">{{ $eur($linha['total']) }}</td></tr>@endforeach</table>
    @endif

    @if(in_array('bar', $seccoes))
        <h2>Dinheiro do Bar por ponto</h2>
        <table><tr><th>Ponto</th><th>Pedidos</th><th class="right">Total</th></tr>@foreach($vendas_bar_por_ponto as $linha)<tr><td>{{ $linha['ponto'] }}</td><td>{{ $linha['pedidos'] }}</td><td class="right">{{ $eur($linha['total']) }}</td></tr>@endforeach</table>
    @endif

    @if(in_array('caixa', $seccoes))
        <h2>Caixa e fundo de maneio</h2>
        <table><tr><th>Ponto</th><th>Dias</th><th>Fechados</th><th class="right">Fundo</th><th class="right">Vendas</th><th class="right">Esperado</th><th class="right">Contado</th><th class="right">Diferença</th></tr>@foreach($caixas_por_ponto as $linha)<tr><td>{{ $linha['ponto'] }}</td><td>{{ $linha['dias_abertos'] }}</td><td>{{ $linha['dias_fechados'] ?? 0 }}</td><td class="right">{{ $eur($linha['fundo_maneio']) }}</td><td class="right">{{ $eur($linha['vendas']) }}</td><td class="right">{{ $eur($linha['esperado_caixa']) }}</td><td class="right">{{ $eur($linha['valor_contado'] ?? 0) }}</td><td class="right">{{ $eur($linha['diferenca'] ?? 0) }}</td></tr>@endforeach</table>
    @endif

    @if(in_array('operadores', $seccoes) && count($operadores))
        <h2>Vendas por operador</h2>
        <table>
            <tr><th>Operador</th><th class="right">Vendas</th><th class="right">Total</th><th class="right">Dinheiro</th><th class="right">MB WAY</th><th class="right">Outros</th><th class="right">Anuladas</th><th class="right">Devolvido</th></tr>
            @foreach($operadores as $o)
                <tr><td>{{ $o['operador'] }}</td><td class="right">{{ $o['pedidos'] }}</td><td class="right">{{ $eur($o['total']) }}</td><td class="right">{{ $eur($o['dinheiro']) }}</td><td class="right">{{ $eur($o['mbway']) }}</td><td class="right">{{ $eur($o['outros']) }}</td><td class="right">{{ $o['anuladas'] }}</td><td class="right">{{ $eur($o['devolvido']) }}</td></tr>
            @endforeach
        </table>
    @endif

    @if(in_array('produtos', $seccoes))
        <h2>{{ $soTop10 ? 'Top 10 produtos' : 'Produtos vendidos ('.count($produtos).')' }}</h2>
        <table>
            <tr>
                <th>Produto</th>
                @foreach($colunas as $c)<th class="{{ $c === 'categoria' ? '' : 'right' }}">{{ $nomesColunas[$c] }}</th>@endforeach
            </tr>
            @foreach($produtos as $p)
                <tr>
                    <td>{{ $p->nome }}</td>
                    @foreach($colunas as $c)
                        @switch($c)
                            @case('categoria')<td>{{ $p->categoria ?: '—' }}</td>@break
                            @case('quantidade')<td class="right">{{ (int) $p->quantidade }}</td>@break
                            @case('total')<td class="right">{{ $eur($p->total) }}</td>@break
                            @case('custo')<td class="right">{{ $eur($p->custo_estimado) }}</td>@break
                            @case('margem')<td class="right">{{ $eur($p->margem_estimada) }}</td>@break
                            @case('margem_pct')<td class="right">{{ number_format((float) $p->margem_percentagem, 1, ',', '.') }}%</td>@break
                        @endswitch
                    @endforeach
                </tr>
            @endforeach
            @if(count($produtos) > 1)
                @php($somaTotal = collect($produtos)->sum('total'))
                @php($somaMargem = collect($produtos)->sum('margem_estimada'))
                <tr class="total">
                    <td>Total</td>
                    @foreach($colunas as $c)
                        @switch($c)
                            @case('categoria')<td></td>@break
                            @case('quantidade')<td class="right">{{ (int) collect($produtos)->sum('quantidade') }}</td>@break
                            @case('total')<td class="right">{{ $eur($somaTotal) }}</td>@break
                            @case('custo')<td class="right">{{ $eur(collect($produtos)->sum('custo_estimado')) }}</td>@break
                            @case('margem')<td class="right">{{ $eur($somaMargem) }}</td>@break
                            @case('margem_pct')<td class="right">{{ $somaTotal > 0 ? number_format($somaMargem / $somaTotal * 100, 1, ',', '.') : '0,0' }}%</td>@break
                        @endswitch
                    @endforeach
                </tr>
            @endif
        </table>
    @endif

    @if(in_array('stock', $seccoes) && count($stock))
        @php($qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ','))
        <h2>Stock no período</h2>
        <table>
            <tr><th>Produto</th><th>Categoria</th><th class="right">Inicial</th><th class="right">Entradas</th><th class="right">Vendido</th><th class="right">Final</th></tr>
            @foreach($stock as $l)
                <tr><td>{{ $l['nome'] }}</td><td>{{ $l['categoria'] ?: '—' }}</td><td class="right">{{ $qtd($l['inicial']) }}</td><td class="right">{{ $qtd($l['entradas']) }}</td><td class="right">{{ $qtd($l['vendido']) }}</td><td class="right">{{ $qtd($l['final']) }}</td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
