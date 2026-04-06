<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (tests in-memory) ne supporte pas MODIFY COLUMN — enum non enforced de toute façon
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('kpi_objectifs')->where('type', 'delai_moyen_facturation')->delete();
        DB::statement("ALTER TABLE kpi_objectifs MODIFY COLUMN type ENUM('ca_ht', 'missions_cloturees', 'taches_terminees') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('kpi_objectifs')->where('type', 'taches_terminees')->delete();
        DB::statement("ALTER TABLE kpi_objectifs MODIFY COLUMN type ENUM('ca_ht', 'missions_cloturees', 'delai_moyen_facturation') NOT NULL");
    }
};
