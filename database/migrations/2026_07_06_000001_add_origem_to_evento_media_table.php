<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evento_media', function (Blueprint $table) {
            $table->string('origem')->default('evento')->after('titulo');
            $table->string('url_origem')->nullable()->after('origem');
        });
    }

    public function down(): void
    {
        Schema::table('evento_media', function (Blueprint $table) {
            $table->dropColumn(['origem', 'url_origem']);
        });
    }
};
