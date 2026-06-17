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
        Schema::table('missions', function (Blueprint $table) {
            $table->string('convention_numero')->nullable()->unique()->after('reference');
            $table->string('mandat_numero')->nullable()->unique()->after('convention_numero');
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn(['convention_numero', 'mandat_numero']);
        });
    }
};
