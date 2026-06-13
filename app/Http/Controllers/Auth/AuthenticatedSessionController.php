<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended($this->homePathFor($request->user()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function homePathFor($user): string
    {
        if ($user->can('dashboard.ver')) {
            return route('dashboard', absolute: false);
        }

        if ($user->can('pedidos.criar')) {
            return route('pedidos.create', absolute: false);
        }

        if ($user->can('pedidos.ver')) {
            return route('pedidos.index', absolute: false);
        }

        if ($user->can('caixa.ver')) {
            return route('caixa.index', absolute: false);
        }

        if ($user->can('bar.ver')) {
            return route('pos.login', absolute: false);
        }

        return route('pos.login', absolute: false);
    }
}
