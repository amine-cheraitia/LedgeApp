<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avoirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_origine_id')->constrained('factures');
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->foreignId('created_by')->constrained('users');
            $table->string('numero', 20)->unique();
            $table->date('date_avoir');
            $table->decimal('montant_ht', 12, 2);
            $table->decimal('taux_tva_snapshot', 5, 2)->default(0);
            $table->decimal('montant_tva', 12, 2)->default(0);
            $table->decimal('montant_ttc', 12, 2);
            $table->text('motif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avoirs');
    }
};
