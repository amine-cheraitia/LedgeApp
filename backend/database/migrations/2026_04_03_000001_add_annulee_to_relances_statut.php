<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE relances MODIFY COLUMN statut ENUM('en_attente', 'envoyee', 'echec', 'annulee') NOT NULL DEFAULT 'en_attente'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE relances MODIFY COLUMN statut ENUM('en_attente', 'envoyee', 'echec') NOT NULL DEFAULT 'en_attente'");
        }
    }
};
