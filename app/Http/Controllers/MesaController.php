<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MesaController extends Controller
{
    public function index(): View
    {
        $mesas = Mesa::orderBy('numero')->get();

        return view('mesas.index', compact('mesas')); // view pode renderizar um mapa visual
    }

    public function create(): View
    {
        return view('mesas.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'numero' => 'required|integer|unique:mesas,numero',
            'capacidade' => 'required|integer|min:1',
            'localizacao' => 'required|in:interior,exterior,bar',
            'estado' => 'required|in:livre,ocupada,reservada',
        ]);

        Mesa::create($request->only(['numero', 'capacidade', 'localizacao', 'estado']));

        return redirect()->route('mesas.index')->with('success', 'Mesa criada com sucesso.');
    }

    public function edit(Mesa $mesa): View
    {
        return view('mesas.edit', compact('mesa'));
    }

    public function update(Request $request, Mesa $mesa): RedirectResponse
    {
        $request->validate([
            'numero' => 'required|integer|unique:mesas,numero,' . $mesa->id,
            'capacidade' => 'required|integer|min:1',
            'localizacao' => 'required|in:interior,exterior,bar',
            'estado' => 'required|in:livre,ocupada,reservada',
        ]);

        $mesa->update($request->only(['numero', 'capacidade', 'localizacao', 'estado']));

        return redirect()->route('mesas.index')->with('success', 'Mesa atualizada com sucesso.');
    }

    public function destroy(Mesa $mesa): RedirectResponse
    {
        $mesa->delete();

        return redirect()->route('mesas.index')->with('success', 'Mesa excluída com sucesso.');
    }
}
