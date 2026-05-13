<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\CaixaDiaria;
use App\Models\Pedido;
use App\Models\Produto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BarController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:bar.ver')->only(['index', 'show', 'talao']);
        $this->middleware('permission:bar.vender')->only(['novaContaBar', 'novoPrepago', 'storeContaBar', 'storePrepago', 'fecharContaBar']);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Bar/Index', [
            'pedidos' => Pedido::with('items.produto.categoria')
                ->whereIn('tipo', ['bar_conta', 'bar_prepago'])
                ->whereDate('created_at', today())
                ->when($request->tipo, fn ($query, $tipo) => $query->where('tipo', $tipo))
                ->when($request->estado, fn ($query, $estado) => $query->where('estado', $estado))
                ->latest()
                ->get(),
            'caixas' => CaixaDiaria::whereDate('data', today())
                ->get(['ponto', 'fundo_maneio', 'estado', 'created_at']),
            'filters' => $request->only('tipo', 'estado'),
        ]);
    }

    public function novaContaBar(): Response
    {
        return Inertia::render('Bar/NovaContaBar', ['produtos' => $this->produtos()]);
    }

    public function novoPrepago(): Response
    {
        return Inertia::render('Bar/NovoPrepago', ['produtos' => $this->produtos()]);
    }

    public function storePrepago(Request $request): RedirectResponse
    {
        $data = $this->validarPedidoBar($request, true);

        return DB::transaction(function () use ($data, $request) {
            if (! $this->caixaAberta($data['ponto_bar'])) {
                return back()->withErrors(['ponto_bar' => 'Abre a caixa deste ponto antes de vender.']);
            }

            $senha = $this->proximaSenhaBar();
            $total = $this->totalDosItens($data['items']);
            $valorRecebido = round((float) $data['valor_recebido'], 2);
            $troco = round(max(0, $valorRecebido - $total), 2);

            if ($valorRecebido < $total) {
                return back()->withErrors(['valor_recebido' => 'O valor recebido nao pode ser inferior ao total.']);
            }

            $pedido = Pedido::create([
                'user_id' => $request->user()->id,
                'tipo' => 'bar_prepago',
                'estado' => 'pronto',
                'numero_senha' => $senha,
                'pago_antecipado' => true,
                'ponto_bar' => $data['ponto_bar'],
                'total' => $total,
                'valor_recebido' => $valorRecebido,
                'troco' => $troco,
                'doacao' => 0,
            ]);

            $this->criarItems($pedido, $data['items']);

            return to_route('bar.talao', $pedido)->with('success', 'Senha emitida.');
        });
    }

    public function storeContaBar(Request $request): RedirectResponse
    {
        $data = $this->validarPedidoBar($request);

        if (! $this->caixaAberta($data['ponto_bar'])) {
            return back()->withErrors(['ponto_bar' => 'Abre a caixa deste ponto antes de vender.']);
        }

        $total = $this->totalDosItens($data['items']);

        $pedido = Pedido::create([
            'user_id' => $request->user()->id,
            'tipo' => 'bar_conta',
            'estado' => 'pendente',
            'ponto_bar' => $data['ponto_bar'],
            'total' => $total,
            'observacoes' => $data['observacoes'] ?? null,
        ]);

        $this->criarItems($pedido, $data['items']);

        return to_route('bar.show', $pedido)->with('success', 'Conta de bar aberta.');
    }

    public function show(Pedido $pedido): Response
    {
        abort_unless(in_array($pedido->tipo, ['bar_conta', 'bar_prepago'], true), 404);

        return Inertia::render('Bar/Show', [
            'pedido' => $pedido->load('items.produto.categoria'),
            'produtos' => $this->produtos(),
        ]);
    }

    public function fecharContaBar(Request $request, Pedido $pedido): RedirectResponse
    {
        abort_unless($pedido->tipo === 'bar_conta', 404);

        $data = $request->validate([
            'valor_recebido' => ['nullable', 'numeric', 'min:0'],
            'troco' => ['nullable', 'numeric', 'min:0'],
        ]);

        $total = round($pedido->fresh('items')->total_calculado, 2);
        $valorRecebido = round((float) ($data['valor_recebido'] ?? $total), 2);
        $troco = round((float) ($data['troco'] ?? 0), 2);
        $excedente = round($valorRecebido - $total, 2);

        if ($valorRecebido < $total) {
            return back()->withErrors(['valor_recebido' => 'O valor recebido nao pode ser inferior ao total.']);
        }

        if ($troco > $excedente) {
            return back()->withErrors(['troco' => 'O troco nao pode ser superior ao valor a devolver.']);
        }

        $pedido->update([
            'estado' => 'entregue',
            'total' => $total,
            'valor_recebido' => $valorRecebido,
            'troco' => $troco,
            'doacao' => max(0, round($excedente - $troco, 2)),
        ]);

        return to_route('bar.talao', $pedido)->with('success', 'Conta fechada.');
    }

    public function talao(Pedido $pedido): Response
    {
        abort_unless(in_array($pedido->tipo, ['bar_conta', 'bar_prepago'], true), 404);

        return Inertia::render('Bar/TalaoSenha', [
            'pedido' => $pedido->load('items.produto.categoria', 'user'),
        ]);
    }

    private function validarPedidoBar(Request $request, bool $prepago = false): array
    {
        return $request->validate([
            'observacoes' => ['nullable', 'string', 'max:255'],
            'ponto_bar' => ['required', 'string', 'max:80'],
            'valor_recebido' => [$prepago ? 'required' : 'nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produto_id' => ['required', 'exists:produtos,id'],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
        ]);
    }

    private function totalDosItens(array $items): float
    {
        $produtos = Produto::whereIn('id', collect($items)->pluck('produto_id'))->get()->keyBy('id');

        return round(collect($items)->sum(fn ($item) => (float) $produtos[$item['produto_id']]->preco * (int) $item['quantidade']), 2);
    }

    private function criarItems(Pedido $pedido, array $items): void
    {
        $produtos = Produto::with('categoria')->whereIn('id', collect($items)->pluck('produto_id'))->get()->keyBy('id');

        foreach ($items as $item) {
            $produto = $produtos[$item['produto_id']];
            $pedido->items()->create([
                'produto_id' => $produto->id,
                'quantidade' => $item['quantidade'],
                'preco_unitario' => $produto->preco,
                'secao' => $produto->categoria->secao,
            ]);
        }
    }

    private function proximaSenhaBar(): int
    {
        $config = Configuracao::where('chave', 'ultima_senha_bar')->lockForUpdate()->firstOrCreate(
            ['chave' => 'ultima_senha_bar'],
            ['valor' => '0', 'descricao' => 'Ultima senha diaria emitida no bar']
        );

        $senha = ((int) $config->valor) + 1;
        $config->update(['valor' => (string) $senha]);

        return $senha;
    }

    private function caixaAberta(string $ponto): bool
    {
        return CaixaDiaria::whereDate('data', today())
            ->where('ponto', $ponto)
            ->where('estado', 'aberta')
            ->exists();
    }

    private function produtos()
    {
        return Produto::with('categoria')
            ->whereHas('categoria', fn ($query) => $query->where('secao', 'bebidas'))
            ->disponiveis()
            ->orderBy('nome')
            ->get();
    }
}
