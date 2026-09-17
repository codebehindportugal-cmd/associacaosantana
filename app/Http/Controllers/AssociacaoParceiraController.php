<?php

namespace App\Http\Controllers;

use App\Models\AssociacaoParceira;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssociacaoParceiraController extends Controller
{
    public const CHAVE_TOKEN = 'contas_partilhadas_token';

    public function __construct()
    {
        $this->middleware('permission:relatorios.ver');
    }

    public function store(Request $request): RedirectResponse
    {
        AssociacaoParceira::create($this->validated($request));

        return back()->with('success', 'Associacao adicionada.');
    }

    public function update(Request $request, AssociacaoParceira $associacao): RedirectResponse
    {
        $associacao->update($this->validated($request));

        return back()->with('success', 'Associacao atualizada.');
    }

    public function destroy(AssociacaoParceira $associacao): RedirectResponse
    {
        $associacao->delete();

        return back()->with('success', 'Associacao removida.');
    }

    /**
     * Divide 100% em partes iguais pelas associacoes ativas.
     * O resto dos centesimos fica na primeira, para somar exactamente 100.
     */
    public function igualarPercentagens(): RedirectResponse
    {
        $associacoes = AssociacaoParceira::ativas();
        $total = $associacoes->count();

        if ($total === 0) {
            return back()->withErrors(['percentagem' => 'Ainda nao ha associacoes ativas.']);
        }

        $base = floor(10000 / $total) / 100;
        $resto = round(100 - ($base * $total), 2);

        foreach ($associacoes as $indice => $associacao) {
            $associacao->update([
                'percentagem' => $indice === 0 ? $base + $resto : $base,
            ]);
        }

        return back()->with('success', 'Percentagens igualadas.');
    }

    /**
     * Gera (ou renova) o token do link partilhado.
     * Renovar invalida o link anterior que ja foi distribuido.
     */
    public function gerarLink(): RedirectResponse
    {
        DB::table('configuracoes')->updateOrInsert(
            ['chave' => self::CHAVE_TOKEN],
            [
                'valor' => Str::random(40),
                'descricao' => 'Token do link read-only das contas partilhadas do evento',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'Link partilhado gerado.');
    }

    public function apagarLink(): RedirectResponse
    {
        DB::table('configuracoes')->where('chave', self::CHAVE_TOKEN)->delete();

        return back()->with('success', 'Link partilhado desativado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'sigla' => ['nullable', 'string', 'max:40'],
            'percentagem' => ['required', 'numeric', 'min:0', 'max:100'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notas' => ['nullable', 'string'],
            'ativo' => ['nullable', 'boolean'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
