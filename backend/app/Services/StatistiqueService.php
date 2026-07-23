<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Avoir;
use App\Models\Facture;
use App\Models\Mission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Statistiques cabinet (page /statistiques, admin uniquement) : agregats globaux
 * top entreprises, missions par prestation/etat, creances (aging + top debiteurs).
 *
 * La logique creances (aging + top debiteurs + montant restant avoirs deduits) est
 * partagee avec DashboardService::getSecretaireStats via les methodes publiques
 * creancesQuery() / restantAvecAvoirsSum() / calculerAgingEtTopDebiteurs() ci-dessous.
 */
class StatistiqueService
{
    /**
     * @return array{
     *     top_entreprises: list<array{entreprise_id:int, raison_sociale:string, ca_ht_net:float}>,
     *     missions_par_prestation: list<array{prestation_id:int, designation:string, total:int}>,
     *     missions_par_etat: array{en_cours:int, terminee:int, suspendue:int, annulee:int},
     *     creances: array{total_impaye:float, aging:array, top_debiteurs:list}
     * }
     */
    public function getCabinetStats(?int $exerciceId): array
    {
        return [
            'top_entreprises' => $this->calculerTopEntreprises($exerciceId),
            'missions_par_prestation' => $this->calculerMissionsParPrestation($exerciceId),
            'missions_par_etat' => $this->calculerMissionsParEtat($exerciceId),
            'creances' => $this->calculerCreances($exerciceId),
        ];
    }

    /**
     * CA facture HT net d'avoirs par entreprise (factures type FF de l'exercice, avoirs
     * rattaches deduits). Top 8, tri desc, exclut les totaux <= 0.
     *
     * Deux agregats groupes distincts (factures, puis avoirs via jointure factures) pour
     * eviter le fan-out d'un join direct factures<->avoirs (une facture peut avoir
     * plusieurs avoirs, ce qui dupliquerait montant_ht si somme dans la meme requete).
     * Colonnes constantes uniquement dans les selectRaw/groupBy — aucun input utilisateur.
     *
     * @return list<array{entreprise_id:int, raison_sociale:string, ca_ht_net:float}>
     */
    private function calculerTopEntreprises(?int $exerciceId): array
    {
        $facturesParEntreprise = Facture::query()
            ->join('entreprises', 'entreprises.id', '=', 'factures.entreprise_id')
            ->where('factures.type', 'FF')
            ->when($exerciceId, fn (Builder $q) => $q->where('factures.exercice_id', $exerciceId))
            ->groupBy('factures.entreprise_id', 'entreprises.raison_sociale')
            ->selectRaw('factures.entreprise_id as entreprise_id, entreprises.raison_sociale as raison_sociale, SUM(factures.montant_ht) as total_ht')
            ->get()
            ->keyBy('entreprise_id');

        $avoirsParEntreprise = Avoir::query()
            ->join('factures', 'factures.id', '=', 'avoirs.facture_origine_id')
            ->where('factures.type', 'FF')
            ->when($exerciceId, fn (Builder $q) => $q->where('factures.exercice_id', $exerciceId))
            ->groupBy('factures.entreprise_id')
            ->selectRaw('factures.entreprise_id as entreprise_id, SUM(avoirs.montant_ht) as total_ht')
            ->pluck('total_ht', 'entreprise_id');

        return $facturesParEntreprise
            ->map(function ($row) use ($avoirsParEntreprise): array {
                $entrepriseId = (int) $row->entreprise_id;
                $caHtNet = (float) $row->total_ht - (float) ($avoirsParEntreprise[$entrepriseId] ?? 0);

                return [
                    'entreprise_id' => $entrepriseId,
                    'raison_sociale' => $row->raison_sociale,
                    'ca_ht_net' => round($caHtNet, 2),
                ];
            })
            ->filter(fn (array $row) => $row['ca_ht_net'] > 0)
            ->sortByDesc('ca_ht_net')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Nombre de missions par prestation (scope exercice).
     *
     * @return list<array{prestation_id:int, designation:string, total:int}>
     */
    private function calculerMissionsParPrestation(?int $exerciceId): array
    {
        return Mission::query()
            ->join('prestations', 'prestations.id', '=', 'missions.prestation_id')
            ->when($exerciceId, fn (Builder $q) => $q->where('missions.exercice_id', $exerciceId))
            ->groupBy('missions.prestation_id', 'prestations.designation')
            ->selectRaw('missions.prestation_id as prestation_id, prestations.designation as designation, COUNT(*) as total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'prestation_id' => (int) $row->prestation_id,
                'designation' => $row->designation,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array{en_cours:int, terminee:int, suspendue:int, annulee:int}
     */
    private function calculerMissionsParEtat(?int $exerciceId): array
    {
        return [
            'en_cours' => $this->missionQuery($exerciceId)->where('statut', 'en_cours')->count(),
            'terminee' => $this->missionQuery($exerciceId)->where('statut', 'terminee')->count(),
            'suspendue' => $this->missionQuery($exerciceId)->where('statut', 'suspendue')->count(),
            'annulee' => $this->missionQuery($exerciceId)->where('statut', 'annulee')->count(),
        ];
    }

    private function missionQuery(?int $exerciceId): Builder
    {
        return Mission::when($exerciceId, fn (Builder $q) => $q->where('exercice_id', $exerciceId));
    }

    /**
     * Creances (total impaye + aging + top 5 debiteurs) scopees par exercice.
     *
     * @return array{total_impaye:float, aging:array, top_debiteurs:list}
     */
    private function calculerCreances(?int $exerciceId): array
    {
        $creances = $this->creancesQuery($exerciceId)->get();

        return $this->calculerAgingEtTopDebiteurs($creances, Carbon::now());
    }

    /**
     * Requete de base des factures impayees (en_attente/partiel), avec l'entreprise
     * chargee et l'agregat avoirs_sum precalcule via withSum (anti N+1 — une seule
     * requete correlee au lieu d'un appel Facture::montantRestant() par facture).
     * Reutilisee telle quelle par DashboardService::getSecretaireStats (sans filtre
     * exercice) et par les stats cabinet (avec filtre exercice).
     */
    public function creancesQuery(?int $exerciceId): Builder
    {
        return Facture::with('entreprise')
            ->where('type', 'FF')
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->when($exerciceId, fn (Builder $q) => $q->where('exercice_id', $exerciceId))
            ->withSum('avoirs as avoirs_sum', 'montant_ttc');
    }

    /**
     * Montant restant d'une facture (avoirs deduits), a partir de l'agregat avoirs_sum
     * precharge par creancesQuery(). Meme semantique que Facture::montantRestant()
     * mais sans requete supplementaire.
     */
    public function restantAvecAvoirsSum(Facture $facture): float
    {
        return max(0.0, (float) $facture->montant_ttc - (float) $facture->montant_paye - (float) ($facture->avoirs_sum ?? 0));
    }

    /**
     * Aging (15-30/30-60/60+ jours de retard) + top debiteurs + total impaye,
     * a partir d'une collection de factures deja chargee via creancesQuery().
     *
     * @param  Collection<int, Facture>  $creances
     * @return array{total_impaye:float, aging:array{retard_15_30:float, retard_30_60:float, retard_60_plus:float}, top_debiteurs:list<array{entreprise_id:int, raison_sociale:string, montant_impaye:float}>}
     */
    public function calculerAgingEtTopDebiteurs(Collection $creances, Carbon $now, int $topDebiteurs = 5): array
    {
        $totalImpaye = 0.0;
        $aging = ['retard_15_30' => 0.0, 'retard_30_60' => 0.0, 'retard_60_plus' => 0.0];
        $debiteursMap = [];

        foreach ($creances as $facture) {
            $restant = $this->restantAvecAvoirsSum($facture);
            $totalImpaye += $restant;

            if ($restant <= 0) {
                continue;
            }

            $entrepriseId = $facture->entreprise_id;
            if (! isset($debiteursMap[$entrepriseId])) {
                $debiteursMap[$entrepriseId] = [
                    'entreprise_id' => $entrepriseId,
                    'raison_sociale' => $facture->entreprise?->raison_sociale ?? 'Inconnu',
                    'montant_impaye' => 0.0,
                ];
            }
            $debiteursMap[$entrepriseId]['montant_impaye'] += $restant;

            if ($facture->date_echeance && $facture->date_echeance->lt($now)) {
                $jours = (int) $facture->date_echeance->diffInDays($now);
                if ($jours >= 60) {
                    $aging['retard_60_plus'] += $restant;
                } elseif ($jours >= 30) {
                    $aging['retard_30_60'] += $restant;
                } elseif ($jours >= 15) {
                    $aging['retard_15_30'] += $restant;
                }
            }
        }

        $top = collect($debiteursMap)
            ->sortByDesc('montant_impaye')
            ->take($topDebiteurs)
            ->values()
            ->map(fn (array $row) => [
                'entreprise_id' => $row['entreprise_id'],
                'raison_sociale' => $row['raison_sociale'],
                'montant_impaye' => round($row['montant_impaye'], 2),
            ])
            ->all();

        return [
            'total_impaye' => round($totalImpaye, 2),
            'aging' => [
                'retard_15_30' => round($aging['retard_15_30'], 2),
                'retard_30_60' => round($aging['retard_30_60'], 2),
                'retard_60_plus' => round($aging['retard_60_plus'], 2),
            ],
            'top_debiteurs' => $top,
        ];
    }
}
