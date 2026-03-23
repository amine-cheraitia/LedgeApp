<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tva_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('taux', 5, 2);
            $table->string('designation');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->enum('type', ['standard', 'reduit', 'exonere'])->default('standard');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('timbre_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('taux', 5, 2);
            $table->decimal('plafond', 10, 2);
            $table->string('designation');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timbre_rates');
        Schema::dropIfExists('tva_rates');
    }
};
