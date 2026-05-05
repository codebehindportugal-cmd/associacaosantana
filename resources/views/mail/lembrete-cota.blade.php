<!doctype html>
<html lang="pt">
<body>
    <h1>Associação de Santana</h1>
    <p>Olá {{ $nomeSocio }},</p>
    <p>Registamos {{ $mesesAtraso }} mês(es) de cota em atraso, no valor total de {{ number_format($valorDivida, 2, ',', '.') }}€.</p>
    <p>Pedimos que regularize a situação junto da tesouraria assim que possível.</p>
    <p>Obrigado.</p>
</body>
</html>
