<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                'roles' => fn () => $request->user()?->getRoleNames()->values() ?? [],
                'permissions' => fn () => $request->user()?->getAllPermissions()->pluck('name')->values() ?? [],
            ],
            'urgentes_count'      => fn () => \App\Models\PedidoItem::urgentes()->count(),
            'chamadas_comissao'   => fn () => $request->user()
                ? (function () {
                    try {
                        return \App\Models\ChamadaComissao::pendentes()
                            ->orderBy('created_at')
                            ->get()
                            ->map(fn ($c) => [
                                'id'            => $c->id,
                                'operador_nome' => $c->operador_nome,
                                'local'         => $c->local,
                                'criado_em'     => $c->created_at->diffForHumans(),
                            ]);
                    } catch (\Throwable) {
                        return [];
                    }
                })()
                : [],
            'restaurante' => [
                'mostrar_estado_items' => (bool) config('restaurante.mostrar_estado_items'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'avisoCliente' => fn () => $request->session()->get('avisoCliente'),
                'avisoFuncionario' => fn () => $request->session()->get('avisoFuncionario'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
