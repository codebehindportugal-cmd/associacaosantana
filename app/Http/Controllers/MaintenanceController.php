<?php

namespace App\Http\Controllers;

use App\Models\CaixaDiaria;
use App\Models\AuditLog;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function cleanup(Request $request): Response
    {
        $this->authorizeMaintenance($request);

        $filters = $this->cleanupFilters($request);

        return Inertia::render('Manutencao/Limpeza', [
            'filters' => $filters,
            'preview' => $this->cleanupPreview($filters),
        ]);
    }

    public function destroyCleanup(Request $request): RedirectResponse
    {
        $this->authorizeMaintenance($request);

        $data = $request->validate([
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
            'tipo' => ['required', 'in:pedidos,caixas,ambos'],
            'manter_pedido_id' => ['nullable', 'integer', 'exists:pedidos,id'],
            'apenas_relatorios' => ['nullable', 'boolean'],
            'confirmacao' => ['required', 'string'],
        ]);

        if ($data['confirmacao'] !== 'APAGAR DADOS') {
            return back()->withErrors(['confirmacao' => 'Escreve exatamente APAGAR DADOS para confirmar.'])->withInput();
        }

        $filters = [
            'data_inicio' => Carbon::parse($data['data_inicio'])->toDateString(),
            'data_fim' => Carbon::parse($data['data_fim'])->toDateString(),
            'tipo' => $data['tipo'],
            'manter_pedido_id' => $data['manter_pedido_id'] ?? null,
            'apenas_relatorios' => $request->boolean('apenas_relatorios'),
        ];

        $deleted = DB::transaction(function () use ($filters) {
            $deleted = ['pedidos' => 0, 'caixas' => 0];

            if (in_array($filters['tipo'], ['pedidos', 'ambos'], true)) {
                $pedidoIds = $this->pedidoCleanupQuery($filters)->pluck('id');

                if ($pedidoIds->isNotEmpty()) {
                    DB::table('print_jobs')
                        ->where('printable_type', Pedido::class)
                        ->whereIn('printable_id', $pedidoIds)
                        ->delete();

                    Pedido::whereIn('id', $pedidoIds)->get()->each(function (Pedido $pedido) use (&$deleted) {
                        $pedido->delete();
                        $deleted['pedidos']++;
                    });
                }
            }

            if (in_array($filters['tipo'], ['caixas', 'ambos'], true)) {
                $this->caixaCleanupQuery($filters)->get()->each(function (CaixaDiaria $caixa) use (&$deleted) {
                    $caixa->delete();
                    $deleted['caixas']++;
                });
            }

            return $deleted;
        });

        Log::warning('Limpeza manual de dados executada no backoffice.', [
            'user_id' => $request->user()->id,
            'filters' => $filters,
            'deleted' => $deleted,
        ]);

        return back()->with('success', "Limpeza concluída: {$deleted['pedidos']} pedidos e {$deleted['caixas']} caixas apagados.");
    }

    public function logs(Request $request): Response
    {
        $this->authorizeMaintenance($request);

        $filters = [
            'data_inicio' => ($request->date('data_inicio') ?? today()->subDays(7))->toDateString(),
            'data_fim' => ($request->date('data_fim') ?? today())->toDateString(),
            'acao' => $request->input('acao', 'todas'),
            'modelo' => $request->input('modelo', 'todos'),
            'funcionario' => $request->input('funcionario'),
        ];

        $query = AuditLog::with('user')
            ->whereBetween(DB::raw('DATE(created_at)'), [$filters['data_inicio'], $filters['data_fim']])
            ->when($filters['acao'] !== 'todas', fn ($query) => $query->where('action', $filters['acao']))
            ->when($filters['modelo'] !== 'todos', fn ($query) => $query->where('auditable_type', $filters['modelo']))
            ->when($filters['funcionario'], fn ($query, $name) => $query->where('actor_name', 'like', '%'.$name.'%'))
            ->latest();

        $logs = $query
            ->limit(250)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                'actor_name' => $log->actor_name ?: 'Sistema',
                'actor_type' => $log->actor_type,
                'action' => $log->action,
                'model' => class_basename($log->auditable_type),
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'auditable_label' => $log->auditable_label,
                'old_values' => $log->old_values ?? [],
                'new_values' => $log->new_values ?? [],
                'url' => $log->url,
                'ip' => $log->ip,
            ]);

        return Inertia::render('Manutencao/Logs', [
            'filters' => $filters,
            'logs' => $logs,
            'modelos' => AuditLog::query()
                ->select('auditable_type')
                ->distinct()
                ->orderBy('auditable_type')
                ->pluck('auditable_type')
                ->map(fn (string $type) => ['value' => $type, 'label' => class_basename($type)])
                ->values(),
        ]);
    }

    private function authorizeMaintenance(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'gerente']), 403);
    }

    private function cleanupFilters(Request $request): array
    {
        $inicio = $request->date('data_inicio') ?? today();
        $fim = $request->date('data_fim') ?? today();

        return [
            'data_inicio' => $inicio->toDateString(),
            'data_fim' => $fim->toDateString(),
            'tipo' => $request->input('tipo', 'ambos'),
            'manter_pedido_id' => $request->input('manter_pedido_id'),
            'apenas_relatorios' => $request->has('apenas_relatorios') ? $request->boolean('apenas_relatorios') : true,
        ];
    }

    private function cleanupPreview(array $filters): array
    {
        $pedidoQuery = $this->pedidoCleanupQuery($filters);
        $pedidoIds = (clone $pedidoQuery)->pluck('id');
        $caixaQuery = $this->caixaCleanupQuery($filters);

        return [
            'pedidos' => [
                'count' => $pedidoIds->count(),
                'total' => (float) (clone $pedidoQuery)->sum('total'),
                'items' => $pedidoIds->isEmpty() ? 0 : DB::table('pedido_items')->whereIn('pedido_id', $pedidoIds)->count(),
                'samples' => (clone $pedidoQuery)
                    ->latest('created_at')
                    ->limit(8)
                    ->get(['id', 'tipo', 'estado', 'total', 'created_at', 'ponto_bar'])
                    ->map(fn (Pedido $pedido) => [
                        'id' => $pedido->id,
                        'tipo' => $pedido->tipo,
                        'estado' => $pedido->estado,
                        'total' => (float) $pedido->total,
                        'created_at' => $pedido->created_at?->format('Y-m-d H:i'),
                        'ponto' => $pedido->ponto_bar ?: 'Restaurante',
                    ]),
            ],
            'caixas' => [
                'count' => (clone $caixaQuery)->count(),
                'fundo_maneio' => (float) (clone $caixaQuery)->sum('fundo_maneio'),
                'samples' => (clone $caixaQuery)
                    ->latest('data')
                    ->limit(8)
                    ->get(['id', 'data', 'ponto', 'estado', 'fundo_maneio', 'valor_contado'])
                    ->map(fn (CaixaDiaria $caixa) => [
                        'id' => $caixa->id,
                        'data' => $caixa->data?->toDateString(),
                        'ponto' => $caixa->ponto,
                        'estado' => $caixa->estado,
                        'fundo_maneio' => (float) $caixa->fundo_maneio,
                        'valor_contado' => $caixa->valor_contado !== null ? (float) $caixa->valor_contado : null,
                    ]),
            ],
        ];
    }

    private function pedidoCleanupQuery(array $filters)
    {
        return Pedido::query()
            ->whereBetween(DB::raw('DATE(created_at)'), [$filters['data_inicio'], $filters['data_fim']])
            ->when($filters['apenas_relatorios'], fn ($query) => $query->where(fn ($q) => $q->where('estado', 'entregue')->orWhere('pago_antecipado', true)))
            ->when($filters['manter_pedido_id'], fn ($query, $id) => $query->where('id', '<>', $id));
    }

    private function caixaCleanupQuery(array $filters)
    {
        return CaixaDiaria::query()
            ->whereBetween('data', [$filters['data_inicio'], $filters['data_fim']]);
    }

}
