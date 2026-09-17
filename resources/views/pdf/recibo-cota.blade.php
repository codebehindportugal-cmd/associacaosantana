<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Recibo de cota #{{ $numero }}</title>
    <style>
        @page { size: A5 portrait; margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        .cab { width: 100%; border-bottom: 2px solid #2E4732; padding-bottom: 8px; }
        .cab td { vertical-align: middle; }
        .logo { width: 58px; }
        .assoc { font-size: 13px; font-weight: bold; color: #2E4732; line-height: 1.25; }
        .sub { font-size: 9px; color: #666; }
        .num { text-align: right; font-size: 10px; color: #444; }
        .num b { display: block; font-size: 15px; color: #222; }
        h1 { font-size: 15px; text-align: center; letter-spacing: 1px; margin: 14px 0 12px; }
        table.dados { width: 100%; border-collapse: collapse; }
        table.dados td { padding: 6px 4px; border-bottom: 1px dotted #999; }
        table.dados td.k { width: 32%; color: #555; }
        table.dados td.v { font-weight: bold; font-size: 12px; }
        table.anos { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.anos th, table.anos td { border: 1px solid #bbb; padding: 5px 6px; }
        table.anos th { background: #F4EAD5; text-align: left; }
        .dir, table.anos .dir { text-align: right; }
        .total td { font-weight: bold; font-size: 13px; background: #FBF6EB; }
        .assinaturas { width: 100%; margin-top: 38px; }
        .assinaturas td { width: 50%; text-align: center; font-size: 9px; color: #555; padding: 0 10px; }
        .linha { border-top: 1px solid #333; padding-top: 3px; }
        .rodape { position: fixed; bottom: -4mm; left: 0; right: 0; text-align: center; font-size: 8px; color: #888; }
    </style>
</head>
<body>
    <table class="cab">
        <tr>
            <td style="width:64px">@if($logo)<img src="{{ $logo }}" class="logo">@endif</td>
            <td>
                <div class="assoc">Associação Recreativa, Desportiva<br>e Cultural de Santana</div>
                <div class="sub">Santana · Carvalhal Benfeito · Caldas da Rainha</div>
            </td>
            <td class="num">Recibo N.º<b>{{ $numero }}</b>{{ $dataPagamento }}</td>
        </tr>
    </table>

    <h1>RECIBO DE PAGAMENTO DE QUOTA</h1>

    <table class="dados">
        <tr><td class="k">Sócio N.º</td><td class="v">{{ $socio->numero_socio }}</td></tr>
        <tr><td class="k">Nome</td><td class="v">{{ $socio->nome }}</td></tr>
        @if($socio->morada)
            <tr><td class="k">Localidade / Morada</td><td class="v">{{ $socio->morada }}</td></tr>
        @endif
        @if($socio->telefone)
            <tr><td class="k">Telefone</td><td class="v">{{ $socio->telefone }}</td></tr>
        @endif
        <tr><td class="k">Forma de pagamento</td><td class="v">{{ $metodo }}</td></tr>
    </table>

    <table class="anos">
        <tr><th>Referente a</th><th class="dir">Valor</th></tr>
        @foreach($cotas as $cota)
            <tr><td>Quota anual de {{ $cota->ano }}</td><td class="dir">{{ number_format((float) $cota->valor, 2, ',', '.') }} €</td></tr>
        @endforeach
        <tr class="total"><td>Total recebido</td><td class="dir">{{ number_format($total, 2, ',', '.') }} €</td></tr>
    </table>

    <p style="margin-top:12px">Recebemos do(a) sócio(a) acima identificado(a) a quantia de <b>{{ number_format($total, 2, ',', '.') }} €</b>, referente ao pagamento da(s) quota(s) indicada(s).</p>

    <table class="assinaturas">
        <tr>
            <td><div class="linha">O Sócio</div></td>
            <td><div class="linha">Pela Direção</div></td>
        </tr>
    </table>

    <div class="rodape">Obrigado pela sua contribuição! · Emitido em {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
