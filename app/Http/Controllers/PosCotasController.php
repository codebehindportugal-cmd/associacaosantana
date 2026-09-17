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
            'pdfUrl' => route('pos.cotas.recibo.pdf', $cota),
        ]);
    }

    /** PDF do recibo com os dados do socio (A5), para imprimir. */
    public function reciboPdf(Cota $cota): HttpResponse
    {
        $cota->load('socio');
        $cotas = $this->cotasDoRecibo($cota);
        $logo = public_path('images/santana-logo.png');

        $pdf = Pdf::loadView('pdf.recibo-cota', [
            'socio' => $cota->socio,
            'cotas' => $cotas,
            'total' => (float) $cotas->sum('valor'),
            'numero' => str_pad((string) $cota->id, 5, '0', STR_PAD_LEFT),
            'dataPagamento' => optional($cota->data_pagamento)->format('d/m/Y'),
            'metodo' => ['dinheiro' => 'Numerário', 'mbway' => 'MB WAY', 'transferencia' => 'Transferência bancária'][$cota->metodo_pagamento] ?? ($cota->metodo_pagamento ?: '—'),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null,
        ])->setPaper('a5', 'portrait');

        return $pdf->stream("recibo-quota-{$cota->socio->numero_socio}-{$cota->id}.pdf");
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
