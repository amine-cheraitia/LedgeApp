<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercice_id')->constrained('exercices');
            $table->foreignId('prestation_id')->constrained('prestations');
            $table->string('reference')->unique();
            $table->decimal('prix_ht', 12, 2);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', ['en_cours', 'terminee', 'suspendue', 'annulee'])->default('en_cours');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('mission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_mission')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_user');
        Schema::dropIfExists('missions');
    }
};
