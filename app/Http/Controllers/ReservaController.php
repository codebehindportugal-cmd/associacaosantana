<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:reservas.ver')->only(['index', 'show']);
        $this->middleware('permission:reservas.criar')->only(['create', 'store']);
        $this->middleware('permission:reservas.editar')->only(['edit', 'update', 'sentar']);
        $this->middleware('permission:reservas.apagar')->only('destroy');
    }

    public function index(): Response
    {
        $hoje = now()->toDateString();

        return Inertia::render('Reservas/Index', [
            'reservas' => Reserva::query()
                ->where('estado', 'confirmada')
                ->orderByRaw('CASE WHEN data >= ? THEN 0 ELSE 1 END', [$hoje])
                ->orderBy('data')
                ->orderBy('hora')
                ->paginate(30),
        ]);
    }

    public function create(): Response
    {
        return $this->index();
    }

    public function store(Request $request): RedirectResponse
    {
        Reserva::create($this->validated($request) + ['estado' => 'confirmada']);

        return back()->with('success', 'Reserva criada.');
    }

    public function show(Reserva $reserva): Response
    {
        return Inertia::render('Reservas/Index', ['reserva' => $reserva]);
    }

    public function edit(Reserva $reserva): Response
    {
        return $this->show($reserva);
    }

    public function update(Request $request, Reserva $reserva): RedirectResponse
    {
        $reserva->update($this->validated($request));

        return back()->with('success', 'Reserva atualizada.');
    }

    public function destroy(Reserva $reserva): RedirectResponse
    {
        $reserva->delete();

        return back()->with('success', 'Reserva apagada.');
    }

    public function sentar(Reserva $reserva): RedirectResponse
    {
        $reserva->update(['estado' => 'sentada']);

        return back()->with('success', 'Reserva marcada como sentada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'data' => ['required', 'date'],
            'hora' => ['required', 'date_format:H:i'],
            'pessoas' => ['required', 'integer', 'min:1'],
            'estado' => ['nullable', 'in:confirmada,sentada,cancelada'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }
}
