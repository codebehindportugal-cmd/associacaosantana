<?php

namespace App\Services;

use App\Models\Impressora;
use App\Models\Pedido;
use App\Models\PrintJob;
use App\Models\TalaoConfig;
use Illuminate\Support\Facades\Log;

class PrintJobService
{
    /**
     * Quando definida, todos os taloes desta venda saem nesta impressora,
     * em vez de seguirem a seccao. Usado pelos terminais POS, para o talao
     * sair no posto onde o cliente esta.
     */
    private ?int $impressoraForcada = null;

    public function paraImpressora(?int $impressoraId): static
    {
        $this->impressoraForcada = $impressoraId ?: null;

        return $this;
    }

    public function criarItemPedido(Pedido $pedido, array $item, ?string $secao = null, string $tipo = 'pedido'): ?PrintJob
    {
        return $this->criarPedido($pedido, $secao, $tipo, [$item]);
    }

    public function criarAnulacaoItemPedido(Pedido $pedido, array $item, ?string $secao = null): ?PrintJob
    {
        return $this->criarPedido($pedido, $secao, 'anulado', [[
            'quantidade' => $item['quantidade'],
            'nome' => 'ANULADO: '.$item['nome'],
            'observacoes' => $item['observacoes'] ?? null,
        ]]);
    }

    public function criarPedido(Pedido $pedido, ?string $secao = null, string $tipo = 'pedido', ?array $items = null): ?PrintJob
    {
        $impressora = $this->impressoraParaSecao($secao);

        if (! $impressora) {
            return null;
        }

        $pedido->loadMissing('mesa.mesaPrincipal', 'user', 'pos', 'items.produto.categoria');

        return PrintJob::create([
            'impressora_id' => $impressora->id,
            'printable_type' => $pedido::class,
            'printable_id' => $pedido->id,
            'tipo' => $tipo,
            'payload' => [
                'titulo' => TalaoConfig::atual()->tituloImpresso(),
                'subtitulo' => strtoupper($tipo),
                'linhas' => $this->linhasPedido($pedido, $secao, $items),
                'cortar' => true,
            ],
        ]);
    }

    public function criarConta(Pedido $pedido, string $secao = 'contas'): ?PrintJob
    {
        $impressora = $this->impressoraParaSecao($secao);

        if (! $impressora) {
            return null;
        }

        $pedido->loadMissing('mesa.mesaPrincipal', 'user', 'pos', 'items.produto.categoria');

        return PrintJob::create([
            'impressora_id' => $impressora->id,
            'printable_type' => $pedido::class,
            'printable_id' => $pedido->id,
            'tipo' => 'conta',
            'payload' => [
                'titulo' => TalaoConfig::atual()->tituloImpresso(),
                'subtitulo' => 'CONTA',
                'linhas' => $this->linhasConta($pedido),
                'cortar' => true,
            ],
        ]);
    }

    public function criarTalaoBar(Pedido $pedido, string $secao = 'bar', string $subtitulo = 'SENHA'): ?PrintJob
    {
        $impressora = $this->impressoraParaSecao($secao);

        if (! $impressora) {
            return null;
        }

        return PrintJob::create([
            'impressora_id' => $impressora->id,
            'printable_type' => $pedido::class,
            'printable_id' => $pedido->id,
            'tipo' => 'talao_bar',
            'payload' => $this->payloadTalaoBar($pedido, $subtitulo),
        ]);
    }

    /**
     * Payload do talao agrupado. Publico para o POS o poder imprimir pelo
     * browser (WebUSB) sem passar pela fila do agente — mesmos bytes, mesmo
     * resultado.
     */
    public function payloadTalaoBar(Pedido $pedido, string $subtitulo = 'SENHA'): array
    {
        $pedido->loadMissing('user', 'pos', 'items.produto.categoria');

        return [
            'titulo' => TalaoConfig::atual()->tituloImpresso(),
            'subtitulo' => $subtitulo,
            'linhas' => $this->linhasTalaoBar($pedido),
            'cortar' => true,
        ];
    }

    /**
     * Cria uma senha individual para 1 unidade de um produto (ex: frango).
     * Usar quando o produto precisa de senha por unidade em vez de "2x Frango".
     */
    public function criarTalaoBarUnitario(Pedido $pedido, string $nomeProduto, string $secao = 'bar', int $indice = 1, int $totalTaloes = 1, ?string $secaoLevantamento = null): ?PrintJob
    {
        $impressora = $this->impressoraParaSecao($secao);

        if (! $impressora) {
            return null;
        }

        return PrintJob::create([
            'impressora_id' => $impressora->id,
            'printable_type' => $pedido::class,
            'printable_id' => $pedido->id,
            'tipo' => 'talao_bar',
            'payload' => $this->payloadTalaoUnitario($pedido, $nomeProduto, $indice, $totalTaloes, $secaoLevantamento),
        ]);
    }

    /**
     * Talao de uma seccao, para o cliente levantar naquela tasquinha.
     *
     * Cada unidade leva a sua linha — "1x Imperial" duas vezes em vez de
     * "2x Imperial" — para quem entrega ir riscando o que ja deu.
     */
    public function payloadTalaoSeccao(Pedido $pedido, string $secao, array $unidades, int $indice = 1, int $totalTaloes = 1): array
    {
        $talao = TalaoConfig::atual();

        return [
            'titulo' => $talao->tituloImpresso(),
            'subtitulo' => 'SENHA',
            'linhas' => [
                ...$talao->linhasCabecalho(),
                'Ponto: '.($pedido->ponto_bar ?: 'Bar'),
                'Hora: '.now()->format('H:i'),
                ...($pedido->numero_senha ? [[
                    'texto' => 'SENHA #'.$pedido->numero_senha,
                    'alinhamento' => 'centro',
                    'tamanho' => 'grande',
                ]] : []),
                '------------------------------',
                [
                    'texto' => mb_strtoupper($this->nomeSecao($secao), 'UTF-8'),
                    'alinhamento' => 'centro',
                    'tamanho' => 'grande',
                ],
                '------------------------------',
                ...array_map(fn ($nome) => [
                    'texto' => '1x '.$nome,
                    'alinhamento' => 'centro',
                    'tamanho' => 'grande',
                ], $unidades),
                '------------------------------',
                ...($totalTaloes > 1 ? [[
                    'texto' => 'Talao '.$indice.' de '.$totalTaloes,
                    'alinhamento' => 'centro',
                ]] : []),
                ...$talao->linhasInstrucoes(),
            ],
            'cortar' => true,
        ];
    }

    public function criarTalaoSeccao(Pedido $pedido, string $secao, array $unidades, string $secaoImpressora = 'bar', int $indice = 1, int $totalTaloes = 1): ?PrintJob
    {
        $impressora = $this->impressoraParaSecao($secaoImpressora);

        if (! $impressora) {
            return null;
        }

        return PrintJob::create([
            'impressora_id' => $impressora->id,
            'printable_type' => $pedido::class,
            'printable_id' => $pedido->id,
            'tipo' => 'talao_bar',
            'payload' => $this->payloadTalaoSeccao($pedido, $secao, $unidades, $indice, $totalTaloes),
        ]);
    }

    /**
     * Agrupa o que foi comprado por seccao, com uma entrada por unidade.
     *
     *   ['bebidas' => ['Agua', 'Imperial', 'Imperial'], 'frango' => ['Frango']]
     */
    public function unidadesPorSeccao(Pedido $pedido): array
    {
        $pedido->loadMissing('items.produto.categoria');
        $grupos = [];

        foreach ($pedido->items as $item) {
            $secao = $item->secao ?: ($item->produto->categoria->secao ?? 'outros');
            $nome = $item->produto?->nome ?? 'Produto';

            for ($u = 0; $u < (int) $item->quantidade; $u++) {
                $grupos[$secao][] = $nome;
            }
        }

        return $grupos;
    }

    /** Nome legivel da seccao, para o cliente saber onde levantar. */
    public function nomeSecao(?string $secao): string
    {
        return [
            'bebidas' => 'Bebidas',
            'bar' => 'Bar',
            'cafe' => 'Cafe',
            'frango' => 'Frango',
            'acompanhamentos' => 'Acompanhamentos',
            'comida' => 'Comida',
            'cozinha' => 'Cozinha',
            'sobremesas' => 'Sobremesas',
            'servico' => 'Servico',
        ][$secao] ?? ucfirst(str_replace('_', ' ', (string) ($secao ?: 'Tasquinha')));
    }

    /**
     * Payload do talao unitario que o cliente leva para levantar.
     */
    public function payloadTalaoUnitario(Pedido $pedido, string $nomeProduto, int $indice = 1, int $totalTaloes = 1, ?string $secaoLevantamento = null): array
    {
        $talao = TalaoConfig::atual();

        return [
            'titulo' => $talao->tituloImpresso(),
            'subtitulo' => 'SENHA',
            'linhas' => [
                ...$talao->linhasCabecalho(),
                'Ponto: '.($pedido->ponto_bar ?: 'Bar'),
                'Hora: '.now()->format('H:i'),
                ...($pedido->numero_senha ? [[
                    'texto' => 'SENHA #'.$pedido->numero_senha,
                    'alinhamento' => 'centro',
                    'tamanho' => 'grande',
                ]] : []),
                '------------------------------',
                [
                    'texto' => '1x '.$nomeProduto,
                    'alinhamento' => 'centro',
                    'tamanho' => 'grande',
                ],
                // Onde o cliente vai levantar este talao, em destaque
                ...($secaoLevantamento ? [[
                    'texto' => mb_strtoupper($this->nomeSecao($secaoLevantamento), 'UTF-8'),
                    'alinhamento' => 'centro',
                    'tamanho' => 'grande',
                ]] : []),
                ...($totalTaloes > 1 ? [[
                    'texto' => 'Talao '.$indice.' de '.$totalTaloes,
                    'alinhamento' => 'centro',
                ]] : []),
                '------------------------------',
                ...$talao->linhasInstrucoes(),
            ],
            'cortar' => true,
        ];
    }

    private function impressoraParaSecao(?string $secao): ?Impressora
    {
        if ($this->impressoraForcada) {
            $forcada = Impressora::where('ativa', true)->find($this->impressoraForcada);

            if ($forcada) {
                return $forcada;
            }
        }

        $query = Impressora::query()
            ->where('ativa', true)
            ->orderBy('id');

        if (! $secao) {
            return $query->first();
        }

        // 1. Correspondência exacta
        $impressora = (clone $query)
            ->where('secao', $secao)
            ->first();

        if ($impressora) {
            return $impressora;
        }

        // 2. Secções equivalentes
        $impressora = (clone $query)
            ->whereIn('secao', $this->secoesEquivalentes($secao))
            ->first();

        if ($impressora) {
            return $impressora;
        }

        // 3. Fallback final: bebidas/bar/café e contas nunca usam impressora genérica
        // para evitar que bebidas do café imprimam na cozinha do restaurante
        if (in_array($secao, ['contas', 'pos', 'caixa', 'cafe', 'bar', 'bebidas'])) {
            Log::warning('Impressao sem impressora para a seccao', [
                'secao' => $secao,
                'equivalentes' => $this->secoesEquivalentes($secao),
            ]);

            return null;
        }

        // Para comida, frango, sobremesas, etc. usa a primeira impressora ativa
        // para não perder pedidos de cozinha
        return $query->first();
    }

    private function secoesEquivalentes(string $secao): array
    {
        return match ($secao) {
            // Balcao (bar/cafe) e sala (bebidas) sao pontos fisicos diferentes:
            // nunca se misturam entre si nem caem para a cozinha.
            'bebidas'        => ['bebidas'],
            'bar'            => ['bar', 'cafe'],
            'cafe'           => ['cafe', 'bar'],
            'comida'         => ['comida', 'cozinha', 'frango', 'acompanhamentos'],
            'cozinha'        => ['cozinha', 'comida', 'frango', 'acompanhamentos'],
            'frango'         => ['frango', 'cozinha', 'comida', 'acompanhamentos'],
            'sobremesas'     => ['sobremesas', 'comida', 'cozinha', 'acompanhamentos'],
            'acompanhamentos'=> ['acompanhamentos', 'comida', 'cozinha'],
            'servico'        => ['servico', 'comida', 'cozinha', 'acompanhamentos'],
            'contas'         => ['contas', 'pos', 'caixa'],
            'pos'            => ['pos', 'contas', 'caixa'],
            default          => [$secao],
        };
    }

    private function linhasPedido(Pedido $pedido, ?string $secao, ?array $itemsParaImprimir = null): array
    {
        $mesaPedido = $pedido->mesa;
        $mesaPrincipal = $mesaPedido?->mesaPrincipal ?: $mesaPedido;
        $submesa = $mesaPedido?->mesaPrincipal ? $mesaPedido : null;
        $operador = $pedido->operador_nome ?: ($pedido->user?->name ?: $pedido->pos?->nome);
        $items = $itemsParaImprimir
            ? collect($itemsParaImprimir)
            : $pedido->items
                ->filter(fn ($item) => ! $secao || $item->secao === $secao)
                ->map(fn ($item) => [
                'quantidade' => $item->quantidade,
                'nome' => $item->produto?->nome ?? 'Produto',
                'observacoes' => $item->observacoes,
                'prioridade' => (bool) $item->prioridade,
                ]);

        $items = $items->values();

        $talao = TalaoConfig::atual();

        return [
            ...$talao->linhasCabecalho(),
            'Tipo: '.$pedido->tipo,
            ...$this->linhasMesa($mesaPrincipal, $submesa),
            ...($pedido->nome_reserva ? ['Reserva: '.$pedido->nome_reserva] : []),
            'Operador: '.($operador ?: 'Sem operador'),
            'Hora: '.now()->format('H:i'),
            '------------------------------',
            ...$items->map(fn ($item) => [
                'texto' => ($item['prioridade'] ?? false ? '*** A TERMINAR *** ' : '').$item['quantidade'].'x '.$item['nome'].($item['observacoes'] ? ' - '.$item['observacoes'] : ''),
                'alinhamento' => 'centro',
                'tamanho' => 'grande',
            ])->all(),
            '------------------------------',
            ...($talao->rodape_em_pedidos ? $talao->linhasRodape() : []),
        ];
    }

    private function linhasIdentificacao(Pedido $pedido): array
    {
        if ($pedido->numero_senha) {
            return [[
                'texto' => 'SENHA #'.$pedido->numero_senha,
                'alinhamento' => 'centro',
                'tamanho' => 'grande',
            ]];
        }

        return [];
    }

    public function criarPedidoExtra(\App\Models\Mesa $mesa, string $descricao, string $secao = 'contas'): ?PrintJob
    {
        $impressora = $this->impressoraParaSecao($secao);

        if (! $impressora) {
            return null;
        }

        $mesaPrincipal = $mesa->mesaPrincipal ?: $mesa;
        $submesa = $mesa->mesa_principal_id ? $mesa : null;
        $mesaTexto = 'MESA '.$mesaPrincipal->numero.($submesa ? $this->letraSubmesa($submesa) : '');

        return PrintJob::create([
            'impressora_id'  => $impressora->id,
            'printable_type' => \App\Models\Mesa::class,
            'printable_id'   => $mesa->id,
            'tipo'           => 'pedido_extra',
            'payload'        => [
                'titulo'   => TalaoConfig::atual()->tituloImpresso(),
                'subtitulo' => 'PEDIDO',
                'linhas'   => [
                    ...TalaoConfig::atual()->linhasCabecalho(),
                    ['texto' => $mesaTexto, 'alinhamento' => 'centro', 'tamanho' => 'grande'],
                    '------------------------------',
                    ['texto' => mb_strtoupper($descricao, 'UTF-8'), 'alinhamento' => 'centro', 'tamanho' => 'grande'],
                    '------------------------------',
                    'Hora: '.now()->format('H:i'),
                ],
                'cortar'   => true,
            ],
        ]);
    }

    private function linhasConta(Pedido $pedido): array
    {
        $operador = $pedido->operador_nome ?: ($pedido->user?->name ?: $pedido->pos?->nome);
        $total = (float) ($pedido->total ?: $pedido->total_calculado);
        $valorRecebido = (float) ($pedido->valor_recebido ?: $total);
        $troco = (float) ($pedido->troco ?: 0);
        $doacao = (float) ($pedido->doacao ?: 0);

        return [
            ...TalaoConfig::atual()->linhasCabecalho(),
            'Tipo: '.$pedido->tipo,
            ...$this->linhasMesa($pedido->mesa?->mesaPrincipal ?: $pedido->mesa, $pedido->mesa?->mesaPrincipal ? $pedido->mesa : null),
            ...($pedido->nome_reserva ? ['Reserva: '.$pedido->nome_reserva] : []),
            'Operador: '.($operador ?: 'Sem operador'),
            'Hora: '.now()->format('H:i'),
            '------------------------------',
            ...$pedido->items->map(fn ($item) => sprintf(
                '%sx %s  %s',
                $item->quantidade,
                $item->produto?->nome ?? 'Produto',
                $this->euros((float) $item->preco_unitario * (int) $item->quantidade)
            ))->all(),
            '------------------------------',
            'Total: '.$this->euros($total),
            'Recebido: '.$this->euros($valorRecebido),
            'Troco: '.$this->euros($troco),
            ...($doacao > 0 ? ['Donativo: '.$this->euros($doacao)] : []),
            'Pagamento: '.($pedido->metodo_pagamento ?: 'dinheiro'),
            '',
            ...TalaoConfig::atual()->linhasRodape(),
            ...($pedido->observacoes ? ['------------------------------', 'OBS: '.$pedido->observacoes] : []),
        ];
    }

    private function linhasTalaoBar(Pedido $pedido): array
    {
        $operador = $pedido->operador_nome ?: ($pedido->user?->name ?: $pedido->pos?->nome);
        $total = (float) ($pedido->total ?: $pedido->total_calculado);
        $valorRecebido = (float) ($pedido->valor_recebido ?: $total);
        $troco = (float) ($pedido->troco ?: 0);
        $doacao = (float) ($pedido->doacao ?: 0);

        return [
            ...TalaoConfig::atual()->linhasCabecalho(),
            'Ponto: '.($pedido->ponto_bar ?: 'Bar/Cafe'),
            'Operador: '.($operador ?: 'Sem operador'),
            'Hora: '.now()->format('H:i'),
            ...($pedido->numero_senha ? [[
                'texto' => 'SENHA #'.$pedido->numero_senha,
                'alinhamento' => 'centro',
                'tamanho' => 'grande',
            ]] : []),
            '------------------------------',
            ...$pedido->items->map(fn ($item) => sprintf(
                '%sx %s  %s',
                $item->quantidade,
                $item->produto?->nome ?? 'Produto',
                $this->euros((float) $item->preco_unitario * (int) $item->quantidade)
            ))->all(),
            '------------------------------',
            'Total: '.$this->euros($total),
            'Recebido: '.$this->euros($valorRecebido),
            'Troco: '.$this->euros($troco),
            ...($doacao > 0 ? ['Donativo: '.$this->euros($doacao)] : []),
            '',
            ...TalaoConfig::atual()->linhasRodape(),
        ];
    }

    private function euros(float $valor): string
    {
        return number_format($valor, 2, ',', ' ').' EUR';
    }

    private function linhasMesa($mesaPrincipal, $submesa): array
    {
        if (! $mesaPrincipal) {
            return [[
                'texto' => 'BALCAO',
                'alinhamento' => 'centro',
                'tamanho' => 'grande',
            ]];
        }

        return [[
            'texto' => 'MESA '.$mesaPrincipal->numero.($submesa ? $this->letraSubmesa($submesa) : ''),
            'alinhamento' => 'centro',
            'tamanho' => 'grande',
        ]];
    }

    private function letraSubmesa($submesa): string
    {
        $base = $submesa->mesaPrincipal?->numero;
        $designacao = (string) ($submesa->designacao ?? $submesa->nome ?? $submesa->numero);

        if ($base) {
            $letra = preg_replace('/^Mesa\s*'.preg_quote((string) $base, '/').'/i', '', $designacao);
            $letra = trim((string) $letra);

            if ($letra !== '') {
                return $letra;
            }
        }

        return (string) $submesa->numero;
    }
}
