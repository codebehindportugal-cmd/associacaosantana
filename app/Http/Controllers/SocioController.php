<?php

namespace App\Http\Controllers;

use App\Models\Cota;
use App\Models\Socio;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SocioController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:socios.ver')->only(['index', 'show', 'emAtraso', 'exportarPDF']);
        $this->middleware('permission:socios.criar')->only(['create', 'store']);
        $this->middleware('permission:socios.editar')->only(['edit', 'update']);
        $this->middleware('permission:socios.apagar')->only('destroy');
    }

    public function index(Request $request): Response
    {
        $socios = Socio::with('cotas')
            ->when($request->estado, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($request->pesquisa, fn ($query, $pesquisa) => $query->where(fn ($q) => $q
                ->where('nome', 'like', "%{$pesquisa}%")
                ->orWhere('numero_socio', 'like', "%{$pesquisa}%")))
            ->orderBy('numero_socio')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Socios/Index', ['socios' => $socios, 'filters' => $request->only('estado', 'pesquisa')]);
    }

    public function create(): Response
    {
        return Inertia::render('Socios/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        Socio::create($this->validated($request));

        return to_route('socios.index')->with('success', 'Sócio criado com sucesso.');
    }

    public function show(Socio $socio): Response
    {
        $socio->load(['cotas' => fn ($query) => $query->latest('ano')->latest('mes')]);

        return Inertia::render('Socios/Show', ['socio' => $socio]);
    }

    public function edit(Socio $socio): Response
    {
        return Inertia::render('Socios/Form', ['socio' => $socio]);
    }

    public function update(Request $request, Socio $socio): RedirectResponse
    {
        $socio->update($this->validated($request, $socio));

        return to_route('socios.index')->with('success', 'Sócio atualizado com sucesso.');
    }

    public function destroy(Socio $socio): RedirectResponse
    {
        $socio->delete();

        return to_route('socios.index')->with('success', 'Sócio apagado com sucesso.');
    }

    public function emAtraso(): Response
    {
        return Inertia::render('Cotas/EmAtraso', ['socios' => $this->sociosEmAtraso()]);
    }

    public function exportarPDF()
    {
        $socios = $this->sociosEmAtraso();

        return Pdf::loadView('pdf.socios-atraso', [
            'socios' => $socios,
            'total' => collect($socios)->sum('valor_divida'),
        ])->download('socios-com-cotas-em-atraso.pdf');
    }

    private function validated(Request $request, ?Socio $socio = null): array
    {
        return $request->validate([
            'numero_socio' => ['required', 'string', 'max:50', 'unique:socios,numero_socio,'.($socio?->id ?? 'NULL')],
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'morada' => ['nullable', 'string'],
            'data_nascimento' => ['nullable', 'date'],
            'data_inscricao' => ['required', 'date'],
            'estado' => ['required', 'in:ativo,inativo'],
        ]);
    }

    private function sociosEmAtraso()
    {
        return Socio::emAtraso()->with(['cotas' => fn ($query) => $query->emAtraso()])->orderBy('nome')->get()
            ->map(fn (Socio $socio) => [
                'id' => $socio->id,
                'numero_socio' => $socio->numero_socio,
                'nome' => $socio->nome,
                'meses_atraso' => $socio->cotas->count(),
                'valor_divida' => (float) $socio->cotas->sum('valor'),
            ]);
    }
}
