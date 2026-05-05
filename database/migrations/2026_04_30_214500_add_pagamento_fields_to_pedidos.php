<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->decimal('valor_recebido', 10, 2)->nullable()->after('total');
            $table->decimal('troco', 10, 2)->default(0)->after('valor_recebido');
            $table->decimal('doacao', 10, 2)->default(0)->after('troco');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['valor_recebido', 'troco', 'doacao']);
        });
    }
};
