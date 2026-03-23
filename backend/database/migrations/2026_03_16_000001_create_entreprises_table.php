<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id();
            $table->string('raison_sociale');
            $table->string('nif')->nullable()->unique();
            $table->string('nis')->nullable()->unique();
            $table->string('num_rc')->nullable();
            $table->string('article_imposition')->nullable();
            $table->enum('regime_fiscal', ['forfait', 'reel'])->default('forfait');
            $table->enum('categorie', ['TPE', 'PME', 'GE'])->default('TPE');
            $table->string('secteur_activite')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->string('wilaya')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_principal')->nullable();
            $table->enum('statut', ['prospect', 'client'])->default('prospect');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entreprises');
    }
};
