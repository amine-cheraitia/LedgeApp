<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->foreignId('created_by')->constrained('users');
            $table->string('numero')->unique();
            $table->date('date_devis');
            $table->date('date_validite')->nullable();
            $table->decimal('montant_ht', 12, 2)->default(0);
            $table->decimal('montant_tva', 12, 2)->default(0);
            $table->decimal('montant_ttc', 12, 2)->default(0);
            $table->enum('statut', ['brouillon', 'envoye', 'accepte', 'refuse', 'expire'])->default('brouillon');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('devis_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('devis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prestation_id')->nullable()->constrained('prestations')->nullOnDelete();
            $table->string('designation');
            $table->decimal('quantite', 8, 2)->default(1);
            $table->decimal('prix_unitaire_ht', 12, 2);
            $table->decimal('total_ht', 12, 2);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devis_lignes');
        Schema::dropIfExists('devis');
    }
};
