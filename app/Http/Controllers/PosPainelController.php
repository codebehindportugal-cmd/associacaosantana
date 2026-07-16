<?php

namespace App\Http\Controllers;

use App\Models\ChamadaComissao;
use App\Models\Configuracao;
use App\Models\Pedido;
use App\Models\PosSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel da comissão no back-office: estado de todos os POS,
 * chamadas pendentes e gestão do PIN da comissão.
 */
class PosPainelController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:pos.comissao');
    }

    public function index(): Response
    {
        $ordem = ['restaurante', 'reservas', 'bar', 'cafe', 'cotas'];

        return Inertia::render('PosPainel/Index', [
            'terminais' => PosSession::query()
                ->orderBy('nome')
                ->get(['id', 'nome', 'localizacao', 'tipo', 'ativo', 'ultimo_operador', 'ultimo_login_em'])
                ->sortBy(fn ($terminal) => array_search($terminal->tipo, $ordem, true))
                ->values()
                ->map(fn (PosSession $terminal) => [
                    'id' => $terminal->id,
                    'nome' => $terminal->nome,
                    'localizacao' => $terminal->localizacao,
                    'tipo' => $terminal->tipo,
                    'ativo' => $terminal->ativo,
                    'ultimo_operador' => $terminal->ultimo_operador,
                    'ultimo_login' => $terminal->ultimo_login_em?->diffForHumans(),
                ]),
            'chamadasComissao' => ChamadaComissao::pendentes()
                ->orderBy('created_at')
                ->get()
                ->map(fn (ChamadaComissao $c) => [
                    'id' => $c->id,
                    'operador_nome' => $c->operador_nome,
                    'local' => $c->local,
                    'criado_em' => $c->created_at->diffForHumans(),
                ]),
            'chamadasCliente' => Pedido::query()
                ->whereNotNull('chamado_em')
                ->with('mesa.mesaPrincipal', 'pos')
                ->orderBy('chamado_em')
                ->get()
                ->map(fn (Pedido $pedido) => [
                    'id' => $pedido->id,
                    'mesa' => $pedido->mesa?->mesaPrincipal?->nome ?? $pedido->mesa?->nome ?? '—',
                    'pos' => $pedido->pos?->nome,
                    'ha_quanto' => $pedido->chamado_em->diffForHumans(),
                ]),
        ]);
    }

    public function atualizarPin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'digits_between:4,8'],
        ]);

        Configuracao::updateOrCreate(
            ['chave' => 'comissao_pin'],
            ['valor' => Hash::make($data['pin']), 'descricao' => 'PIN de acesso da comissao no login POS']
        );

        return back()->with('success', 'PIN da comissão atualizado.');
    }
}
