<?php

namespace App\Http\Controllers;

use App\Models\Configuracao;
use App\Models\Impressora;
use App\Models\PosSession;
use App\Models\TalaoConfig;
use App\Models\PrintJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ImpressoraController extends Controller
{
    private const SECOES = [
        'bebidas' => 'Bebidas',
        'frango' => 'Frango',
        'acompanhamentos' => 'Acompanhamentos',
        'comida' => 'Comida',
        'cozinha' => 'Cozinha',
        'sobremesas' => 'Sobremesas',
        'servico' => 'Servico',
        'bar' => 'Bar',
        'cafe' => 'Cafe',
        'pos' => 'POS',
        'contas' => 'Contas',
    ];

    public function index(): Response
    {
        $impressoras = Impressora::orderBy('nome')->get();
        $estados = $this->estadosImpressoras($impressoras);
        $impressoras->each(fn (Impressora $impressora) => $impressora->setAttribute('estado_impressao', $estados[$impressora->id] ?? null));

        return Inertia::render('Impressoras/Index', [
            'impressoras' => $impressoras,
            'secoes' => self::SECOES,
            'terminais' => PosSession::orderBy('nome')->get(['id', 'nome', 'tipo', 'localizacao', 'impressora_id', 'impressao_navegador', 'offline', 'prefixo_senha', 'ativo']),
            'tiposTerminal' => ['restaurante', 'reservas', 'bar', 'cafe', 'cotas'],
            'tiposImpressora' => Impressora::TIPOS,
            'agente' => $this->estadoAgente(),
        ]);
    }

    /**
     * Guarda o token do agente. Sem token no pedido, gera um novo.
     * Mudar o token desliga os agentes que ainda tenham o antigo.
     */
    public function guardarTokenAgente(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['nullable', 'string', 'min:16', 'max:120', 'regex:/^[A-Za-z0-9_\-\.]+$/'],
        ], [
            'token.min' => 'O token tem de ter pelo menos 16 caracteres.',
            'token.regex' => 'O token so pode ter letras, numeros, - _ e .',
        ]);

        $token = ($data['token'] ?? '') ?: Str::random(40);

        Configuracao::updateOrCreate(
            ['chave' => PrintAgentController::CHAVE_TOKEN],
            ['valor' => $token, 'descricao' => 'Token do agente de impressao (Raspberry / Windows)'],
        );

        Cache::forget(PrintAgentController::CACHE_ULTIMO_401);

        return back()->with('success', 'Token do agente guardado. Atualiza o .env do Raspberry com o comando indicado.');
    }

    private function estadoAgente(): array
    {
        $doSite = (string) Configuracao::where('chave', PrintAgentController::CHAVE_TOKEN)->value('valor');

        return [
            'token' => PrintAgentController::tokenAtual(),
            'origem' => $doSite !== '' ? 'site' : (config('services.print_agent.token') ? 'servidor' : null),
            'url' => rtrim((string) config('app.url'), '/'),
            'ultimo_ok_at' => Cache::get(PrintAgentController::CACHE_ULTIMO_OK),
            'ultimo_401_at' => Cache::get(PrintAgentController::CACHE_ULTIMO_401),
        ];
    }

    /**
     * Teste de impressao por WebUSB: o browser fala com a impressora USB
     * sem agente nenhum. Unico caminho nos Chromebooks.
     */
    public function testeUsb(): Response
    {
        $talao = TalaoConfig::atual();

        return Inertia::render('Impressoras/TesteUsb', [
            'talao' => [
                'titulo' => $talao->tituloImpresso(),
                'cabecalho' => array_column($talao->linhasCabecalho(), 'texto'),
                'rodape' => $talao->linhasRodape(),
                'instrucoes' => array_column($talao->linhasInstrucoes(), 'texto'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        Impressora::create($validated);

        return back()->with('success', 'Impressora criada com sucesso.');
    }

    public function update(Request $request, Impressora $impressora): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $impressora->update($validated);

        return back()->with('success', 'Impressora atualizada com sucesso.');
    }

    public function destroy(Impressora $impressora): RedirectResponse
    {
        $impressora->delete();

        return back()->with('success', 'Impressora removida com sucesso.');
    }

    public function retentarFalhados(): RedirectResponse
    {
        $count = PrintJob::where('estado', 'falhado')->update([
            'estado' => 'pendente',
            'tentativas' => 0,
            'ultimo_erro' => null,
            'reservado_ate' => null,
        ]);

        return back()->with('success', "Reentrou $count job(s) na fila de impressão.");
    }

    public function statusJobs(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'pendente' => PrintJob::where('estado', 'pendente')->count(),
            'processando' => PrintJob::where('estado', 'processando')->count(),
            'falhado' => PrintJob::where('estado', 'falhado')->count(),
            'impresso' => PrintJob::where('estado', 'impresso')->whereDate('updated_at', today())->count(),
        ]);
    }

    /**
     * Estado de cada impressora, derivado do que ja existe: ativa/inativa,
     * jobs pendentes/falhados recentes da fila print_jobs e a ultima vez que
     * o agente confirmou um talao (ultimo_ok_at, ou o impresso_em mais recente).
     * WebUSB/navegador imprimem no proprio browser e nao passam pela fila.
     *
     * @return array<int, array<string, mixed>>
     */
    private function estadosImpressoras($impressoras): array
    {
        $desde = now()->subHours(12);
        $paradoHa = now()->subMinutes(2);

        $porImpressora = collect();
        $ultimoImpresso = collect();
        $ultimoErroMsg = collect();

        try {
            $porImpressora = PrintJob::query()
                ->where('created_at', '>=', $desde)
                ->selectRaw('impressora_id')
                ->selectRaw("SUM(CASE WHEN estado IN ('pendente', 'processando') THEN 1 ELSE 0 END) as pendentes")
                ->selectRaw("SUM(CASE WHEN estado IN ('pendente', 'processando') AND created_at < ? THEN 1 ELSE 0 END) as parados", [$paradoHa])
                ->selectRaw("SUM(CASE WHEN estado = 'falhado' THEN 1 ELSE 0 END) as falhados")
                ->selectRaw("SUM(CASE WHEN estado = 'impresso' THEN 1 ELSE 0 END) as impressos")
                ->groupBy('impressora_id')
                ->get()
                ->keyBy('impressora_id');

            $ultimoImpresso = PrintJob::query()
                ->where('estado', 'impresso')
                ->whereNotNull('impresso_em')
                ->selectRaw('impressora_id, MAX(impresso_em) as ultimo')
                ->groupBy('impressora_id')
                ->pluck('ultimo', 'impressora_id');

            $ultimoErroMsg = PrintJob::query()
                ->where('estado', 'falhado')
                ->where('created_at', '>=', $desde)
                ->orderByDesc('updated_at')
                ->get(['impressora_id', 'ultimo_erro', 'updated_at'])
                ->unique('impressora_id')
                ->keyBy('impressora_id');
        } catch (\Throwable $e) {
            Log::warning('Estado das impressoras indisponivel: '.$e->getMessage());
        }

        $estados = [];

        foreach ($impressoras as $impressora) {
            $j = $porImpressora->get($impressora->id);
            $pendentes = (int) ($j->pendentes ?? 0);
            $parados = (int) ($j->parados ?? 0);
            $falhados = (int) ($j->falhados ?? 0);
            $impressos = (int) ($j->impressos ?? 0);

            $okJob = $ultimoImpresso->get($impressora->id);
            $okJob = $okJob ? Carbon::parse($okJob) : null;
            $ultimoOk = collect([$impressora->ultimo_ok_at, $okJob])->filter()->max();

            $erroJob = $ultimoErroMsg->get($impressora->id);
            $ultimoErro = collect([$impressora->ultimo_erro_at, $erroJob?->updated_at])->filter()->max();

            if (! $impressora->ativa) {
                [$nivel, $texto] = ['inativa', 'Inativa'];
            } elseif (! $impressora->usaAgente()) {
                [$nivel, $texto] = ['browser', 'Imprime no browser do posto'];
            } elseif ($falhados > 0 && (! $ultimoOk || ($ultimoErro && $ultimoErro->gt($ultimoOk)))) {
                [$nivel, $texto] = ['erro', $falhados === 1 ? '1 falhado' : "$falhados falhados"];
            } elseif ($parados > 0) {
                [$nivel, $texto] = ['atencao', 'Fila parada — agente desligado?'];
            } elseif ($ultimoOk && $ultimoOk->gte($desde)) {
                [$nivel, $texto] = ['ok', 'A imprimir'];
            } else {
                [$nivel, $texto] = ['sem_atividade', 'Sem talões recentes'];
            }

            $estados[$impressora->id] = [
                'nivel' => $nivel,
                'texto' => $texto,
                'pendentes' => $pendentes,
                'falhados' => $falhados,
                'impressos' => $impressos,
                'ultimo_ok_at' => $ultimoOk?->toIso8601String(),
                'ultimo_erro_at' => $ultimoErro?->toIso8601String(),
                'ultimo_erro' => $erroJob?->ultimo_erro,
            ];
        }

        return $estados;
    }

    public function downloadAgente(): StreamedResponse
    {
        $path = base_path('local-printer-agent/setup-pi.sh');

        return response()->streamDownload(function () use ($path): void {
            echo file_get_contents($path);
        }, 'setup-pi.sh', ['Content-Type' => 'text/x-sh']);
    }

    private function systemdService(): string
    {
        return "[Unit]\n".
            "Description=ARDC Santana - Agente de Impressao Local\n".
            "After=network.target\n\n".
            "[Service]\n".
            "Type=simple\n".
            "WorkingDirectory=/opt/ardc-print-agent\n".
            "ExecStart=node --env-file=.env agent.mjs\n".
            "Restart=always\n".
            "RestartSec=5\n".
            "StandardOutput=journal\n".
            "StandardError=journal\n\n".
            "[Install]\n".
            "WantedBy=multi-user.target\n";
    }

    private function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'secao' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::SECOES))],
            'tipo' => ['required', 'in:'.implode(',', array_keys(Impressora::TIPOS))],
            // Rede: IP e porta. USB: nome da impressora no Windows ou /dev/usb/lp0
            'host' => ['nullable', 'required_if:tipo,rede', 'string', 'max:255'],
            'porta' => ['nullable', 'required_if:tipo,rede', 'integer', 'min:1', 'max:65535'],
            // So a USB pelo agente precisa do nome do dispositivo; no WebUSB
            // e o proprio utilizador que escolhe a impressora no browser
            'dispositivo' => ['nullable', 'required_if:tipo,usb', 'string', 'max:255'],
            'agente' => ['nullable', 'string', 'max:60'],
            'ativa' => ['boolean'],
        ];
    }
}
