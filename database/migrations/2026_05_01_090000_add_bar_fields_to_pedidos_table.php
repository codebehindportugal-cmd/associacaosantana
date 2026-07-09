<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'tipo')) {
                $table->enum('tipo', ['restaurante', 'bar_conta', 'bar_prepago'])->default('restaurante')->after('estado');
            }

            if (! Schema::hasColumn('pedidos', 'numero_senha')) {
                $table->integer('numero_senha')->nullable()->after('tipo');
            }

            if (! Schema::hasColumn('pedidos', 'pago_antecipado')) {
                $table->boolean('pago_antecipado')->default(false)->after('numero_senha');
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE pedidos MODIFY mesa_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite accepts nullable foreign keys in fresh migrations; existing dev DBs can keep using mesa_id.
        }
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            foreach (['tipo', 'numero_senha', 'pago_antecipado'] as $column) {
                if (Schema::hasColumn('pedidos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
