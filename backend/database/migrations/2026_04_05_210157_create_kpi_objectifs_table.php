<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kpi_objectifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercice_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['ca_ht', 'missions_cloturees', 'delai_moyen_facturation']);
            $table->decimal('valeur', 12, 2);
            $table->timestamps();

            $table->unique(['user_id', 'exercice_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_objectifs');
    }
};
