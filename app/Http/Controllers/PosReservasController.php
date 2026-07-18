<?php

namespace App\Http\Controllers;

use App\Mail\ReservaConfirmadaMail;
use App\Models\Mesa;
use App\Models\Reserva;
use App\Services\WebPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            ->orderBy('id')
            ->get()
            ->each(fn ($r) => $r->setAttribute('tem_push', $r->temPushSubscription()))
            ->makeHidden('push_subscription');

        $totalSentadas  = (int) $reservasHoje->where('estado', 'sentada')->sum('pessoas');
        $totalPorSentar = (int) $reservasHoje->whereIn('estado', ['confirmada', 'em_espera'])->sum('pessoas');

        return Inertia::render('PosReservas/Index', [
            'posNome' => session('pos_nome'),
            'operadorNome' => session('pos_operador') ?: session('pos_nome'),
            'hoje' => $hoje,
            'reservasHoje' => $reservasHoje,
            'totalPessoas' => [
                'sentadas'   => $totalSentadas,
                'por_sentar' => $totalPorSentar,
                'total'      => $totalSentadas + $totalPorSentar,
            ],
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

        $reserva = Reserva::create($this->validated($request) + ['estado' => $estado]);

        $this->enviarEmailReserva($reserva);

        $mensagem = $estado === 'em_espera'
            ? 'Reserva adicionada à lista de espera (limite de 200 atingido).'
            : 'Reserva criada.';

        return back()->with('success', $mensagem);
    }

    public function update(Request $request, Reserva $reserva): RedirectResponse
    {
        $reserva->update($request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'hora' => ['required', 'date_format:H:i,H:i:s'],
            'pessoas' => ['required', 'integer', 'min:1'],
            'observacoes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Reserva atualizada.');
    }

    public function chamar(Reserva $reserva): RedirectResponse
    {
        $reserva->update(['chamada_em' => now()]);

        // Enviar notificação push se o cliente subscreveu
        if ($reserva->temPushSubscription()) {
            app(WebPushService::class)->enviarChamada($reserva);
        }

        return back();
    }

    public function sentar(Request $request, Reserva $reserva): RedirectResponse
    {
        $data = $request->validate([
            'mesa_numero' => ['nullable', 'integer', 'min:1'],
            'mesa_letra'  => ['nullable', 'string', 'max:5'],
        ]);

        $mesaAtribuida = null;
        if (! empty($data['mesa_numero'])) {
            $mesaAtribuida = (string) $data['mesa_numero'];
            if (! empty($data['mesa_letra'])) {
                $mesaAtribuida .= strtoupper(trim($data['mesa_letra']));
            }
        }

        $reserva->update([
            'estado'         => 'sentada',
            'sentada_em'     => $reserva->sentada_em ?? now(),
            'mesa_atribuida' => $mesaAtribuida,
        ]);

        // Escrever o nome da reserva na mesa para não se perder
        if (! empty($data['mesa_numero'])) {
            Mesa::where('numero', (int) $data['mesa_numero'])
                ->whereNull('mesa_principal_id')
                ->update(['nome_reserva' => $reserva->nome]);
        }

        // Notificar cliente via push com a mesa atribuída
        if ($mesaAtribuida && $reserva->temPushSubscription()) {
            app(WebPushService::class)->enviarSentada($reserva, $mesaAtribuida);
        }

        return back();
    }

    public function destroy(Reserva $reserva): RedirectResponse
    {
        $reserva->delete();

        return back()->with('success', 'Reserva eliminada.');
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
                ->where(function ($q) {
                    // Pendentes (não sentada, não cancelada) OU sentada recentemente com mesa atribuída
                    $q->where(function ($inner) {
                        $inner->where('estado', '!=', 'cancelada')
                              ->where('estado', '!=', 'sentada');
                    })->orWhere(function ($inner) {
                        $inner->where('estado', 'sentada')
                              ->whereNotNull('mesa_atribuida')
                              ->where('sentada_em', '>=', now()->subMinutes(5));
                    });
                })
                ->orderByDesc('chamada_em')
                ->get(['id', 'nome', 'pessoas', 'hora', 'chamada_em', 'mesa_atribuida', 'estado']),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nome'        => ['required', 'string', 'max:255'],
            'telefone'    => ['nullable', 'string', 'max:30'],
            'email'       => ['nullable', 'email', 'max:150'],
            'data'        => ['required', 'date'],
            'hora'        => ['required', 'date_format:H:i,H:i:s'],
            'pessoas'     => ['required', 'integer', 'min:1'],
            'observacoes' => ['nullable', 'string'],
        ]);
    }

    private function enviarEmailReserva(Reserva $reserva): void
    {
        try {
            // Email de confirmação para o cliente
            if ($reserva->email) {
                Mail::to($reserva->email)->send(new ReservaConfirmadaMail($reserva));
            }

            // Aviso para a associação
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
