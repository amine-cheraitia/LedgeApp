<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pdf_path : jamais renseigne (PDF genere a la volee).
        // facture_origine_id : vestige du design avoir-as-facture, remplace par la table avoirs.
        // La FK auto-referente doit etre supprimee avant la colonne (etape separee).
        Schema::table('factures', function (Blueprint $table) {
            $table->dropForeign(['facture_origine_id']);
        });

        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn(['pdf_path', 'facture_origine_id']);
        });

        // Flag jamais positionne a true (les commentaires de tache sont internes).
        Schema::table('tache_commentaires', function (Blueprint $table) {
            $table->dropColumn('visible_portail');
        });

        // Doublon de prix_ht (meme valeur) : on ne garde que prix_ht (aligne sur mission).
        Schema::table('devis', function (Blueprint $table) {
            $table->dropColumn('montant_ht');
        });

        // Table GED jamais alimentee.
        Schema::dropIfExists('documents');
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->string('pdf_path')->nullable();
            $table->foreignId('facture_origine_id')->nullable()->constrained('factures')->nullOnDelete();
        });

        Schema::table('tache_commentaires', function (Blueprint $table) {
            $table->boolean('visible_portail')->default(false);
        });

        Schema::table('devis', function (Blueprint $table) {
            $table->decimal('montant_ht', 12, 2)->default(0);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('nom');
            $table->string('type')->nullable();
            $table->string('chemin');
            $table->unsignedBigInteger('taille')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('partage_client')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
