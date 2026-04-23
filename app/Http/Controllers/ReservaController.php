<?php

namespace App\Http\Controllers;

use App\Models\Mesa;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReservaController extends Controller
{
    public function index(): View
    {
        $reservas = Reserva::with('mesa')->orderBy('data')->orderBy('hora')->paginate(20);

        return view('reservas.index', compact('reservas'));
    }

    public function create(): View
    {
        $mesas = Mesa::orderBy('numero')->get();

        return view('reservas.create', compact('mesas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'mesa_id' => 'required|exists:mesas,id',
            'nome' => 'required|string|max:255',
            'telefone' => 'required|string|max:50',
            'data' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'pessoas' => 'required|integer|min:1',
            'estado' => 'required|in:confirmada,cancelada',
        ]);

        Reserva::create($request->only(['mesa_id', 'nome', 'telefone', 'data', 'hora', 'pessoas', 'estado']));

        return redirect()->route('reservas.index')->with('success', 'Reserva criada com sucesso.');
    }

    public function update(Request $request, Reserva $reserva): RedirectResponse
    {
        $request->validate([
            'mesa_id' => 'required|exists:mesas,id',
            'nome' => 'required|string|max:255',
            'telefone' => 'required|string|max:50',
            'data' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'pessoas' => 'required|integer|min:1',
            'estado' => 'required|in:confirmada,cancelada',
        ]);

        $reserva->update($request->only(['mesa_id', 'nome', 'telefone', 'data', 'hora', 'pessoas', 'estado']));

        return redirect()->route('reservas.index')->with('success', 'Reserva atualizada com sucesso.');
    }

    public function destroy(Reserva $reserva): RedirectResponse
    {
        $reserva->delete();

        return redirect()->route('reservas.index')->with('success', 'Reserva excluída com sucesso.');
    }
}
