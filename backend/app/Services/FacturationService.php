<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Avoir;
use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Mission;
use App\Models\Prestation;
use App\Models\Setting;
use App\Models\TvaTaux;
use Carbon\Carbon;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
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

    private const DEVIS_SORT_FIELDS = ['numero', 'date_devis', 'date_validite', 'statut', 'prix_ht'];

    private const FACTURE_SORT_FIELDS = ['numero', 'date_facture', 'date_echeance', 'montant_ttc', 'statut_paiement'];

    public function listerDevis(array $filters): LengthAwarePaginator
    {
        $sortField = in_array($filters['sort_field'] ?? '', self::DEVIS_SORT_FIELDS)
            ? $filters['sort_field']
            : 'created_at';
        $sortDir = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Devis::with('entreprise', 'prestation')
            ->when($filters['exercice_id'] ?? null, fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($filters['entreprise_id'] ?? null, fn ($q, $v) => $q->where('entreprise_id', $v))
            ->when($filters['statut'] ?? null, fn ($q, $v) => $q->where('statut', $v))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q
                ->where('numero', 'like', "%{$s}%")
                ->orWhereHas('entreprise', fn ($eq) => $eq->where('raison_sociale', 'like', "%{$s}%"))
            )
            ->orderBy($sortField, $sortDir)
            ->paginate($filters['per_page'] ?? 15);
    }

    public function listerCreances(): Collection
    {
        return Facture::with(['entreprise', 'mission.prestation'])
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->orderBy('date_echeance', 'asc')
            ->get();
    }

    public function listerFactures(array $filters): LengthAwarePaginator
    {
        $sortField = in_array($filters['sort_field'] ?? '', self::FACTURE_SORT_FIELDS)
            ? $filters['sort_field']
            : 'created_at';
        $sortDir = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Facture::with('entreprise', 'mission', 'lignes')
            ->when($filters['exercice_id'] ?? null, fn ($q, $v) => $q->where('exercice_id', $v))
            ->when($filters['entreprise_id'] ?? null, fn ($q, $v) => $q->where('entreprise_id', $v))
            ->when($filters['statut_paiement'] ?? null, fn ($q, $v) => $q->where('statut_paiement', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['search'] ?? null, fn ($q, $s) => $q
                ->where('numero', 'like', "%{$s}%")
                ->orWhereHas('entreprise', fn ($eq) => $eq->where('raison_sociale', 'like', "%{$s}%"))
            )
            ->orderBy($sortField, $sortDir)
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Cree un devis pour une seule prestation.
     * Le prix HT est calcule automatiquement via la grille tarifaire (immuable).
     */
    public function creerDevis(array $data, int $userId): Devis
    {
        return DB::transaction(function () use ($data, $userId) {
            $exercice = isset($data['exercice_id'])
                ? Exercice::findOrFail($data['exercice_id'])
                : Exercice::current();
            $prefixe = Setting::get('devis_prefixe', 'DV');

            $entreprise = Entreprise::findOrFail($data['entreprise_id']);
            $prestation = Prestation::findOrFail($data['prestation_id']);

            // Prix HT calcule une seule fois via la grille tarifaire — immuable
            $prixHt = $prestation->calculerPrixHt($entreprise->regime_fiscal, $entreprise->categorie);

            $typeTva = $data['type_tva'] ?? 'standard';
            $tvaTaux = TvaTaux::enVigueurLe($data['date_devis'], $typeTva);

            $montantTva = $tvaTaux ? round($prixHt * (float) $tvaTaux->taux / 100, 2) : 0;
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

        // Recalculer le prix HT si entreprise ou prestation change
        if (isset($data['entreprise_id']) || isset($data['prestation_id'])) {
            $entreprise = Entreprise::findOrFail($data['entreprise_id'] ?? $devis->entreprise_id);
            $prestation = Prestation::findOrFail($data['prestation_id'] ?? $devis->prestation_id);
            $data['prix_ht'] = $prestation->calculerPrixHt($entreprise->regime_fiscal, $entreprise->categorie);
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
     * Cree une facture de mission avec tranche automatique.
     * T1=30%, T2=30%, T3=40% — determinee par le nb de factures existantes sur la mission.
     * Date echeance = date_facture + 45 jours.
     * Snapshots TVA figes a la date de facturation — regle immuable.
     */
    public function creerFacture(array $data, int $userId): Facture
    {
        return DB::transaction(function () use ($data, $userId) {
            $mission = Mission::with('entreprise', 'prestation')
                ->lockForUpdate()
                ->findOrFail($data['mission_id']);

            $nbFactures = Facture::where('mission_id', $mission->id)
                ->where('type', 'FF')
                ->count();

            if ($nbFactures >= 3) {
                throw new DomainException('Les 3 tranches ont deja ete facturees pour cette mission.');
            }

            $tranches = [
                0 => ['taux' => 0.30, 'label' => 'Tranche 1 (30%)'],
                1 => ['taux' => 0.30, 'label' => 'Tranche 2 (30%)'],
                2 => ['taux' => 0.40, 'label' => 'Tranche 3 — solde (40%)'],
            ];

            $tranche = $tranches[$nbFactures];

            // La 3e tranche est le solde exact (prix_ht - T1 - T2) et non un round() independant :
            // garantit T1 + T2 + T3 == prix_ht meme lorsque le prix porte des centimes (pas de perte d'arrondi).
            $prixHt = (float) $mission->prix_ht;
            $montantT1 = round($prixHt * 0.30, 2);
            $montantT2 = round($prixHt * 0.30, 2);
            $montantsTranches = [
                0 => $montantT1,
                1 => $montantT2,
                2 => round($prixHt - $montantT1 - $montantT2, 2),
            ];
            $montantHt = $montantsTranches[$nbFactures];

            $dateFacture = $data['date_facture'];
            $dateEcheance = Carbon::parse($dateFacture)->addDays(45)->toDateString();

            $typeTva = $data['type_tva'] ?? 'standard';
            $tvaTaux = TvaTaux::enVigueurLe($dateFacture, $typeTva);
            $tauxTva = $tvaTaux ? (float) $tvaTaux->taux : 0;
            $montantTva = round($montantHt * $tauxTva / 100, 2);
            $montantTtc = round($montantHt + $montantTva, 2);

            $exercice = isset($data['exercice_id'])
                ? Exercice::findOrFail($data['exercice_id'])
                : Exercice::current();
            $prefixe = Setting::get('facture_prefixe', 'FF');
            $designation = $mission->prestation->designation.' — '.($tranche['taux'] * 100).'%';

            $facture = Facture::create([
                'entreprise_id' => $mission->entreprise_id,
                'exercice_id' => $exercice->id,
                'mission_id' => $mission->id,
                'devis_id' => $mission->devis_id ?? null,
                'created_by' => $userId,
                'tva_taux_id' => $tvaTaux?->id,
                'numero' => $this->genererNumero($prefixe, 'factures', $exercice),
                'type' => 'FF',
                'date_facture' => $dateFacture,
                'date_echeance' => $dateEcheance,
                'montant_ht' => $montantHt,
                'taux_tva' => $tauxTva,
                'montant_tva' => $montantTva,
                'montant_ttc' => $montantTtc,
                'montant_paye' => 0,
                'statut_paiement' => 'en_attente',
                'mode_paiement' => 'non_defini',
                'notes' => $data['notes'] ?? null,
            ]);

            FactureLigne::create([
                'facture_id' => $facture->id,
                'prestation_id' => $mission->prestation_id,
                'designation' => $designation,
                'quantite' => 1,
                'prix_unitaire_ht' => $montantHt,
                'total_ht' => $montantHt,
                'ordre' => 1,
            ]);

            return $facture->load('lignes', 'entreprise', 'mission');
        });
    }

    /**
     * Emet un avoir sur une facture existante.
     * Le taux TVA est repris depuis la facture d'origine (snapshot immuable).
     * Le montant HT saisi ne peut pas depasser le montant restant du de la facture.
     */
    public function creerAvoir(Facture $facture, array $data, int $userId): Avoir
    {
        return DB::transaction(function () use ($facture, $data, $userId) {
            $montantHt = (float) $data['montant_ht'];
            $tauxTva = (float) $facture->taux_tva;
            $montantTva = round($montantHt * $tauxTva / 100, 2);
            $montantTtc = round($montantHt + $montantTva, 2);

            if ($montantTtc > $facture->montantRestant() + 0.001) {
                throw new DomainException('Le montant de l\'avoir ne peut pas dépasser le montant restant dû ('
                    .number_format($facture->montantRestant(), 2, ',', ' ').' DA).');
            }

            $exercice = Exercice::current();
            $prefixe = Setting::get('avoir_prefixe', 'FA');

            return Avoir::create([
                'facture_origine_id' => $facture->id,
                'exercice_id' => $exercice->id,
                'created_by' => $userId,
                'numero' => $this->genererNumero($prefixe, 'avoirs', $exercice),
                'date_avoir' => $data['date_avoir'],
                'montant_ht' => $montantHt,
                'taux_tva_snapshot' => $tauxTva,
                'montant_tva' => $montantTva,
                'montant_ttc' => $montantTtc,
                'motif' => $data['motif'],
            ]);
        });
    }

    public function supprimerFacture(Facture $facture): void
    {
        if ($facture->paiements()->exists()) {
            throw new DomainException('Impossible de supprimer une facture avec des paiements.');
        }

        DB::transaction(function () use ($facture) {
            $facture->lignes()->delete();
            $facture->delete();
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
