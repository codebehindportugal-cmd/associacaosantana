<?php

namespace App\Http\Controllers;

use App\Models\Aluguer;
use App\Models\AluguerOpcao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AluguerController extends Controller
{
    public function index(Request $request): Response
    {
        $ano  = (int) ($request->ano  ?? now()->year);
        $mes  = (int) ($request->mes  ?? now()->month);

        $inicio = Carbon::create($ano, $mes, 1)->startOfMonth();
        $fim    = Carbon::create($ano, $mes, 1)->endOfMonth();

        // Alugueres que tocam neste mês
        $alugueres = Aluguer::with('opcoes')
            ->where('data_inicio', '<=', $fim)
            ->where('data_fim', '>=', $inicio)
            ->where('estado', '!=', 'cancelado')
            ->orderBy('data_inicio')
            ->get()
            ->map(fn ($a) => $this->aluguerData($a));

        // Próximos alugueres (todos os confirmados futuros)
        $proximos = Aluguer::with('opcoes')
            ->where('data_fim', '>=', now()->toDateString())
            ->whereIn('estado', ['pendente', 'confirmado'])
            ->orderBy('data_inicio')
            ->limit(10)
            ->get()
            ->map(fn ($a) => $this->aluguerData($a));

        return Inertia::render('Alugueres/Index', [
            'alugueres' => $alugueres,
            'proximos'  => $proximos,
            'ano'       => $ano,
            'mes'       => $mes,
            'opcoes'    => AluguerOpcao::orderBy('ordem')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome_cliente'     => ['required', 'string', 'max:255'],
            'entidade'         => ['nullable', 'string', 'max:255'],
            'telefone'         => ['nullable', 'string', 'max:50'],
            'email'            => ['nullable', 'email', 'max:255'],
            'data_inicio'      => ['required', 'date'],
            'data_fim'         => ['required', 'date', 'gte:data_inicio'],
            'notas'            => ['nullable', 'string'],
            'estado'           => ['nullable', 'in:pendente,confirmado,cancelado,concluido'],
            'caucao'           => ['nullable', 'numeric', 'min:0'],
            'caucao_devolvida' => ['boolean'],
            'preco_total'      => ['nullable', 'numeric', 'min:0'],
            'pago'             => ['boolean'],
            'metodo_pagamento' => ['nullable', 'string', 'max:100'],
            'opcoes'           => ['nullable', 'array'],
            'opcoes.*'         => ['integer', 'exists:aluguer_opcoes,id'],
        ]);

        $opcoes = $data['opcoes'] ?? [];
        unset($data['opcoes']);

        $aluguer = Aluguer::create(array_merge($data, [
            'user_id' => auth()->id(),
        ]));

        if (! empty($opcoes)) {
            $aluguer->opcoes()->sync($opcoes);
        }

        return redirect()->route('alugueres.index')
            ->with('success', 'Aluguer criado.');
    }

    public function update(Request $request, $aluguer): RedirectResponse
    {
        // Aceita quer o modelo (route model binding) quer um ID inteiro
        $id     = $aluguer instanceof Aluguer ? $aluguer->getKey() : (int) $aluguer;
        $model  = Aluguer::findOrFail($id);

        $data = $request->validate([
            'nome_cliente'     => ['required', 'string', 'max:255'],
            'entidade'         => ['nullable', 'string', 'max:255'],
            'telefone'         => ['nullable', 'string', 'max:50'],
            'email'            => ['nullable', 'email', 'max:255'],
            'data_inicio'      => ['required', 'date'],
            'data_fim'         => ['required', 'date', 'gte:data_inicio'],
            'notas'            => ['nullable', 'string'],
            'estado'           => ['nullable', 'in:pendente,confirmado,cancelado,concluido'],
            'caucao'           => ['nullable', 'numeric', 'min:0'],
            'caucao_devolvida' => ['boolean'],
            'preco_total'      => ['nullable', 'numeric', 'min:0'],
            'pago'             => ['boolean'],
            'metodo_pagamento' => ['nullable', 'string', 'max:100'],
            'opcoes'           => ['nullable', 'array'],
            'opcoes.*'         => ['integer', 'exists:aluguer_opcoes,id'],
        ]);

        $opcoes = $data['opcoes'] ?? [];
        unset($data['opcoes']);

        $model->update($data);
        $model->opcoes()->sync($opcoes);

        return redirect()->route('alugueres.index')
            ->with('success', 'Aluguer atualizado.');
    }

    public function destroy(Aluguer $aluguer): RedirectResponse
    {
        $aluguer->delete();

        return redirect()->route('alugueres.index')
            ->with('success', 'Aluguer eliminado.');
    }

    public function opcoes(): Response
    {
        return Inertia::render('Alugueres/Opcoes', [
            'opcoes' => AluguerOpcao::orderBy('ordem')->get(),
        ]);
    }

    private function aluguerData(Aluguer $a): array
    {
        return [
            'id'               => $a->id,
            'nome_cliente'     => $a->nome_cliente,
            'entidade'         => $a->entidade,
            'telefone'         => $a->telefone,
            'email'            => $a->email,
            'data_inicio'      => $a->data_inicio->toDateString(),
            'data_fim'         => $a->data_fim->toDateString(),
            'numero_dias'      => $a->numero_dias,
            'notas'            => $a->notas,
            'estado'           => $a->estado,
            'caucao'           => $a->caucao,
            'caucao_devolvida' => $a->caucao_devolvida,
            'preco_total'      => $a->preco_total,
            'pago'             => $a->pago,
            'metodo_pagamento' => $a->metodo_pagamento,
            'opcoes'           => $a->opcoes->map(fn ($o) => ['id' => $o->id, 'nome' => $o->nome])->values(),
            'opcoes_ids'       => $a->opcoes->pluck('id')->values(),
        ];
    }
}
