<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('niveau')->default(1);
            $table->enum('type', ['automatique', 'manuelle'])->default('automatique');
            $table->string('email_destinataire');
            $table->dateTime('envoyee_le')->nullable();
            $table->enum('statut', ['en_attente', 'envoyee', 'echec'])->default('en_attente');
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relances');
    }
};
