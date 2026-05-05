<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
use App\Models\Configuracao;
use App\Models\Pedido;
use App\Models\Produto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PosBarController extends Controller
{
    public function index(): Response
    {
        $ponto = session('pos_localizacao') ?: session('pos_nome');

        return Inertia::render('Pos/Index', [
            'posNome' => session('pos_nome'),
            'pontoBar' => $ponto,
            'caixaAberta' => $this->caixaAberta($ponto),
            'produtos' => Produto::with('categoria')
                ->whereHas('categoria', fn ($query) => $query->where('secao', 'bebidas'))
                ->disponiveis()
                ->orderBy('nome')
                ->get(),
            'senhasHoje' => Pedido::where('ponto_bar', $ponto)
                ->where('tipo', 'bar_prepago')
                ->whereDate('created_at', today())
                ->with('items.produto')
                ->latest()
                ->limit(12)
                ->get(),
        ]);
    }

    public function storePrepago(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'valor_recebido' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.produto_id' => ['required', 'exists:produtos,id'],
            'items.*.quantidade' => ['required', 'integer', 'min:1'],
        ]);

        $ponto = session('pos_localizacao') ?: session('pos_nome');

        if (! $this->caixaAberta($ponto)) {
            return back()->withErrors(['ponto_bar' => 'Abre a caixa deste ponto no backoffice antes de vender.']);
        }

        return DB::transaction(function () use ($data, $ponto) {
            $produtos = Produto::with('categoria')->whereIn('id', collect($data['items'])->pluck('produto_id'))->get()->keyBy('id');
            $total = round(collect($data['items'])->sum(fn ($item) => (float) $produtos[$item['produto_id']]->preco * (int) $item['quantidade']), 2);
            $valorRecebido = round((float) $data['valor_recebido'], 2);

            if ($valorRecebido < $total) {
                return back()->withErrors(['valor_recebido' => 'O valor recebido nao pode ser inferior ao total.']);
            }

            $pedido = Pedido::create([
                'pos_id' => session('pos_id'),
                'tipo' => 'bar_prepago',
                'estado' => 'pronto',
                'numero_senha' => $this->proximaSenhaBar(),
                'pago_antecipado' => true,
                'ponto_bar' => $ponto,
                'total' => $total,
                'valor_recebido' => $valorRecebido,
                'troco' => round(max(0, $valorRecebido - $total), 2),
                'doacao' => 0,
                'metodo_pagamento' => 'dinheiro',
            ]);

            foreach ($data['items'] as $item) {
                $produto = $produtos[$item['produto_id']];
                $pedido->items()->create([
                    'produto_id' => $produto->id,
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $produto->preco,
                    'secao' => $produto->categoria->secao,
                ]);
            }

            return to_route('pos.pedido.talao', $pedido);
        });
    }

    public function talao(Pedido $pedido): Response
    {
        abort_unless($pedido->tipo === 'bar_prepago' && (int) $pedido->pos_id === (int) session('pos_id'), 404);

        return Inertia::render('Pos/TalaoSenha', [
            'pedido' => $pedido->load('items.produto.categoria'),
        ]);
    }

    private function caixaAberta(string $ponto): bool
    {
        return CaixaDiaria::whereDate('data', today())
            ->where('ponto', $ponto)
            ->where('estado', 'aberta')
            ->exists();
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
}
