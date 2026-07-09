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
            ->whereIn('estado', ['em_espera', 'confirmada', 'sentada'])
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
        $total = Reserva::whereDate('data', $request->input('data'))
            ->whereIn('estado', ['em_espera', 'confirmada'])
            ->count();

        $estado = $total >= 200 ? 'em_espera' : 'confirmada';

        Reserva::create($this->validated($request) + ['estado' => $estado]);

        $mensagem = $estado === 'em_espera'
            ? 'Reserva adicionada à lista de espera (limite de 200 atingido).'
            : 'Reserva criada.';

        return back()->with('success', $mensagem);
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
        $data = $reserva->data;
        $reserva->update(['estado' => 'cancelada']);

        // Promove a primeira reserva em espera, se houver capacidade
        $confirmadas = Reserva::whereDate('data', $data)
            ->where('estado', 'confirmada')
            ->count();

        if ($confirmadas < 200) {
            Reserva::whereDate('data', $data)
                ->where('estado', 'em_espera')
                ->orderBy('id')
                ->first()
                ?->update(['estado' => 'confirmada']);
        }

        return back();
    }

    public function ecra(): Response
    {
        $hoje = today()->toDateString();

        return Inertia::render('PosReservas/Ecra', [
            'chamadas' => Reserva::query()
                ->whereDate('data', $hoje)
                ->whereNotNull('chamada_em')
                ->where('estado', '!=', 'cancelada')
                ->where('estado', '!=', 'sentada')
                ->orderByDesc('chamada_em')
                ->get(['id', 'nome', 'pessoas', 'hora', 'chamada_em']),
        ]);
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
