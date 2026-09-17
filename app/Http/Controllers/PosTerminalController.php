<?php

namespace App\Http\Controllers;

use App\Models\PosSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Postos POS: terminais com PIN proprio e impressora propria.
 *
 * Existe para se poder abrir mais um ponto de venda durante o evento — um
 * telemovel a ajudar num pico de afluencia, por exemplo — sem ir a base de
 * dados. Cada posto aponta para a sua impressora, porque qualquer posto pode
 * vender qualquer produto e o talao tem de sair onde o cliente esta.
 */
class PosTerminalController extends Controller
{
    private const TIPOS = ['restaurante', 'reservas', 'bar', 'cafe', 'cotas'];

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate($this->regras(true));

        PosSession::create($dados + [
            'ativo' => $request->boolean('ativo', true),
            'impressao_navegador' => $request->boolean('impressao_navegador'),
        ]);

        return back()->with('success', 'Posto criado.');
    }

    public function update(Request $request, PosSession $terminal): RedirectResponse
    {
        $dados = $request->validate($this->regras(false));

        // Sem PIN novo, mantem o que la esta
        if (blank($dados['pin'] ?? null)) {
            unset($dados['pin']);
        }

        $terminal->update($dados + [
            'ativo' => $request->boolean('ativo', true),
            'impressao_navegador' => $request->boolean('impressao_navegador'),
        ]);

        return back()->with('success', 'Posto atualizado.');
    }

    /**
     * Atalho para trocar so a impressora, direto na lista.
     */
    public function impressora(Request $request, PosSession $terminal): RedirectResponse
    {
        $dados = $request->validate([
            'impressora_id' => ['nullable', 'exists:impressoras,id'],
        ]);

        $terminal->update(['impressora_id' => $dados['impressora_id'] ?? null]);

        return back()->with('success', 'Impressora do posto '.$terminal->nome.' atualizada.');
    }

    public function destroy(PosSession $terminal): RedirectResponse
    {
        // Os pedidos guardam o pos_id; desativar preserva o historico das vendas
        $terminal->update(['ativo' => false]);

        return back()->with('success', 'Posto '.$terminal->nome.' desativado.');
    }

    private function regras(bool $novo): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', Rule::in(self::TIPOS)],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'pin' => [$novo ? 'required' : 'nullable', 'string', 'min:4', 'max:12'],
            'impressora_id' => ['nullable', 'exists:impressoras,id'],
            'impressao_navegador' => ['nullable', 'boolean'],
        ];
    }
}
