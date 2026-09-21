<?php

namespace App\Http\Controllers;

use App\Models\Cota;
use App\Models\Socio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PosCotasController extends Controller
{
    public function index(): Response
    {
        $sociosEmAtraso = Socio::emAtraso()->count();
        $cobradosHoje = Cota::whereDate('data_pagamento', today())->sum('valor');
        $cotasHoje = Cota::whereDate('data_pagamento', today())->count();

        return Inertia::render('PosCotas/Index', compact('sociosEmAtraso', 'cobradosHoje', 'cotasHoje'));
    }

    public function pesquisa(Request $request): Response
    {
        $query = trim((string) $request->q);
        $socios = Socio::query()
            ->when($query !== '', fn ($builder) => $builder->where(function ($where) use ($query) {
                $where->where('nome', 'like', "%{$query}%")
                    ->orWhere('numero_socio', 'like', "%{$query}%")
                    ->orWhere('telefone', 'like', "%{$query}%");
            }))
            ->with(['cotas' => fn ($q) => $q->latest()->limit(12)])
            ->orderBy('nome')
            ->limit(10)
            ->get();

        return Inertia::render('PosCotas/Pesquisa', ['socios' => $socios, 'query' => $query]);
    }

    public function socio(Socio $socio): Response
    {
        $cotasRecentes = $socio->cotas()->latest()->limit(24)->get();
        $anosEmAtraso = $socio->anos_em_atraso;
        $valorEmDivida = $socio->valor_em_divida;
        $anosPagos = $socio->cotas()->where('estado', 'pago')->pluck('ano')->map(fn ($a) => (int) $a)->unique()->values();

        return Inertia::render('PosCotas/Socio', compact('socio', 'cotasRecentes', 'anosEmAtraso', 'valorEmDivida', 'anosPagos') + [
            'valorCota' => Socio::VALOR_COTA_ANUAL,
            'anoInscricao' => (int) (optional($socio->data_inscricao)->year ?: now()->year),
        ]);
    }

    public function novoSocioForm(): Response
    {
        // numero_socio e texto: MAX() em texto dava "99" > "474"
        $proximo = ((int) Socio::max(DB::raw('CAST(numero_socio AS UNSIGNED)'))) + 1;

        return Inertia::render('PosCotas/NovoSocio', ['proximoNumero' => (string) max(1, $proximo)]);
    }

    public function registarPagamento(Request $request, Socio $socio): RedirectResponse
    {
        // A cota e anual: paga-se um ano, ou varios de uma vez quando ha atraso
        $data = $request->validate([
            'anos' => ['required', 'array', 'min:1'],
            'anos.*' => ['integer', 'min:2000', 'max:2100'],
            'metodo_pagamento' => ['required', 'in:dinheiro,mbway,transferencia'],
            'valor_recebido' => ['nullable', 'numeric', 'min:0'],
        ]);

        $ultimaCota = null;

        foreach ($data['anos'] as $ano) {
            $ultimaCota = $socio->cotas()->updateOrCreate(
                ['ano' => $ano, 'tipo' => 'anual'],
                [
                    'mes' => null,
                    'valor' => Socio::VALOR_COTA_ANUAL,
                    'estado' => 'pago',
                    'data_pagamento' => today(),
                    'data_vencimento' => now()->setDate($ano, 12, 31)->toDateString(),
                    'metodo_pagamento' => $data['metodo_pagamento'],
                ],
            );
        }

        return to_route('pos.cotas.recibo', $ultimaCota);
    }

    public function novoSocio(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'numero_socio' => ['required', 'string', 'max:50', 'unique:socios,numero_socio'],
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:50'],
        ]);

        $socio = Socio::create($data + ['data_inscricao' => today(), 'estado' => 'ativo']);

        return to_route('pos.cotas.socio', $socio);
    }

    public function recibo(Cota $cota): Response
    {
        $cotas = $this->cotasDoRecibo($cota);

        return Inertia::render('PosCotas/Recibo', [
            'cota' => $cota->load('socio'),
            'anos' => $cotas->pluck('ano')->map(fn ($a) => (int) $a)->values(),
            'total' => (float) $cotas->sum('valor'),
            'papelUrl' => route('pos.cotas.recibo.papel', $cota),
        ]);
    }

    /**
     * Recibo de quota em formato DL (220 × 110 mm), réplica do recibo em papel da
     * associação: formulário completo + dados (ou só os dados, ver config/recibos.php).
     * Uma folha por cada ano pago.
     * ?grelha=1 → folha de teste com grelha em mm para acertar posições.
     */
    public function reciboPapel(Request $request, Cota $cota): HttpResponse
    {
        $cota->load('socio');
        $socio = $cota->socio;

        $recibos = $this->cotasDoRecibo($cota)->map(function (Cota $c) use ($socio) {
            [$euros, $centimos] = explode(',', number_format((float) $c->valor, 2, ',', ''));
            $ano = substr((string) $c->ano, -2);

            return [
                'socio_cima' => (string) $socio->numero_socio,
                'nome' => (string) $socio->nome,
                'euros_cima' => $euros,
                'centimos_cima' => $centimos,
                'ano_cima' => $ano,
                'socio_baixo' => (string) $socio->numero_socio,
                'ano_baixo' => $ano,
                'euros_baixo' => $euros,
                'centimos_baixo' => $centimos,
            ];
        })->values();

        $p = config('recibos.papel');
        $logo = public_path('images/santana-logo-recibo.png');
        // 1 mm = 72/25,4 pt
        $tamanho = [0, 0, $p['largura'] * 72 / 25.4, $p['altura'] * 72 / 25.4];

        $pdf = Pdf::loadView('pdf.recibo-cota-papel', [
            'recibos' => $recibos,
            'grelha' => $request->boolean('grelha'),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null,
        ])->setPaper($tamanho);

        return $pdf->stream("recibo-papel-{$socio->numero_socio}-{$cota->id}.pdf");
    }

    /**
     * Anos pagos no mesmo pagamento: quando se pagam varios anos de uma vez,
     * o recibo mostra-os todos (mesmo socio, mesmo dia, gravados no mesmo minuto).
     */
    private function cotasDoRecibo(Cota $cota): Collection
    {
        if ($cota->estado !== 'pago' || ! $cota->data_pagamento) {
            return collect([$cota]);
        }

        return Cota::where('socio_id', $cota->socio_id)
            ->where('estado', 'pago')
            ->whereDate('data_pagamento', $cota->data_pagamento)
            ->where('metodo_pagamento', $cota->metodo_pagamento)
            ->whereBetween('updated_at', [$cota->updated_at->copy()->subMinute(), $cota->updated_at->copy()->addMinute()])
            ->orderBy('ano')
            ->get();
    }

    public function emAtraso(): Response
    {
        $socios = Socio::emAtraso()
            ->ativos()
            ->with(['cotas' => fn ($q) => $q->emAtraso()])
            ->orderBy('nome')
            ->get()
            ->sortByDesc('anos_em_atraso')
            ->values();

        return Inertia::render('PosCotas/EmAtraso', ['socios' => $socios]);
    }

    public function resumoDia(): Response
    {
        $cotas = Cota::whereDate('data_pagamento', today())->with('socio')->latest()->get();

        return Inertia::render('PosCotas/ResumoDia', ['cotas' => $cotas]);
    }
}
