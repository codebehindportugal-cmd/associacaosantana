<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pedidos')
            ->whereNull('cliente_token')
            ->orderBy('id')
            ->select('id')
            ->each(function ($pedido) {
                DB::table('pedidos')
                    ->where('id', $pedido->id)
                    ->update(['cliente_token' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        //
    }
};
