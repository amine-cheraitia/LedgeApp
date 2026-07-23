<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            // Snapshot du taux applique a la creation (immuable), parite avec les factures.
            $table->decimal('taux_tva', 5, 2)->default(0)->after('montant_ht');
            $table->foreignId('tva_taux_id')->nullable()->after('taux_tva')->constrained('tva_taux')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropForeign(['tva_taux_id']);
            $table->dropColumn(['taux_tva', 'tva_taux_id']);
        });
    }
};
