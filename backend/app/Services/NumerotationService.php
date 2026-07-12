<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Exercice;
use Illuminate\Support\Facades\DB;

/**
 * Numerotation sequentielle des documents, transverse aux domaines (facturation,
 * planning). Format {prefixe}{annee}-{sequence}, reinitialisee par exercice.
 */
class NumerotationService
{
    /**
     * Genere un numero unique par exercice. Utilise lockForUpdate (hors sqlite de
     * test) pour eviter les doublons en concurrence ; une contrainte UNIQUE en base
     * sert de filet.
     */
    public function genererNumero(string $prefixe, string $table, Exercice $exercice, string $colonne = 'numero'): string
    {
        return DB::transaction(function () use ($prefixe, $table, $exercice, $colonne) {
            $annee = $exercice->annee;
            $pattern = "{$prefixe}{$annee}-%";

            $query = DB::table($table)->where($colonne, 'like', $pattern);

            if (DB::getDriverName() !== 'sqlite') {
                $query->lockForUpdate();
            }

            $dernierNumero = $query->max($colonne);

            $sequence = $dernierNumero
                ? (int) substr($dernierNumero, strrpos($dernierNumero, '-') + 1) + 1
                : 1;

            return sprintf('%s%d-%03d', $prefixe, $annee, $sequence);
        });
    }
}
