<?php

namespace App\Http\Middleware;

use App\Models\PosSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosSession
{
    private const COOKIE = 'pos_lembrar';
    private const COOKIE_DAYS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('pos_id')) {
            // Tenta restaurar a sessão a partir do cookie persistente
            if (! $this->restaurarDeCookie($request)) {
                return redirect()->route('pos.login');
            }
        }

        $tipo = session('pos_tipo');
        $path = $request->path();

        if (str_starts_with($path, 'pos-rest') && $tipo !== 'restaurante') {
            abort(403, 'Terminal nao autorizado para esta area');
        }

        if (str_starts_with($path, 'pos-reservas') && $tipo !== 'reservas') {
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

    private function restaurarDeCookie(Request $request): bool
    {
        $raw = $request->cookie(self::COOKIE);
        if (! $raw) {
            return false;
        }

        try {
            $dados = json_decode(decrypt($raw), true, 512, JSON_THROW_ON_ERROR);
            $terminal = PosSession::where('ativo', true)->find($dados['terminal_id'] ?? null);
            if (! $terminal) {
                return false;
            }

            session([
                'pos_id'         => $terminal->id,
                'pos_nome'       => $terminal->nome,
                'pos_tipo'       => $terminal->tipo,
                'pos_localizacao' => $terminal->localizacao,
                'pos_operador'   => $dados['operador'] ?? '',
            ]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Cria o cookie persistente depois do login.
     * Chamado a partir do PosLoginController.
     */
    public static function criarCookie(int $terminalId, string $operador): \Symfony\Component\HttpFoundation\Cookie
    {
        $payload = encrypt(json_encode([
            'terminal_id' => $terminalId,
            'operador'    => $operador,
        ]));

        return cookie(self::COOKIE, $payload, 60 * 24 * self::COOKIE_DAYS, httpOnly: true);
    }

    /**
     * Apaga o cookie no logout.
     */
    public static function apagarCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie()->forget(self::COOKIE);
    }
}
