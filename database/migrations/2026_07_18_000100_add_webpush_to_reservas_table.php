<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->string('token', 16)->nullable()->unique()->after('mesa_atribuida');
            $table->text('push_subscription')->nullable()->after('token');
        });

        // Gerar token para reservas já existentes
        \DB::table('reservas')->whereNull('token')->orderBy('id')->each(function ($r) {
            \DB::table('reservas')->where('id', $r->id)->update(['token' => Str::random(12)]);
        });
    }

    public function down(): void
    {
        Schema::table('reservas', function (Blueprint $table) {
            $table->dropColumn(['token', 'push_subscription']);
        });
    }
};
