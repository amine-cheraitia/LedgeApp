<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rattrapage de donnees : recalcule statut_paiement (et montant_paye) de
     * toutes les factures selon la regle actuelle — paiements + avoirs >= TTC
     * => solde. Des factures anterieures a la prise en compte des avoirs dans
     * le recalcul pouvaient garder un statut 'en_attente' avec un reste a
     * payer nul : elles apparaissaient en creances et pouvaient etre relancees
     * pour 0 DA.
     */
    public function up(): void
    {
        DB::table('factures')->orderBy('id')->chunkById(200, function ($factures) {
            foreach ($factures as $facture) {
                $paye = (float) DB::table('paiements')->where('facture_id', $facture->id)->sum('montant');
                $avoirs = (float) DB::table('avoirs')->where('facture_origine_id', $facture->id)->sum('montant_ttc');

                $statut = 'en_attente';
                if ($paye + $avoirs >= (float) $facture->montant_ttc) {
                    $statut = 'solde';
                } elseif ($paye > 0) {
                    $statut = 'partiel';
                }

                DB::table('factures')->where('id', $facture->id)->update([
                    'montant_paye' => $paye,
                    'statut_paiement' => $statut,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Rien a restaurer : statut_paiement et montant_paye sont des champs
        // derives, recalcules ici selon la regle canonique — l'etat anterieur
        // etait precisement la donnee incoherente que cette migration corrige.
    }
};
