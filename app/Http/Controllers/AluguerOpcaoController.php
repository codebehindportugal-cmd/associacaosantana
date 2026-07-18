<?php

namespace App\Http\Controllers;

use App\Models\AluguerOpcao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AluguerOpcaoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome'        => ['required', 'string', 'max:255'],
            'descricao'   => ['nullable', 'string', 'max:500'],
            'preco_extra' => ['nullable', 'numeric', 'min:0'],
            'ordem'       => ['nullable', 'integer', 'min:0'],
        ]);

        AluguerOpcao::create([
            'nome'        => $data['nome'],
            'descricao'   => $data['descricao'] ?? null,
            'preco_extra' => $data['preco_extra'] ?? 0,
            'ativo'       => true,
            'ordem'       => $data['ordem'] ?? (AluguerOpcao::max('ordem') + 1),
        ]);

        return back()->with('success', 'Opção criada.');
    }

    public function update(Request $request, AluguerOpcao $opcao): RedirectResponse
    {
        $data = $request->validate([
            'nome'        => ['required', 'string', 'max:255'],
            'descricao'   => ['nullable', 'string', 'max:500'],
            'preco_extra' => ['nullable', 'numeric', 'min:0'],
            'ativo'       => ['boolean'],
            'ordem'       => ['nullable', 'integer', 'min:0'],
        ]);

        $opcao->update($data);

        return back()->with('success', 'Opção atualizada.');
    }

    public function destroy(AluguerOpcao $opcao): RedirectResponse
    {
        $opcao->delete();

        return back()->with('success', 'Opção eliminada.');
    }
}
