<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintAgentController extends Controller
{
    public function jobs(Request $request): JsonResponse
    {
        $this->autorizarAgente($request);

        $jobs = PrintJob::with('impressora')
            ->where(function ($query) {
                $query->where('estado', 'pendente')
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('estado', 'processando')
                            ->where('reservado_ate', '<', now());
                    })
                    ->orWhere(function ($subQuery) {
                        // Jobs falhados so sao recolhidos apos o backoff expirar
                        $subQuery->where('estado', 'falhado')
                            ->where('tentativas', '<', 10)
                            ->where(function ($q) {
                                $q->whereNull('reservado_ate')
                                    ->orWhere('reservado_ate', '<', now());
                            });
                    });
            })
            ->orderBy('id')
            ->limit(10)
            ->get();

        $jobs->each(fn (PrintJob $job) => $job->update([
            'estado' => 'processando',
            'tentativas' => $job->tentativas + 1,
            'reservado_ate' => now()->addMinute(),
        ]));

        return response()->json([
            'jobs' => $jobs->map(fn (PrintJob $job) => [
                'id' => $job->id,
                'tipo' => $job->tipo,
                'payload' => $job->payload,
                'printer' => [
                    'nome' => $job->impressora->nome,
                    'host' => $job->impressora->host,
                    'porta' => $job->impressora->porta,
                    'secao' => $job->impressora->secao,
                ],
            ])->values(),
        ]);
    }

    public function done(Request $request, PrintJob $printJob): JsonResponse
    {
        $this->autorizarAgente($request);

        $printJob->update([
            'estado' => 'impresso',
            'ultimo_erro' => null,
            'reservado_ate' => null,
            'impresso_em' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function fail(Request $request, PrintJob $printJob): JsonResponse
    {
        $this->autorizarAgente($request);

        $data = $request->validate([
            'error' => ['nullable', 'string', 'max:2000'],
        ]);

        // Backoff exponencial: espera mais a cada tentativa (30s, 60s, 120s, ate 10min)
        $tentativas = $printJob->tentativas;
        $backoffSegundos = min(600, 30 * (2 ** max(0, $tentativas - 1)));

        $printJob->update([
            'estado' => 'falhado',
            'ultimo_erro' => $data['error'] ?? 'Erro desconhecido no agente local.',
            'reservado_ate' => now()->addSeconds($backoffSegundos),
        ]);

        return response()->json(['ok' => true]);
    }

    private function autorizarAgente(Request $request): void
    {
        $token = (string) config('services.print_agent.token');
        $recebido = (string) ($request->bearerToken() ?: $request->query('token'));

        abort_if($token === '', 503, 'PRINT_AGENT_TOKEN nao configurado.');
        abort_unless(hash_equals($token, $recebido), 401);
    }
}
