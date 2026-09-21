<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Recibo de quota</title>
    @php
        $p = config('recibos.papel');
        $ox = $p['offset_x'];
        $oy = $p['offset_y'];
        $fonte = $p['fonte_pt'];
        $azul = $p['cor_formulario'];

        // Posiciona texto pela linha de base (mm). DejaVu no DomPDF: base ≈ topo + 1,37 × tamanho.
        $txt = function (float $x, float $y, float $pt, string $extra = '') use ($ox, $oy) {
            $mm = $pt * 0.3528;
            return sprintf('left:%.2fmm;top:%.2fmm;height:%.2fmm;line-height:%.2fmm;font-size:%.1fpt;%s',
                $x + $ox, $y + $oy - 1.37 * $mm, 1.29 * $mm, 1.29 * $mm, $pt, $extra);
        };
        // Linha horizontal (onde se escreve), de x1 a x2, à altura y
        $linha = fn (float $x1, float $x2, float $y) => sprintf('left:%.2fmm;top:%.2fmm;width:%.2fmm;',
            $x1 + $ox, $y + $oy + 0.6, $x2 - $x1);

        $rotulo = 10.5; // tamanho dos rótulos impressos (Sócio Nº, Euros…)

        // Elementos fixos do formulário (texto, x, y base)
        $rotulos = [
            ['Sócio Nº', 33, 53.5], ['Exmo. Sr.', 80.5, 53.5],
            ['Euros', 33, 63.7], [',', 72.6, 63.7], ['Ano 20', 87.5, 63.7],
            ['Sócio Nº', 33, 85], ['Ano 20', 181, 85],
            ['Euros', 33, 95.5], [',', 75, 95.5], ['A Direcção', 101.5, 95.5],
        ];
        $linhas = [
            [51, 79, 53.5], [100, 206, 53.5],
            [44.5, 71.7, 63.7], [75, 85.5, 63.7], [101.5, 112, 63.7], [117, 206, 63.7],
            [50, 78, 85], [195.5, 206, 85],
            [44.5, 74, 95.5], [77.5, 88, 95.5], [124.5, 206, 95.5],
        ];
    @endphp
    <style>
        @page { size: {{ $p['largura'] }}mm {{ $p['altura'] }}mm; margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; color: #000; }
        .folha { position: relative; width: {{ $p['largura'] }}mm; height: {{ $p['altura'] }}mm; overflow: hidden; }
        .quebra { page-break-after: always; }
        .t { position: absolute; white-space: nowrap; }
        .f { color: {{ $azul }}; }
        .campo { font-weight: bold; overflow: hidden; }
        .l { position: absolute; height: 0; border-top: 0.5pt solid {{ $azul }}; }
        .picotado { position: absolute; left: 0; width: {{ $p['largura'] }}mm; height: 0; border-top: 0.6pt dashed #999; }
        .logo { position: absolute; }
        /* Modo grelha (acertar margens da impressora) */
        .g-v { position: absolute; top: 0; width: 0; height: {{ $p['altura'] }}mm; border-left: 0.2pt solid #ccc; }
        .g-h { position: absolute; left: 0; height: 0; width: {{ $p['largura'] }}mm; border-top: 0.2pt solid #ccc; }
        .g-n { position: absolute; font-size: 5pt; color: #999; }
        .g-borda { position: absolute; left: 0; top: 0; width: {{ $p['largura'] - 0.4 }}mm; height: {{ $p['altura'] - 0.4 }}mm; border: 0.4pt dashed #e11; }
    </style>
</head>
<body>
@foreach ($recibos as $r)
    <div class="folha{{ $loop->last ? '' : ' quebra' }}">
        @if ($grelha)
            <div class="g-borda"></div>
            @for ($x = 10; $x < $p['largura']; $x += 10)
                <div class="g-v" style="left: {{ $x }}mm"></div>
                <div class="g-n" style="left: {{ $x + 0.5 }}mm; top: 1mm">{{ $x }}</div>
            @endfor
            @for ($y = 10; $y < $p['altura']; $y += 10)
                <div class="g-h" style="top: {{ $y }}mm"></div>
                <div class="g-n" style="left: 1mm; top: {{ $y + 0.5 }}mm">{{ $y }}</div>
            @endfor
        @endif

        @if ($p['imprimir_formulario'])
            {{-- Cabeçalho --}}
            @if ($logo)
                <img class="logo" src="{{ $logo }}" style="left: {{ 34 + $ox }}mm; top: {{ 14.5 + $oy }}mm; width: 25mm; height: 25mm;">
            @endif
            <div class="t f" style="{{ $txt(62, 18, 14, 'width:120mm;text-align:center;font-weight:bold;') }}">ASSOCIAÇÃO RECREATIVA, DESPORTIVA</div>
            <div class="t f" style="{{ $txt(62, 24.3, 14, 'width:120mm;text-align:center;font-weight:bold;') }}">E CULTURAL DE SANTANA</div>
            <div class="l" style="left: {{ 87.5 + $ox }}mm; top: {{ 27.5 + $oy }}mm; width: 69mm;"></div>
            <div class="t f" style="{{ $txt(62, 33.3, 11.5, 'width:120mm;text-align:center;') }}">CARVALHAL BENFEITO</div>

            {{-- Picotado entre o recibo e o destacável --}}
            <div class="picotado" style="top: {{ 72.5 + $oy }}mm"></div>

            @foreach ($rotulos as [$texto, $x, $y])
                <div class="t f" style="{{ $txt($x, $y, $rotulo) }}">{{ $texto }}</div>
            @endforeach
            @foreach ($linhas as [$x1, $x2, $y])
                <div class="l" style="{{ $linha($x1, $x2, $y) }}"></div>
            @endforeach
        @endif

        {{-- Dados do sócio --}}
        @foreach ($r as $nome => $valor)
            @php
                $c = $p['campos'][$nome] ?? null;
                if (! $c) continue;
                $tam = $nome === 'nome' && mb_strlen($valor) > 42 ? max(7, $fonte - 2) : $fonte;
            @endphp
            <div class="t campo" style="{{ $txt($c['x'], $c['y'] - 0.3, $tam, 'width:'.$c['w'].'mm;text-align:'.($c['alinhar'] ?? 'left').';') }}">{{ $valor }}</div>
        @endforeach
    </div>
@endforeach
</body>
</html>
