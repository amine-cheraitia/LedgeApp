<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('nom');
            $table->string('type')->nullable(); // mandat, convention, autre
            $table->string('chemin');
            $table->unsignedBigInteger('taille')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('partage_client')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
