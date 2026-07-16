<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsurePosSession;
use App\Models\Configuracao;
use App\Models\PosSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PosLoginController extends Controller
{
    public function show(Request $request): Response
    {
        $tipo = $request->query('tipo');
        $ordem = ['restaurante', 'reservas', 'bar', 'cafe', 'cotas'];
        $comissao = (bool) session('pos_comissao', false);

        return Inertia::render('Pos/Login', [
            // Em modo comissão mostra sempre todos os terminais
            'terminais' => PosSession::where('ativo', true)
                ->when(! $comissao && in_array($tipo, $ordem, true), fn ($query) => $query->where('tipo', $tipo))
                ->orderBy('nome')
                ->get(['id', 'nome', 'localizacao', 'tipo', 'ultimo_operador', 'ultimo_login_em'])
                ->sortBy(fn ($terminal) => array_search($terminal->tipo, $ordem, true))
                ->values(),
            'tipoSelecionado' => $tipo,
            'comissao' => $comissao,
            'comissaoNome' => session('pos_comissao_nome', ''),
            'salaEcraCodigo' => config('app.sala_ecra_codigo'),
        ]);
    }

    /**
     * Valida o PIN da comissão e ativa o modo comissão no login POS.
     */
    public function comissaoStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:100'],
            'pin' => ['required', 'string'],
        ]);

        $hash = Configuracao::where('chave', 'comissao_pin')->value('valor');

        if (! $hash || ! Hash::check($data['pin'], $hash)) {
            return back()->withErrors(['comissao_pin' => 'PIN da comissão inválido.']);
        }

        session([
            'pos_comissao' => true,
            'pos_comissao_nome' => $data['nome'],
        ]);

        return back();
    }

    public function comissaoDestroy(): RedirectResponse
    {
        session()->forget(['pos_comissao', 'pos_comissao_nome']);

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $comissao = (bool) session('pos_comissao', false);

        $data = $request->validate([
            'terminal_id' => ['required', 'exists:pos_sessions,id'],
            'operador_nome' => ['required', 'string', 'max:255'],
            'pin' => [$comissao ? 'nullable' : 'required', 'string'],
        ]);

        $terminal = PosSession::where('ativo', true)->findOrFail($data['terminal_id']);

        // Membros da comissão validados entram sem PIN do terminal
        if (! $comissao && ! $terminal->validarPin($data['pin'])) {
            return back()->withErrors(['pin' => 'PIN invalido.'])->onlyInput('terminal_id');
        }

        $terminal->forceFill([
            'ultimo_operador' => $data['operador_nome'],
            'ultimo_login_em' => now(),
        ])->save();

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
