<?php

use App\Models\Mesa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Alter localizacao column to varchar to support new values
        DB::statement("ALTER TABLE `mesas` MODIFY `localizacao` VARCHAR(50) NOT NULL DEFAULT 'interior'");
        
        // Sala (mesas centrais) - mapa_x entre 31-87
        Mesa::whereIn('numero', range(1, 32))->update(['localizacao' => 'Sala']);
        
        // Balcão - lado direito (mapa_x >= 90)
        Mesa::whereIn('numero', [33, 34, 35, 36, 37, 38, 39])->update(['localizacao' => 'Balcão']);
        
        // Secretárias - lado esquerdo (mapa_x <= 12)
        Mesa::whereIn('numero', [40, 41])->update(['localizacao' => 'Secretárias']);
    }

    public function down(): void
    {
        // Revert column back to enum (using common values)
        DB::statement("ALTER TABLE `mesas` MODIFY `localizacao` ENUM('interior', 'exterior', 'bar') DEFAULT 'interior'");
        Mesa::query()->update(['localizacao' => 'interior']);
    }
};
