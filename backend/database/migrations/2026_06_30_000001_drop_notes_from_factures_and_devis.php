<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('devis', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });

        Schema::table('devis', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });
    }
};
