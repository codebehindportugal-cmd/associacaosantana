<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PrintAgentController extends Controller
{
    /** Chave em `configuracoes` com o token definido no backoffice. */
    public const CHAVE_TOKEN = 'print_agent_token';

    /** Ultimo pedido do agente aceite / recusado por token errado (cache). */
    public const CACHE_ULTIMO_OK = 'print_agent_ultimo_ok_at';

    public const CACHE_ULTIMO_401 = 'print_agent_ultimo_401_at';

    /**
     * Token que o agente tem de enviar. O definido no backoffice
     * (Impressoras > Agente de impressao) tem prioridade; sem ele, usa o
     * PRINT_AGENT_TOKEN do .env do servidor, como antes.
     */
    public static function tokenAtual(): string
    {
        $doSite = (string) Configuracao::where('chave', self::CHAVE_TOKEN)->value('valor');

        return $doSite !== '' ? $doSite : (string) config('services.print_agent.token');
    }

    public function jobs(Request $request): JsonResponse
    {
        $this->autorizarAgente($request);

        // Com varios postos, cada agente so leva os trabalhos das suas
        // impressoras. Um agente sem identificacao leva as que nao estao
        // atribuidas a nenhum — mantem a instalacao antiga a funcionar.
        $agente = trim((string) $request->query('agente'));

        // Talões antigos nunca saem: ao ligar o agente (ex: Raspberry desligado
        // durante horas) imprimia tudo o que ficou na fila. Passam a falhado
        // com tentativas esgotadas, para ficar registo sem voltarem à fila.
        $limite = now()->subMinutes(max(1, (int) config('services.print_agent.validade_minutos', 10)));
        $this->expirarAntigos($limite);

        $jobs = PrintJob::with('impressora')
            ->where('created_at', '>=', $limite)
            ->whereHas('impressora', fn ($query) => $agente !== ''
                ? $query->where('agente', $agente)
                : $query->whereNull('agente'))
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
                    'tipo' => $job->impressora->tipo ?: 'rede',
                    'host' => $job->impressora->host,
                    'porta' => $job->impressora->porta,
                    'dispositivo' => $job->impressora->dispositivo,
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

        $this->registarEstadoImpressora($printJob, 'ultimo_ok_at');

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

        $this->registarEstadoImpressora($printJob, 'ultimo_erro_at');

        return response()->json(['ok' => true]);
    }

    /**
     * Guarda na impressora quando correu bem/mal pela ultima vez (para o
     * estado no backoffice). Nunca pode falhar a resposta ao agente: se a
     * coluna ainda nao existir (migration por correr), so fica no log —
     * senao o agente repetia o job e o talao saia duas vezes.
     */
    private function registarEstadoImpressora(PrintJob $printJob, string $coluna): void
    {
        try {
            if ($printJob->impressora_id) {
                \App\Models\Impressora::whereKey($printJob->impressora_id)->update([$coluna => now()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Nao foi possivel registar o estado da impressora: '.$e->getMessage());
        }
    }

    private function expirarAntigos(\DateTimeInterface $limite): void
    {
        PrintJob::where('created_at', '<', $limite)
            ->whereIn('estado', ['pendente', 'processando', 'falhado'])
            ->where(fn ($q) => $q->where('estado', '!=', 'falhado')->orWhere('tentativas', '<', 10))
            ->update([
                'estado' => 'falhado',
                'tentativas' => 10,
                'reservado_ate' => null,
                'ultimo_erro' => 'Expirado: nao foi impresso a tempo (agente desligado?).',
            ]);
    }

    private function autorizarAgente(Request $request): void
    {
        $token = self::tokenAtual();
        $recebido = (string) ($request->bearerToken() ?: $request->query('token'));

        abort_if($token === '', 503, 'Token do agente nao configurado (Impressoras > Agente de impressao).');

        if (! hash_equals($token, $recebido)) {
            // Para o backoffice poder avisar "o agente esta a tentar com o token errado"
            Cache::put(self::CACHE_ULTIMO_401, now()->toIso8601String(), now()->addDay());
            abort(401);
        }

        Cache::put(self::CACHE_ULTIMO_OK, now()->toIso8601String(), now()->addDays(30));
    }
}
