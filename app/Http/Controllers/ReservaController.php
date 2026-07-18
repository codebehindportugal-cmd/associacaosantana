<?php

namespace App\Http\Controllers;

use App\Mail\ReservaConfirmadaMail;
use App\Models\Reserva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ReservaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:reservas.ver')->only(['index', 'show']);
        $this->middleware('permission:reservas.criar')->only(['create', 'store']);
        $this->middleware('permission:reservas.editar')->only(['edit', 'update', 'sentar', 'chamar']);
        $this->middleware('permission:reservas.apagar')->only('destroy');
    }

    public function index(Request $request): Response
    {
        $hoje = now()->toDateString();
        $dataFiltro = $request->input('data', $hoje);

        return Inertia::render('Reservas/Index', [
            'reservas' => Reserva::query()
                ->whereIn('estado', ['em_espera', 'confirmada', 'sentada', 'cancelada'])
                ->whereDate('data', $dataFiltro)
                ->orderBy('hora')
                ->get(),
            'dataFiltro' => $dataFiltro,
        ]);
    }

    public function create(): Response
    {
        return $this->index();
    }

    public function store(Request $request): RedirectResponse
    {
        $total = Reserva::whereDate('data', $request->input('data'))
            ->whereIn('estado', ['em_espera', 'confirmada'])
            ->count();

        $estado = $total >= 200 ? 'em_espera' : 'confirmada';

        $reserva = Reserva::create($this->validated($request) + ['estado' => $estado]);

        $this->enviarEmailReserva($reserva);

        $mensagem = $estado === 'em_espera'
            ? 'Reserva adicionada à lista de espera (limite de 200 atingido).'
            : 'Reserva criada.';

        return back()->with('success', $mensagem);
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
        $reserva->update([
            'estado' => 'sentada',
            'sentada_em' => $reserva->sentada_em ?? now(),
        ]);

        return back()->with('success', 'Reserva marcada como sentada.');
    }

    public function chamar(Reserva $reserva): RedirectResponse
    {
        $reserva->update(['chamada_em' => now()]);

        return back()->with('success', 'Reserva marcada como chamada.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome'        => ['required', 'string', 'max:255'],
            'telefone'    => ['nullable', 'string', 'max:30'],
            'email'       => ['nullable', 'email', 'max:150'],
            'data'        => ['sometimes', 'required', 'date'],
            'hora'        => ['required', 'date_format:H:i,H:i:s'],
            'pessoas'     => ['required', 'integer', 'min:1'],
            'estado'      => ['nullable', 'in:em_espera,confirmada,sentada,cancelada'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }

    private function enviarEmailReserva(Reserva $reserva): void
    {
        try {
            if ($reserva->email) {
                Mail::to($reserva->email)->send(new ReservaConfirmadaMail($reserva));
            }

            if ($aviso = config('mail.contact_to')) {
                $data = \Carbon\Carbon::parse($reserva->data)->format('d/m/Y');
                $hora = substr($reserva->hora, 0, 5);
                Mail::raw(
                    "Nova reserva registada:\n\n".
                    "Nome: {$reserva->nome}\n".
                    ($reserva->telefone ? "Telefone: {$reserva->telefone}\n" : '').
                    ($reserva->email ? "Email: {$reserva->email}\n" : '').
                    "Data: {$data} às {$hora}\n".
                    "Pessoas: {$reserva->pessoas}\n".
                    ($reserva->observacoes ? "Observações: {$reserva->observacoes}\n" : ''),
                    fn ($m) => $m->to($aviso)->subject("Nova reserva – {$data} às {$hora} – {$reserva->nome}")
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar emails de reserva', ['reserva' => $reserva->id, 'erro' => $e->getMessage()]);
        }
    }
}
