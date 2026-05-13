<?php

namespace App\Http\Controllers;

use App\Models\User;
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
        $this->middleware('permission:users.criar')->only('store');
        $this->middleware('permission:users.editar')->only('update');
        $this->middleware('permission:users.apagar')->only('destroy');
    }

    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::with('roles')->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->pluck('name'),
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
            'role' => ['required', 'exists:roles,name'],
        ]);
        $user->update(collect($data)->only(['name', 'email'])->all());
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Utilizador atualizado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return back()->with('success', 'Utilizador apagado.');
    }
}
