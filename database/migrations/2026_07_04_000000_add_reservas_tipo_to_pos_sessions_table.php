<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pos_sessions MODIFY tipo ENUM('bar', 'cafe', 'restaurante', 'reservas', 'cotas') NOT NULL DEFAULT 'bar'");

        if (! DB::table('pos_sessions')->where('nome', 'Reservas')->exists()) {
            DB::table('pos_sessions')->insert([
                'nome' => 'Reservas',
                'pin' => Hash::make('2222'),
                'localizacao' => 'Restaurante',
                'tipo' => 'reservas',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('pos_sessions')->where('nome', 'Reservas')->where('tipo', 'reservas')->delete();
        DB::table('pos_sessions')->where('tipo', 'reservas')->update(['tipo' => 'restaurante']);
        DB::statement("ALTER TABLE pos_sessions MODIFY tipo ENUM('bar', 'cafe', 'restaurante', 'cotas') NOT NULL DEFAULT 'bar'");
    }
};
