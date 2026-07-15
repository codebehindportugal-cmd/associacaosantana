<?php

namespace App\Http\Controllers;

use App\Models\ChamadaComissao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChamadaComissaoController extends Controller
{
    /**
     * Chamada enviada por um funcionário num POS.
     * Autenticação via pos.auth (sessão do POS).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operador_nome' => ['required', 'string', 'max:100'],
            'local'         => ['required', 'string', 'max:100'],
        ]);

        ChamadaComissao::create($data);

        return response()->json(['ok' => true]);
    }

    /**
     * Lista de chamadas pendentes para polling no back-office.
     */
    public function pendentes(): JsonResponse
    {
        $chamadas = ChamadaComissao::pendentes()
            ->orderBy('created_at')
            ->get()
            ->map(fn (ChamadaComissao $c) => [
                'id'            => $c->id,
                'operador_nome' => $c->operador_nome,
                'local'         => $c->local,
                'criado_em'     => $c->created_at->diffForHumans(),
            ]);

        return response()->json(['chamadas' => $chamadas]);
    }

    /**
     * Membro da comissão marca a chamada como atendida.
     */
    public function atender(Request $request, ChamadaComissao $chamada): JsonResponse
    {
        $chamada->update([
            'atendida_por_id' => $request->user()->id,
            'atendida_em'     => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
