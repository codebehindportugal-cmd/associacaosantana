<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsurePosSession;
use App\Models\PosSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosLoginController extends Controller
{
    public function show(Request $request): Response
    {
        $tipo = $request->query('tipo');
        $ordem = ['restaurante', 'reservas', 'bar', 'cafe', 'cotas'];

        return Inertia::render('Pos/Login', [
            'terminais' => PosSession::where('ativo', true)
                ->when(in_array($tipo, $ordem, true), fn ($query) => $query->where('tipo', $tipo))
                ->orderBy('nome')
                ->get(['id', 'nome', 'localizacao', 'tipo'])
                ->sortBy(fn ($terminal) => array_search($terminal->tipo, $ordem, true))
                ->values(),
            'tipoSelecionado' => $tipo,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'terminal_id' => ['required', 'exists:pos_sessions,id'],
            'operador_nome' => ['required', 'string', 'max:255'],
            'pin' => ['required', 'string'],
        ]);

        $terminal = PosSession::where('ativo', true)->findOrFail($data['terminal_id']);

        if (! $terminal->validarPin($data['pin'])) {
            return back()->withErrors(['pin' => 'PIN invalido.'])->onlyInput('terminal_id');
        }

        session([
            'pos_id'          => $terminal->id,
            'pos_nome'        => $terminal->nome,
            'pos_tipo'        => $terminal->tipo,
            'pos_localizacao' => $terminal->localizacao,
            'pos_operador'    => $data['operador_nome'],
        ]);

        // Cookie persistente de 30 dias — mantém sessão mesmo que o PHP expire
        cookie()->queue(EnsurePosSession::criarCookie($terminal->id, $data['operador_nome']));

        return redirect($this->urlPorTipo($terminal->tipo));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['pos_id', 'pos_nome', 'pos_tipo', 'pos_localizacao', 'pos_operador']);

        // Apaga o cookie persistente
        cookie()->queue(EnsurePosSession::apagarCookie());

        return redirect()->route('pos.login');
    }

    private function urlPorTipo(string $tipo): string
    {
        return match ($tipo) {
            'restaurante' => '/pos-rest',
            'reservas' => '/pos-reservas',
            'cotas' => '/pos-cotas',
            default => '/pos',
        };
    }
}
