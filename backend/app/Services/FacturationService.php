<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Prestation;
use App\Models\Setting;
use App\Models\TimbreTaux;
use App\Models\TvaTaux;
use DomainException;
use Illuminate\Support\Facades\DB;

class FacturationService
{
    /**
     * Genere le prochain numero sequentiel pour un type de document.
     * Utilise lockForUpdate pour eviter les doublons en concurrence.
     */
    public function genererNumero(string $prefixe, string $table, Exercice $exercice, string $colonne = 'numero'): string
    {
        return DB::transaction(function () use ($prefixe, $table, $exercice, $colonne) {
            $annee = $exercice->annee;
            $pattern = "{$prefixe}{$annee}-%";

            $query = DB::table($table)
                ->where($colonne, 'like', $pattern);

            if (DB::getDriverName() !== 'sqlite') {
                $query->lockForUpdate();
            }

            $dernierNumero = $query->max($colonne);

            if ($dernierNumero) {
                $sequence = (int) substr($dernierNumero, strrpos($dernierNumero, '-') + 1);
                $sequence++;
            } else {
                $sequence = 1;
            }

            return sprintf('%s%d-%03d', $prefixe, $annee, $sequence);
        });
    }

    /**
     * Cree un devis pour une seule prestation.
     * Le prix HT est calcule automatiquement via la grille tarifaire (immuable).
     */
    public function creerDevis(array $data, int $userId): Devis
    {
        return DB::transaction(function () use ($data, $userId) {
            $exercice = Exercice::current();
            $prefixe = Setting::get('devis_prefixe', 'DV');

            $entreprise = Entreprise::findOrFail($data['entreprise_id']);
            $prestation = Prestation::findOrFail($data['prestation_id']);

            // Prix HT calcule une seule fois via la grille tarifaire — immuable
            $prixHt = $prestation->calculerPrixHt($entreprise->regime_fiscal, $entreprise->categorie);

            $tvaTaux = TvaTaux::enVigueurLe($data['date_devis']);

            $montantTva = $tvaTaux ? round($prixHt * (float) $tvaTaux->taux / 100, 2) : 0;
            // Le timbre fiscal ne s'applique pas sur les devis — uniquement sur les factures
            $montantTtc = round($prixHt + $montantTva, 2);

            $devis = Devis::create([
                'entreprise_id' => $entreprise->id,
                'prestation_id' => $prestation->id,
                'exercice_id' => $exercice->id,
                'created_by' => $userId,
                'numero' => $this->genererNumero($prefixe, 'devis', $exercice),
                'date_devis' => $data['date_devis'],
                'date_validite' => $data['date_validite'],
                'prix_ht' => $prixHt,
                'montant_ht' => $prixHt,
                'montant_tva' => $montantTva,
                'montant_timbre' => 0,
                'montant_ttc' => $montantTtc,
                'statut' => 'brouillon',
                'notes' => $data['notes'] ?? null,
            ]);

            return $devis->load('prestation', 'entreprise');
        });
    }

    public function mettreAJourDevis(Devis $devis, array $data): Devis
    {
        if ($devis->statut !== 'brouillon') {
            throw new DomainException('Seuls les devis en brouillon peuvent etre modifies.');
        }

        $devis->update($data);

        return $devis->load('prestation', 'entreprise');
    }

    public function envoyerDevis(Devis $devis): Devis
    {
        if ($devis->statut !== 'brouillon') {
            throw new DomainException('Seuls les devis en brouillon peuvent etre envoyes.');
        }

        $devis->update(['statut' => 'envoye']);

        return $devis->load('prestation', 'entreprise');
    }

    public function accepterDevis(Devis $devis): Devis
    {
        if ($devis->statut !== 'envoye') {
            throw new DomainException('Seuls les devis envoyes peuvent etre acceptes.');
        }

        $devis->update(['statut' => 'accepte']);

        return $devis->load('prestation', 'entreprise');
    }

    public function refuserDevis(Devis $devis): Devis
    {
        if ($devis->statut !== 'envoye') {
            throw new DomainException('Seuls les devis envoyes peuvent etre refuses.');
        }

        $devis->update(['statut' => 'refuse']);

        return $devis->load('prestation', 'entreprise');
    }

    public function supprimerDevis(Devis $devis): void
    {
        if ($devis->statut !== 'brouillon') {
            throw new DomainException('Seuls les devis en brouillon peuvent etre supprimes.');
        }

        $devis->delete();
    }

    /**
     * Cree une facture avec ses lignes.
     * Les snapshots TVA/timbre sont figes a la creation — regle immuable.
     */
    public function creerFacture(array $data, array $lignes, int $userId): Facture
    {
        return DB::transaction(function () use ($data, $lignes, $userId) {
            $exercice = Exercice::current();
            $type = $data['type'] ?? 'FF';
            $prefixe = $type === 'FA'
                ? Setting::get('avoir_prefixe', 'FA')
                : Setting::get('facture_prefixe', 'FF');

            $montantHt = collect($lignes)->sum(fn (array $l) => $l['quantite'] * $l['prix_unitaire_ht']);

            $dateFacture = $data['date_facture'];
            $tvaTaux = TvaTaux::enVigueurLe($dateFacture);
            $timbreTaux = TimbreTaux::enVigueurLe($dateFacture);

            $tauxTva = $tvaTaux ? (float) $tvaTaux->taux : 0;
            $montantTva = round($montantHt * $tauxTva / 100, 2);
            $montantTimbre = $timbreTaux ? $timbreTaux->calculer($montantHt) : 0;
            $montantTtc = round($montantHt + $montantTva + $montantTimbre, 2);

            $facture = Facture::create([
                'entreprise_id' => $data['entreprise_id'],
                'exercice_id' => $exercice->id,
                'mission_id' => $data['mission_id'] ?? null,
                'devis_id' => $data['devis_id'] ?? null,
                'created_by' => $userId,
                'tva_taux_id' => $tvaTaux?->id,
                'timbre_taux_id' => $timbreTaux?->id,
                'numero' => $this->genererNumero($prefixe, 'factures', $exercice),
                'type' => $type,
                'facture_origine_id' => $data['facture_origine_id'] ?? null,
                'date_facture' => $dateFacture,
                'date_echeance' => $data['date_echeance'],
                'montant_ht' => $montantHt,
                'taux_tva' => $tauxTva,
                'montant_tva' => $montantTva,
                'montant_timbre' => $montantTimbre,
                'montant_ttc' => $montantTtc,
                'montant_paye' => 0,
                'statut_paiement' => 'en_attente',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lignes as $index => $ligne) {
                FactureLigne::create([
                    'facture_id' => $facture->id,
                    'prestation_id' => $ligne['prestation_id'] ?? null,
                    'designation' => $ligne['designation'],
                    'quantite' => $ligne['quantite'],
                    'prix_unitaire_ht' => $ligne['prix_unitaire_ht'],
                    'total_ht' => round($ligne['quantite'] * $ligne['prix_unitaire_ht'], 2),
                    'ordre' => $index + 1,
                ]);
            }

            return $facture->load('lignes', 'entreprise');
        });
    }

    /**
     * Recalcule le statut de paiement d'une facture apres un paiement.
     */
    public function recalculerStatutPaiement(Facture $facture): void
    {
        $totalPaye = $facture->paiements()->sum('montant');
        $facture->montant_paye = $totalPaye;

        if ($totalPaye >= (float) $facture->montant_ttc) {
            $facture->statut_paiement = 'solde';
        } elseif ($totalPaye > 0) {
            $facture->statut_paiement = 'partiel';
        } else {
            $facture->statut_paiement = 'en_attente';
        }

        $facture->save();
    }
}
