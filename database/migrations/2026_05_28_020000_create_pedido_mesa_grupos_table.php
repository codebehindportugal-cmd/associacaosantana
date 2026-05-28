<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_mesa_grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pedido_id', 'mesa_id']);
        });

        DB::table('mesas')->where('capacidade', '>', 10)->update(['capacidade' => 10]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_mesa_grupos');
    }
};
