<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relances', function (Blueprint $table) {
            $table->enum('statut', ['en_attente', 'envoyee', 'echec', 'annulee'])
                ->default('en_attente')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('relances', function (Blueprint $table) {
            $table->enum('statut', ['en_attente', 'envoyee', 'echec'])
                ->default('en_attente')
                ->change();
        });
    }
};
