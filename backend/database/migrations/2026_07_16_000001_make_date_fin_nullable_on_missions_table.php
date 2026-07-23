<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug decouvert par le harnais E2E : ConvertirEnMissionRequest et le formulaire
 * de conversion (frontend/src/pages/devis/DevisListPage.vue) traitent "Date fin"
 * comme optionnelle (nullable), mais la colonne missions.date_fin etait NOT NULL
 * -> violation de contrainte SQL des qu'une mission est creee sans date de fin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->date('date_fin')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->date('date_fin')->nullable(false)->change();
        });
    }
};
