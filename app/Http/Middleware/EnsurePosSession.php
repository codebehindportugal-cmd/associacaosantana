<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('pos_id')) {
            return redirect()->route('pos.login');
        }

        $tipo = session('pos_tipo');
        $path = $request->path();

        if (str_starts_with($path, 'pos-rest') && $tipo !== 'restaurante') {
            abort(403, 'Terminal nao autorizado para esta area');
        }

        if (str_starts_with($path, 'pos-cotas') && $tipo !== 'cotas') {
            abort(403, 'Terminal nao autorizado para esta area');
        }

        if (($path === 'pos' || str_starts_with($path, 'pos/')) && $path !== 'pos/login' && ! in_array($tipo, ['bar', 'cafe'], true)) {
            abort(403, 'Terminal nao autorizado para esta area');
        }

        return $next($request);
    }
}
