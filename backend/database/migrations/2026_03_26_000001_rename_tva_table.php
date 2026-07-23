<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renommer la table
        Schema::rename('tva_rates', 'tva_taux');

        // Renommer la FK dans factures
        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['tva_rate_id']);
            $table->renameColumn('tva_rate_id', 'tva_taux_id');
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->foreign('tva_taux_id')->references('id')->on('tva_taux');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['tva_taux_id']);
            $table->renameColumn('tva_taux_id', 'tva_rate_id');
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->foreign('tva_rate_id')->references('id')->on('tva_rates');
        });

        Schema::rename('tva_taux', 'tva_rates');
    }
};
