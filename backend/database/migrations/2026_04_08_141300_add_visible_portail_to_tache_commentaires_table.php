<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tache_commentaires', function (Blueprint $table) {
            $table->boolean('visible_portail')->default(false)->after('contenu');
        });
    }

    public function down(): void
    {
        Schema::table('tache_commentaires', function (Blueprint $table) {
            $table->dropColumn('visible_portail');
        });
    }
};
