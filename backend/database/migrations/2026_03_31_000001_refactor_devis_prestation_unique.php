<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('devis_lignes');

        Schema::table('devis', function (Blueprint $table) {
            $table->foreignId('prestation_id')->nullable()->after('entreprise_id')->constrained('prestations')->nullOnDelete();
            $table->decimal('prix_ht', 12, 2)->default(0)->after('montant_ht');
        });
    }

    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropForeign(['prestation_id']);
            $table->dropColumn(['prestation_id', 'prix_ht']);
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
};
