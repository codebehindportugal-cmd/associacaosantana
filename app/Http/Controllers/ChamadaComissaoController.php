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
     * Membro da comissão marca a chamada como atendida
     * (no back-office ou num POS em modo comissão).
     */
    public function atender(Request $request, ChamadaComissao $chamada): JsonResponse
    {
        $data = $request->validate([
            'nome' => ['nullable', 'string', 'max:100'],
        ]);

        $chamada->update([
            'atendida_por_id'   => $request->user()?->id,
            'atendida_por_nome' => $data['nome'] ?? $request->user()?->name ?? session('pos_comissao_nome'),
            'atendida_em'       => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Chamadas pendentes vistas de um POS em modo comissão.
     */
    public function pendentesPos(): JsonResponse
    {
        if (! session('pos_comissao')) {
            return response()->json(['chamadas' => []]);
        }

        return $this->pendentes();
    }

    public function atenderPos(Request $request, ChamadaComissao $chamada): JsonResponse
    {
        abort_unless((bool) session('pos_comissao'), 403);

        return $this->atender($request, $chamada);
    }
}
