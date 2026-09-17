<?php

namespace App\Http\Controllers;

use App\Models\AssociacaoParceira;
use App\Models\Configuracao;
use App\Support\ReceitaFesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pagina read-only para as associacoes que partilham o evento.
 * Sem login: o acesso e feito por token no URL. Mostra apenas receita
 * bruta e a divisao acordada, nunca custos internos da associacao.
 */
class ContasPartilhadasController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $esperado = Configuracao::where('chave', AssociacaoParceiraController::CHAVE_TOKEN)->value('valor');

        if (! $esperado || ! hash_equals((string) $esperado, $token)) {
            Log::warning('Acesso invalido as contas partilhadas', ['ip' => $request->ip()]);
            abort(404);
        }

        $inicio = $request->date('data_inicio') ?? now()->startOfYear();
        $fim = $request->date('data_fim') ?? now()->endOfYear();

        $receitas = ReceitaFesta::linhas($inicio->toDateString(), $fim->toDateString());
        $divisao = AssociacaoParceira::divisao((float) $receitas->sum('valor'));

        return Inertia::render('ContasPartilhadas/Show', [
            'filters' => [
                'data_inicio' => $inicio->toDateString(),
                'data_fim' => $fim->toDateString(),
            ],
            'receitas' => $receitas,
            'divisao' => $divisao,
            'atualizadoEm' => now()->format('d/m/Y H:i'),
        ]);
    }
}
