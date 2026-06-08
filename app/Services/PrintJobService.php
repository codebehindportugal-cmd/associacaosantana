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
            'nome' => 'RETIRADO: '.$item['nome'],
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

    private function impressoraParaSecao(?string $secao): ?Impressora
    {
        $query = Impressora::query()
            ->where('ativa', true)
            ->orderBy('id');

        if (! $secao) {
            return $query->first();
        }

        return $query
            ->whereIn('secao', $this->secoesEquivalentes($secao))
            ->first();
    }

    private function secoesEquivalentes(string $secao): array
    {
        return match ($secao) {
            'bebidas' => ['bebidas', 'bar'],
            'comida' => ['comida', 'cozinha'],
            'cozinha' => ['cozinha', 'comida'],
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
            ...$this->linhasIdentificacao($pedido),
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

        return [[
            'texto' => 'PEDIDO #'.$pedido->id,
            'alinhamento' => 'centro',
            'tamanho' => 'grande',
        ]];
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

        $linhas = [[
            'texto' => 'MESA '.$mesaPrincipal->numero,
            'alinhamento' => 'centro',
            'tamanho' => 'grande',
        ]];

        if ($submesa) {
            $linhas[] = [
                'texto' => 'SUBMESA '.$this->letraSubmesa($submesa),
                'alinhamento' => 'centro',
                'tamanho' => 'grande',
            ];
        }

        return $linhas;
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
