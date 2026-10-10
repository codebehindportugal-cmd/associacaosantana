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
            'offline' => $request->boolean('offline'),
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
            'offline' => $request->boolean('offline'),
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
            // Impressora da cozinha: so no bar/cafe com internet, e tem de ser de rede/USB (pelo agente)
            'impressora_preparacao_id' => ['nullable', 'exists:impressoras,id', function ($atributo, $valor, $falhar) {
                if (blank($valor)) {
                    return;
                }
                if (! in_array(request('tipo'), ['bar', 'cafe'], true)) {
                    $falhar('So os postos do bar/cafe mandam pedidos para a cozinha.');
                } elseif (request()->boolean('offline')) {
                    $falhar('Um posto que trabalha sem internet nao consegue mandar pedidos para a cozinha.');
                } elseif (! \App\Models\Impressora::find($valor)?->usaAgente()) {
                    $falhar('A impressora da cozinha tem de ser de rede ou USB no agente (Raspberry), nao WebUSB nem navegador.');
                } elseif ((int) $valor === (int) request('impressora_id')) {
                    $falhar('A impressora da cozinha tem de ser diferente da impressora das senhas.');
                }
            }],
            // Trabalhar sem internet: so no bar/cafe, com uma letra unica nas senhas
            'offline' => ['nullable', 'boolean', function ($atributo, $valor, $falhar) {
                if (! filter_var($valor, FILTER_VALIDATE_BOOLEAN)) {
                    return;
                }
                if (! in_array(request('tipo'), ['bar', 'cafe'], true)) {
                    $falhar('So os postos do bar/cafe podem trabalhar sem internet.');
                } elseif (\App\Models\Impressora::find(request('impressora_id'))?->tipo !== \App\Models\Impressora::TIPO_WEBUSB) {
                    $falhar('Para trabalhar sem internet, o posto tem de ter uma impressora "USB pelo browser (WebUSB)".');
                }
            }],
            'prefixo_senha' => [
                Rule::requiredIf(fn () => request()->boolean('offline')),
                'nullable', 'string', 'max:3', 'regex:/^[A-Z]{1,3}$/',
                Rule::unique('pos_sessions', 'prefixo_senha')->ignore(request()->route('terminal')?->id),
            ],
        ];
    }
}
