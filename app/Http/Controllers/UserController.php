<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PosSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:users.ver')->only(['index', 'edit']);
        $this->middleware('permission:users.criar')->only(['store', 'storePos']);
        $this->middleware('permission:users.editar')->only(['update', 'updatePos']);
        $this->middleware('permission:users.apagar')->only(['destroy', 'destroyPos']);
    }

    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::with('roles')->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->pluck('name'),
            'posTerminais' => PosSession::orderBy('tipo')->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'exists:roles,name'],
        ]);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Utilizador criado.');
    }

    public function edit(User $user): Response
    {
        return $this->index();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'exists:roles,name'],
        ]);
        $user->update(collect($data)->only(['name', 'email'])->all());
        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Utilizador atualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ((int) $request->user()->id === (int) $user->id) {
            return back()->withErrors(['user' => 'Nao podes apagar o teu proprio utilizador.']);
        }

        $user->delete();

        return back()->with('success', 'Utilizador apagado.');
    }

    public function storePos(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'pin' => ['required', 'string', 'min:4', 'max:20'],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', 'in:bar,cafe,restaurante,reservas,cotas'],
            'ativo' => ['boolean'],
        ]);

        PosSession::create($data + ['ativo' => true]);

        return back()->with('success', 'Acesso POS criado.');
    }

    public function updatePos(Request $request, PosSession $pos): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'pin' => ['nullable', 'string', 'min:4', 'max:20'],
            'localizacao' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', 'in:bar,cafe,restaurante,reservas,cotas'],
            'ativo' => ['boolean'],
        ]);

        if (empty($data['pin'])) {
            unset($data['pin']);
        }

        $pos->update($data);

        return back()->with('success', 'Acesso POS atualizado.');
    }

    public function destroyPos(PosSession $pos): RedirectResponse
    {
        $pos->delete();

        return back()->with('success', 'Acesso POS apagado.');
    }
}
