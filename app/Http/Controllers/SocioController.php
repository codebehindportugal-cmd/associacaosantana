<?php

namespace App\Http\Controllers;

use App\Models\Cota;
use App\Models\Socio;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SocioController extends Controller
{
    public function index(Request $request): View
    {
        $query = Socio::query();

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('pesquisa')) {
            $search = $request->input('pesquisa');
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('numero_socio', 'like', "%{$search}%");
            });
        }

        $socios = $query->orderBy('nome')->paginate(20);

        return view('socios.index', compact('socios'));
    }

    public function create(): View
    {
        return view('socios.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'numero_socio' => 'required|string|unique:socios,numero_socio',
            'nome' => 'required|string|max:255',
            'email' => 'required|email|unique:socios,email',
            'telefone' => 'nullable|string|max:50',
            'morada' => 'nullable|string|max:255',
            'data_nascimento' => 'nullable|date',
            'data_inscricao' => 'nullable|date',
            'estado' => 'required|in:ativo,inativo',
        ]);

        Socio::create($request->only([
            'numero_socio',
            'nome',
            'email',
            'telefone',
            'morada',
            'data_nascimento',
            'data_inscricao',
            'estado',
        ]));

        return redirect()->route('socios.index')->with('success', 'Sócio criado com sucesso.');
    }

    public function show(Socio $socio): View
    {
        return view('socios.show', compact('socio'));
    }

    public function edit(Socio $socio): View
    {
        return view('socios.edit', compact('socio'));
    }

    public function update(Request $request, Socio $socio): RedirectResponse
    {
        $request->validate([
            'numero_socio' => 'required|string|unique:socios,numero_socio,' . $socio->id,
            'nome' => 'required|string|max:255',
            'email' => 'required|email|unique:socios,email,' . $socio->id,
            'telefone' => 'nullable|string|max:50',
            'morada' => 'nullable|string|max:255',
            'data_nascimento' => 'nullable|date',
            'data_inscricao' => 'nullable|date',
            'estado' => 'required|in:ativo,inativo',
        ]);

        $socio->update($request->only([
            'numero_socio',
            'nome',
            'email',
            'telefone',
            'morada',
            'data_nascimento',
            'data_inscricao',
            'estado',
        ]));

        return redirect()->route('socios.index')->with('success', 'Sócio atualizado com sucesso.');
    }

    public function destroy(Socio $socio): RedirectResponse
    {
        $socio->delete();

        return redirect()->route('socios.index')->with('success', 'Sócio excluído com sucesso.');
    }

    public function emAtraso(): View
    {
        $socios = Socio::whereHas('cotas', function ($query) {
            $query->where('estado', 'em_atraso');
        })->orderBy('nome')->paginate(20);

        return view('socios.em_atraso', compact('socios'));
    }

    public function gerarCotas(): RedirectResponse
    {
        $hoje = now();
        $ano = $hoje->year;
        $mes = $hoje->month;

        Socio::chunk(100, function ($socios) use ($ano, $mes, $hoje) {
            foreach ($socios as $socio) {
                $exists = Cota::where('socio_id', $socio->id)
                    ->where('ano', $ano)
                    ->where('mes', $mes)
                    ->exists();

                if (! $exists) {
                    Cota::create([
                        'socio_id' => $socio->id,
                        'ano' => $ano,
                        'mes' => $mes,
                        'tipo' => 'mensal',
                        'valor' => 0,
                        'data_vencimento' => $hoje->endOfMonth()->toDateString(),
                        'estado' => 'pendente',
                    ]);
                }
            }
        });

        return redirect()->route('socios.index')->with('success', 'Cotas geradas com sucesso.');
    }
}
