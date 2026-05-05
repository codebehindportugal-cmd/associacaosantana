<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['Gerente', 'gerente@santana.pt', 'gerente'],
            ['Staff Bar', 'bar@santana.pt', 'staff_bar'],
            ['Staff Cozinha', 'cozinha@santana.pt', 'staff_cozinha'],
            ['Tesoureiro', 'tesoureiro@santana.pt', 'tesoureiro'],
        ])->each(function ($row) {
            $user = User::firstOrCreate(['email' => $row[1]], ['name' => $row[0], 'password' => Hash::make('password'), 'email_verified_at' => now()]);
            $user->syncRoles([$row[2]]);
        });
    }
}
