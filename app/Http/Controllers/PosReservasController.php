<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosReservasController extends Controller
{
    public function index(): Response
    {
        $hoje = today()->toDateString();

        $reservasHoje = Reserva::query()
            ->whereDate('data', $hoje)
            ->whereIn('estado', ['confirmada', 'sentada'])
            ->orderBy('hora')
            ->get();

        return Inertia::render('PosReservas/Index', [
            'posNome' => session('pos_nome'),
            'operadorNome' => session('pos_operador') ?: session('pos_nome'),
            'hoje' => $hoje,
            'reservasHoje' => $reservasHoje,
            'proximasReservas' => Reserva::query()
                ->whereDate('data', '>', $hoje)
                ->where('estado', 'confirmada')
                ->orderBy('data')
                ->orderBy('hora')
                ->limit(12)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Reserva::create($this->validated($request) + ['estado' => 'confirmada']);

        return back()->with('success', 'Reserva criada.');
    }

    public function update(Request $request, Reserva $reserva): RedirectResponse
    {
        $reserva->update($request->validate([
            'hora' => ['required', 'date_format:H:i'],
            'pessoas' => ['required', 'integer', 'min:1'],
        ]));

        return back()->with('success', 'Reserva atualizada.');
    }

    public function chamar(Reserva $reserva): RedirectResponse
    {
        $reserva->update(['chamada_em' => now()]);

        return back();
    }

    public function sentar(Reserva $reserva): RedirectResponse
    {
        $reserva->update([
            'estado' => 'sentada',
            'sentada_em' => $reserva->sentada_em ?? now(),
        ]);

        return back();
    }

    public function cancelar(Reserva $reserva): RedirectResponse
    {
        $reserva->update(['estado' => 'cancelada']);

        return back();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'data' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'pessoas' => ['required', 'integer', 'min:1'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }
}
