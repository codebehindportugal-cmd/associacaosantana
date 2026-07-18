<?php

namespace App\Http\Controllers;

use App\Models\Aluguer;
use App\Models\AluguerOpcao;
use App\Rules\Recaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaoController extends Controller
{
    public function show(): Response
    {
        // Datas ocupadas (pendentes + confirmadas) a partir de hoje
        $ocupadas = Aluguer::query()
            ->where('data_fim', '>=', now()->toDateString())
            ->whereIn('estado', ['pendente', 'confirmado'])
            ->get(['data_inicio', 'data_fim'])
            ->map(fn ($a) => [
                'inicio' => $a->data_inicio->toDateString(),
                'fim'    => $a->data_fim->toDateString(),
            ])
            ->values();

        return Inertia::render('Salao/PreReserva', [
            'opcoes'   => AluguerOpcao::where('ativo', true)
                            ->orderBy('ordem')
                            ->get(['id', 'nome', 'descricao', 'preco_extra']),
            'ocupadas' => $ocupadas,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome_cliente'    => ['required', 'string', 'max:255'],
            'telefone'        => ['required', 'string', 'max:50'],
            'email'           => ['nullable', 'email', 'max:255'],
            'data_inicio'     => ['required', 'date', 'after_or_equal:today'],
            'data_fim'        => ['required', 'date', 'gte:data_inicio'],
            'notas'           => ['nullable', 'string', 'max:2000'],
            'opcoes'          => ['nullable', 'array'],
            'opcoes.*'        => ['integer', 'exists:aluguer_opcoes,id'],
            'recaptcha_token' => [new Recaptcha],
        ]);

        unset($data['recaptcha_token']);

        // Verificar conflito com reservas existentes
        $conflito = Aluguer::query()
            ->where('data_fim', '>=', $data['data_inicio'])
            ->where('data_inicio', '<=', $data['data_fim'])
            ->whereIn('estado', ['pendente', 'confirmado'])
            ->exists();

        if ($conflito) {
            return back()->withErrors([
                'data_inicio' => 'As datas selecionadas já têm uma reserva ou pré-reserva pendente. Por favor escolha outras datas.',
            ])->withInput();
        }

        $aluguer = Aluguer::create([
            'nome_cliente' => $data['nome_cliente'],
            'telefone'     => $data['telefone'],
            'email'        => $data['email'] ?? null,
            'data_inicio'  => $data['data_inicio'],
            'data_fim'     => $data['data_fim'],
            'notas'        => $data['notas'] ?? null,
            'estado'       => 'pendente',
        ]);

        if (! empty($data['opcoes'])) {
            $aluguer->opcoes()->sync($data['opcoes']);
        }

        return redirect()->route('salao.pre-reserva')
            ->with('success', 'Pré-reserva enviada com sucesso! Entraremos em contacto brevemente para confirmar.');
    }
}
