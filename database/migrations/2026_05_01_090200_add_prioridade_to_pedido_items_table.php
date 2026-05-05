<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            if (! Schema::hasColumn('pedido_items', 'prioridade')) {
                $table->boolean('prioridade')->default(false)->after('secao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            if (Schema::hasColumn('pedido_items', 'prioridade')) {
                $table->dropColumn('prioridade');
            }
        });
    }
};
