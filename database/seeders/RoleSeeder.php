<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'mesas.ver', 'mesas.criar', 'mesas.editar', 'mesas.apagar',
            'pedidos.ver', 'pedidos.criar', 'pedidos.editar', 'pedidos.apagar', 'pedidos.gerir-estado',
            'socios.ver', 'socios.criar', 'socios.editar', 'socios.apagar',
            'cotas.ver', 'cotas.criar', 'cotas.editar', 'cotas.apagar', 'cotas.gerar', 'cotas.exportar',
            'reservas.ver', 'reservas.criar', 'reservas.editar', 'reservas.apagar',
            'dashboard.ver', 'relatorios.ver',
            'users.ver', 'users.criar', 'users.editar', 'users.apagar',
        ];

        collect($permissions)->each(fn ($permission) => Permission::firstOrCreate(['name' => $permission]));

        $roles = [
            'admin' => $permissions,
            'gerente' => array_values(array_filter($permissions, fn ($p) => ! str_starts_with($p, 'users.'))),
            'staff_bar' => ['dashboard.ver', 'mesas.ver', 'pedidos.ver', 'pedidos.criar', 'pedidos.editar', 'pedidos.gerir-estado'],
            'staff_cozinha' => ['pedidos.ver', 'pedidos.editar', 'pedidos.gerir-estado'],
            'tesoureiro' => ['dashboard.ver', 'socios.ver', 'socios.criar', 'socios.editar', 'cotas.ver', 'cotas.criar', 'cotas.editar', 'cotas.gerar', 'cotas.exportar'],
        ];

        foreach ($roles as $name => $rolePermissions) {
            Role::firstOrCreate(['name' => $name])->syncPermissions($rolePermissions);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@santana.pt'],
            ['name' => 'Administrador', 'password' => Hash::make('password'), 'email_verified_at' => now()]
        );
        $admin->syncRoles(['admin']);
    }
}
