<?php

namespace App\Http\Controllers;

use App\Models\PosSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosLoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Pos/Login', [
            'terminais' => PosSession::where('ativo', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'localizacao', 'tipo'])
                ->sortBy(fn ($terminal) => array_search($terminal->tipo, ['bar', 'restaurante', 'cotas'], true))
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'terminal_id' => ['required', 'exists:pos_sessions,id'],
            'pin' => ['required', 'string'],
        ]);

        $terminal = PosSession::where('ativo', true)->findOrFail($data['terminal_id']);

        if (! $terminal->validarPin($data['pin'])) {
            return back()->withErrors(['pin' => 'PIN invalido.'])->onlyInput('terminal_id');
        }

        session([
            'pos_id' => $terminal->id,
            'pos_nome' => $terminal->nome,
            'pos_tipo' => $terminal->tipo,
            'pos_localizacao' => $terminal->localizacao,
        ]);

        return redirect($this->urlPorTipo($terminal->tipo));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['pos_id', 'pos_nome', 'pos_tipo', 'pos_localizacao']);

        return redirect()->route('pos.login');
    }

    private function urlPorTipo(string $tipo): string
    {
        return match ($tipo) {
            'restaurante' => '/pos-rest',
            'cotas' => '/pos-cotas',
            default => '/pos',
        };
    }
}
