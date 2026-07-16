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

class ClientePedidoController extends Controller
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

        return Inertia::render('Cliente/Mesa', [
            'token' => $token,
            'pedido' => [
                'estado' => $pedido->estado,
                'disponivel' => $this->pedidoDisponivel($pedido),
                'mesa' => $this->nomeMesa($pedido),
            ],
            'produtos' => $produtos,
            'itemsEnviados' => $this->itemsEnviados($pedido),
        ]);
    }

    public function addItem(Request $request, string $token, PrintJobService $printJobs): RedirectResponse
    {
        $pedido = $this->pedidoPorToken($token);

        if (! $this->pedidoDisponivel($pedido)) {
            return back()->withErrors(['pedido' => 'Este pedido ja nao esta disponivel para adicionar itens.']);
        }

        $data = $request->validate([
            'produto_id' => ['required', $this->produtoRestauranteRule()],
            'quantidade' => ['required', 'integer', 'min:1', 'max:10'],
            'observacoes' => ['nullable', 'string', 'max:255'],
        ]);

        $produto = Produto::with('categoria')->findOrFail($data['produto_id']);
        $quantidade = (int) $data['quantidade'];
        $observacoes = trim((string) ($data['observacoes'] ?? '')) ?: null;
        $secao = $this->normalizarSecao($produto->categoria->secao ?? 'cozinha');

        $this->guardarItemPedido($pedido, $produto, $quantidade, $secao, $observacoes);

        $pedido->update(['total' => $pedido->fresh('items')->total_calculado]);
        $printJobs->criarItemPedido($pedido->fresh('mesa.mesaPrincipal', 'user', 'pos'), [
            'quantidade' => $quantidade,
            'nome' => $produto->nome,
            'observacoes' => $observacoes,
        ], $secao);

        $this->registarItemNaSessao($request, $token, $produto->nome, $quantidade, $observacoes);

        return to_route('cliente.confirmacao', $token);
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
            $secao = $this->normalizarSecao($produto->categoria->secao ?? 'cozinha');

            $this->guardarItemPedido($pedido, $produto, $quantidade, $secao, $observacoes);
            $this->registarItemNaSessao($request, $token, $produto->nome, $quantidade, $observacoes);

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
                // Agrupar duplicados (mesmo nome + observacoes) numa só linha
                $itensCombinados = $itensDaSecao
                    ->groupBy(fn ($i) => $i['nome'].'||'.($i['observacoes'] ?? ''))
                    ->map(fn ($grupo) => [
                        'quantidade'  => $grupo->sum('quantidade'),
                        'nome'        => $grupo->first()['nome'],
                        'observacoes' => $grupo->first()['observacoes'],
                    ])
                    ->values()
                    ->all();
                $printJobs->criarPedido($pedidoFresh, $secao, 'pedido', $itensCombinados);
            });

        return to_route('cliente.confirmacao', $token);
    }

    public function confirmacao(string $token): Response
    {
        $pedido = $this->pedidoPorToken($token);
        $pedido->load('mesa.mesaPrincipal', 'items.produto');

        return Inertia::render('Cliente/Confirmacao', [
            'token' => $token,
            'pedido' => [
                'estado' => $pedido->estado,
                'disponivel' => $this->pedidoDisponivel($pedido),
                'mesa' => $this->nomeMesa($pedido),
            ],
            'items' => $this->itemsEnviados($pedido),
        ]);
    }

    public function showChamar(string $token): Response
    {
        $pedido = $this->pedidoPorToken($token);
        $pedido->load('mesa.mesaPrincipal');

        return Inertia::render('Cliente/Chamar', [
            'token'  => $token,
            'pedido' => [
                'disponivel' => $this->pedidoDisponivel($pedido),
                'mesa'       => $this->nomeMesa($pedido),
            ],
        ]);
    }

    public function chamarFuncionario(string $token, PrintJobService $printJobs): RedirectResponse
    {
        $pedido = $this->pedidoPorToken($token);
        $pedido->load('mesa.mesaPrincipal', 'user', 'pos');

        if ($this->pedidoDisponivel($pedido)) {
            // Gravar timestamp para notificação em tempo real no dispositivo do funcionário
            $pedido->update(['chamado_em' => now()]);

            // Imprimir ticket com nome do funcionário responsável
            $operador = $pedido->operador_nome ?: ($pedido->user?->name ?: $pedido->pos?->nome);
            $descricao = $operador ? "CHAMAR: $operador" : 'CHAMAR FUNCIONARIO';
            $printJobs->criarPedidoExtra($pedido->mesa, $descricao);
        }

        return back()->with('avisoCliente', 'Funcionário chamado! Aguarde um momento.');
    }

    public function confirmarChamada(string $token): \Illuminate\Http\JsonResponse
    {
        $pedido = $this->pedidoPorToken($token);
        $pedido->update(['chamado_em' => null]);

        return response()->json(['ok' => true]);
    }

    /**
     * Chamadas de cliente pendentes para o POS da sessão atual.
     * Usado pelo alerta global (banner + som) em qualquer ecrã POS.
     */
    public function chamadasPos(): \Illuminate\Http\JsonResponse
    {
        $posId = session('pos_id');

        $chamadas = Pedido::query()
            ->whereNotNull('chamado_em')
            ->where(fn ($query) => $query->where('pos_id', $posId)->orWhereNull('pos_id'))
            ->with('mesa.mesaPrincipal')
            ->orderBy('chamado_em')
            ->get()
            ->map(fn (Pedido $pedido) => [
                'id' => $pedido->id,
                'mesa' => $this->nomeMesa($pedido),
                'ha_quanto' => $pedido->chamado_em->diffForHumans(),
            ]);

        return response()->json(['chamadas' => $chamadas]);
    }

    /**
     * Funcionário confirma no POS que atendeu a chamada.
     */
    public function confirmarChamadaPos(Pedido $pedido): \Illuminate\Http\JsonResponse
    {
        abort_unless(
            $pedido->pos_id === null || (int) $pedido->pos_id === (int) session('pos_id'),
            403
        );

        $pedido->update(['chamado_em' => null]);

        return response()->json(['ok' => true]);
    }

    public function estado(string $token): \Illuminate\Http\JsonResponse
    {
        $pedido = $this->pedidoPorToken($token);

        return response()->json([
            'chamado_em' => $pedido->chamado_em?->toISOString(),
            'estado'     => $pedido->estado,
        ]);
    }

    private function pedidoPorToken(string $token): Pedido
    {
        return Pedido::where('cliente_token', $token)->firstOrFail();
    }

    private function pedidoDisponivel(Pedido $pedido): bool
    {
        return in_array($pedido->estado, ['pendente', 'preparacao'], true);
    }

    private function guardarItemPedido(Pedido $pedido, Produto $produto, int $quantidade, string $secao, ?string $observacoes): void
    {
        $pedido->items()->create([
            'produto_id' => $produto->id,
            'quantidade' => $quantidade,
            'preco_unitario' => $produto->preco,
            'secao' => $secao,
            'prioridade' => false,
            'observacoes' => $observacoes,
        ]);
    }

    private function registarItemNaSessao(Request $request, string $token, string $nome, int $quantidade, ?string $observacoes): void
    {
        $key = $this->sessionKey($token);
        $items = $request->session()->get($key, []);
        $items[] = [
            'nome' => $nome,
            'quantidade' => $quantidade,
            'observacoes' => $observacoes,
            'hora' => now()->format('H:i'),
        ];

        $request->session()->put($key, $items);
    }

    private function sessionKey(string $token): string
    {
        return 'cliente_pedido_items_'.$token;
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

    private function normalizarSecao(string $secao): string
    {
        return $secao;
    }

    private function produtoRestauranteRule()
    {
        return Rule::exists('produtos', 'id')
            ->where('disponivel', true)
            ->where('disponivel_restaurante', true);
    }
}
