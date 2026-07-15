<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Produto;
use App\Services\PrintJobService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FuncionarioPedidoController extends Controller
{
    public function show(string $token): Response
    {
        $pedido = $this->pedidoPorToken($token);
        $pedido->load('mesa.mesaPrincipal', 'items.produto');

        $produtos = Produto::with('categoria')
            ->disponiveisRestaurante()
            ->orderBy('nome')
            ->get()
            ->groupBy(fn (Produto $produto) => $produto->categoria?->nome ?? 'Outros')
            ->map(fn ($grupo) => $grupo->map(fn (Produto $produto) => [
                'id' => $produto->id,
                'nome' => $produto->nome,
                'descricao' => null,
                'categoria' => [
                    'nome' => $produto->categoria?->nome,
                    'secao' => $produto->categoria?->secao,
                ],
            ])->values())
            ->toArray();

        return Inertia::render('Funcionario/Mesa', [
            'token' => $token,
            'pedido' => [
                'estado' => $pedido->estado,
                'disponivel' => $this->pedidoDisponivel($pedido),
                'mesa' => $this->nomeMesa($pedido),
                'chamado_em' => $pedido->chamado_em?->toISOString(),
            ],
            'produtos' => $produtos,
            'itemsEnviados' => $this->itemsEnviados($pedido),
        ]);
    }

    public function addItems(Request $request, string $token, PrintJobService $printJobs): RedirectResponse
    {
        $pedido = $this->pedidoPorToken($token);

        if (! $this->pedidoDisponivel($pedido)) {
            return back()->withErrors(['pedido' => 'Este pedido ja nao esta disponivel para adicionar itens.']);
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.produto_id' => ['required', $this->produtoRestauranteRule()],
            'items.*.quantidade' => ['required', 'integer', 'min:1', 'max:10'],
            'items.*.observacoes' => ['nullable', 'string', 'max:255'],
        ]);

        $produtos = Produto::with('categoria')
            ->whereIn('id', collect($data['items'])->pluck('produto_id'))
            ->get()
            ->keyBy('id');

        $itemsParaImpressao = collect();

        foreach ($data['items'] as $item) {
            $produto = $produtos[(int) $item['produto_id']];
            $quantidade = (int) $item['quantidade'];
            $observacoes = trim((string) ($item['observacoes'] ?? '')) ?: null;
            $secao = $produto->categoria->secao ?? 'cozinha';

            $pedido->items()->create([
                'produto_id' => $produto->id,
                'quantidade' => $quantidade,
                'preco_unitario' => $produto->preco,
                'secao' => $secao,
                'prioridade' => false,
                'observacoes' => $observacoes,
            ]);

            $itemsParaImpressao->push([
                'quantidade' => $quantidade,
                'nome' => $produto->nome,
                'observacoes' => $observacoes,
                'secao' => $secao,
            ]);
        }

        $pedido->update(['total' => $pedido->fresh('items')->total_calculado]);

        $pedidoFresh = $pedido->fresh('mesa.mesaPrincipal', 'user', 'pos');
        $itemsParaImpressao
            ->groupBy('secao')
            ->each(function ($itensDaSecao, string $secao) use ($pedidoFresh, $printJobs) {
                $itensCombinados = collect($itensDaSecao->all())
                    ->groupBy(fn ($i) => $i['nome'].'||'.($i['observacoes'] ?? ''))
                    ->map(fn ($grupo) => [
                        'quantidade' => $grupo->sum('quantidade'),
                        'nome' => $grupo->first()['nome'],
                        'observacoes' => $grupo->first()['observacoes'],
                    ])
                    ->values()
                    ->all();
                $printJobs->criarPedido($pedidoFresh, $secao, 'pedido', $itensCombinados);
            });

        return back()->with('avisoFuncionario', 'Pedido enviado com sucesso!');
    }

    private function pedidoPorToken(string $token): Pedido
    {
        return Pedido::where('cliente_token', $token)->firstOrFail();
    }

    private function pedidoDisponivel(Pedido $pedido): bool
    {
        return in_array($pedido->estado, ['pendente', 'preparacao'], true);
    }

    private function nomeMesa(Pedido $pedido): string
    {
        $mesa = $pedido->mesa;
        $mesaPrincipal = $mesa?->mesaPrincipal ?: $mesa;

        if (! $mesaPrincipal) {
            return 'Balcao';
        }

        return $mesaPrincipal->numero.($mesa?->mesaPrincipal ? $this->letraSubmesa($mesa) : '');
    }

    private function letraSubmesa($submesa): string
    {
        $base = $submesa->mesaPrincipal?->numero;
        $designacao = (string) ($submesa->designacao ?? $submesa->nome ?? $submesa->numero);
        $letra = $base ? preg_replace('/^Mesa\s*'.preg_quote((string) $base, '/').'/i', '', $designacao) : $designacao;

        return trim((string) $letra);
    }

    private function itemsEnviados(Pedido $pedido): array
    {
        return $pedido->items
            ->sortByDesc('created_at')
            ->map(fn ($item) => [
                'id' => $item->id,
                'nome' => $item->produto?->nome ?? 'Produto',
                'quantidade' => $item->quantidade,
                'observacoes' => $item->observacoes,
                'estado' => $item->estado,
                'hora' => $item->created_at?->format('H:i'),
            ])
            ->values()
            ->toArray();
    }

    private function produtoRestauranteRule()
    {
        return Rule::exists('produtos', 'id')
            ->where('disponivel', true)
            ->where('disponivel_restaurante', true);
    }
}
