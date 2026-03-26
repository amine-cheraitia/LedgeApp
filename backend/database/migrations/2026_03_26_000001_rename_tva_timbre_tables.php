<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Renommer les tables
        Schema::rename('tva_rates', 'tva_taux');
        Schema::rename('timbre_rates', 'timbre_taux');

        // Renommer les FK dans factures
        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['tva_rate_id']);
            $table->dropForeign(['timbre_rate_id']);
            $table->renameColumn('tva_rate_id', 'tva_taux_id');
            $table->renameColumn('timbre_rate_id', 'timbre_taux_id');
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->foreign('tva_taux_id')->references('id')->on('tva_taux');
            $table->foreign('timbre_taux_id')->references('id')->on('timbre_taux');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['tva_taux_id']);
            $table->dropForeign(['timbre_taux_id']);
            $table->renameColumn('tva_taux_id', 'tva_rate_id');
            $table->renameColumn('timbre_taux_id', 'timbre_rate_id');
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->foreign('tva_rate_id')->references('id')->on('tva_rates');
            $table->foreign('timbre_rate_id')->references('id')->on('timbre_rates');
        });

        Schema::rename('tva_taux', 'tva_rates');
        Schema::rename('timbre_taux', 'timbre_rates');
    }
};
