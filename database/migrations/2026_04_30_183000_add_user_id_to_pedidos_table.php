<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('pedidos', 'user_id')) {
            return;
        }

        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('mesa_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // The current create_pedidos_table migration owns this column.
    }
};
