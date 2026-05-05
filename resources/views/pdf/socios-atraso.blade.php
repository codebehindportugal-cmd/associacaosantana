<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Associação de Santana - Sócios com Cotas em Atraso</h1>
    <p>Data de geração: {{ now()->format('d/m/Y H:i') }}</p>
    <table>
        <thead><tr><th>Número sócio</th><th>Nome</th><th>Meses em atraso</th><th>Valor em dívida</th></tr></thead>
        <tbody>
            @foreach ($socios as $socio)
                <tr><td>{{ $socio['numero_socio'] }}</td><td>{{ $socio['nome'] }}</td><td>{{ $socio['meses_atraso'] }}</td><td>{{ number_format($socio['valor_divida'], 2, ',', '.') }}€</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr><th colspan="3">Total geral</th><th>{{ number_format($total, 2, ',', '.') }}€</th></tr></tfoot>
    </table>
</body>
</html>
