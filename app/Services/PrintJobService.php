<?php

namespace App\Services;

use App\Models\Impressora;
use App\Models\Pedido;
use App\Models\PrintJob;

class PrintJobService
{
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
                'titulo' => 'ARDC Santana',
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
                'titulo' => 'ARDC Santana',
                'subtitulo' => 'CONTA',
                'linhas' => $this->linhasConta($pedido),
                'cortar' => true,
            ],
        ]);
    }

    public function criarTalaoBar(Pedido $pedido, string $secao = 'bar'): ?PrintJob
    {
        $impressora = $this->impressoraParaSecao($secao);

        if (! $impressora) {
            return null;
        }

        $pedido->loadMissing('user', 'pos', 'items.produto.categoria');

        return PrintJob::create([
            'impressora_id' => $impressora->id,
            'printable_type' => $pedido::class,
            'printable_id' => $pedido->id,
            'tipo' => 'talao_bar',
            'payload' => [
                'titulo' => 'ARDC Santana',
                'subtitulo' => 'SENHA',
                'linhas' => $this->linhasTalaoBar($pedido),
                'cortar' => true,
            ],
        ]);
    }

    private function impressoraParaSecao(?string $secao): ?Impressora
    {
        $query = Impressora::query()
            ->where('ativa', true)
            ->orderBy('id');

        if (! $secao) {
            return $query->first();
        }

        $impressora = (clone $query)
            ->where('secao', $secao)
            ->first();

        if ($impressora) {
            return $impressora;
        }

        return (clone $query)
            ->whereIn('secao', $this->secoesEquivalentes($secao))
            ->first();
    }

    private function secoesEquivalentes(string $secao): array
    {
        return match ($secao) {
            'bebidas' => ['bebidas', 'bar', 'cafe'],
            'bar' => ['bar', 'bebidas', 'cafe'],
            'cafe' => ['cafe', 'bar', 'bebidas'],
            'comida' => ['comida', 'cozinha'],
            'cozinha' => ['cozinha', 'comida'],
            'contas' => ['contas', 'pos', 'caixa'],
            'pos' => ['pos', 'contas', 'caixa'],
            default => [$secao],
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
                ]);

        $items = $items->values();

        return [
            'Tipo: '.$pedido->tipo,
            ...$this->linhasMesa($mesaPrincipal, $submesa),
            'Operador: '.($operador ?: 'Sem operador'),
            'Hora: '.now()->format('H:i'),
            '------------------------------',
            ...$items->map(fn ($item) => [
                'texto' => $item['quantidade'].'x '.$item['nome'].($item['observacoes'] ? ' - '.$item['observacoes'] : ''),
                'alinhamento' => 'centro',
                'tamanho' => 'grande',
            ])->all(),
            '------------------------------',
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

    private function linhasConta(Pedido $pedido): array
    {
        $operador = $pedido->operador_nome ?: ($pedido->user?->name ?: $pedido->pos?->nome);
        $total = (float) ($pedido->total ?: $pedido->total_calculado);
        $valorRecebido = (float) ($pedido->valor_recebido ?: $total);
        $troco = (float) ($pedido->troco ?: 0);
        $doacao = (float) ($pedido->doacao ?: 0);

        return [
            'Tipo: '.$pedido->tipo,
            ...$this->linhasMesa($pedido->mesa?->mesaPrincipal ?: $pedido->mesa, $pedido->mesa?->mesaPrincipal ? $pedido->mesa : null),
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
            'Este documento nao serve de fatura',
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
            'Este documento nao serve de fatura',
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
