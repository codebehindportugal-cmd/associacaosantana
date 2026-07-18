<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldos_conta', function (Blueprint $table) {
            $table->id();
            $table->enum('conta', ['banco', 'prazo'])->unique();
            $table->decimal('valor', 10, 2)->default(0);
            $table->date('data');
            $table->string('notas', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldos_conta');
    }
};
