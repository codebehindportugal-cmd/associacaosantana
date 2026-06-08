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

    public function criarPedido(Pedido $pedido, ?string $secao = null, string $tipo = 'pedido', ?array $items = null): ?PrintJob
    {
        $impressora = Impressora::query()
            ->where('ativa', true)
            ->when($secao, fn ($query) => $query->where('secao', $secao))
            ->orderBy('id')
            ->first();

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

    private function linhasPedido(Pedido $pedido, ?string $secao, ?array $itemsParaImprimir = null): array
    {
        $mesa = $pedido->mesa?->mesaPrincipal ?: $pedido->mesa;
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
            'Pedido #'.$pedido->id,
            'Tipo: '.$pedido->tipo,
            $mesa ? 'Mesa: '.$mesa->numero : 'Balcao',
            'Operador: '.($operador ?: 'Sem operador'),
            'Hora: '.now()->format('H:i'),
            '------------------------------',
            ...$items->map(fn ($item) => $item['quantidade'].'x '.$item['nome'].($item['observacoes'] ? ' - '.$item['observacoes'] : ''))->all(),
            '------------------------------',
        ];
    }
}
