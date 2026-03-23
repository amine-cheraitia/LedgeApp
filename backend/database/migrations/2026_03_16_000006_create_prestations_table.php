<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('designation');
            $table->decimal('tarif_initial', 12, 2);
            $table->unsignedSmallInteger('duree_mois')->default(12);
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('regimes_fiscaux', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('designation');
            $table->decimal('indice', 5, 2)->default(1.00);
            $table->timestamps();
        });

        Schema::create('categories_entreprise', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('designation');
            $table->decimal('indice', 5, 2)->default(1.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories_entreprise');
        Schema::dropIfExists('regimes_fiscaux');
        Schema::dropIfExists('prestations');
    }
};
