<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Setting;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardService
{
    public function getStats(?int $exerciceId): array
    {
        $now = Carbon::now();

        $caTtc = (float) $this->factureQuery($exerciceId)->sum('montant_ttc');
        $totalPaye = (float) $this->factureQuery($exerciceId)->sum('montant_paye');

        $tvaCollectee = (float) $this->factureQuery($exerciceId)->sum('montant_tva');
        $tauxRecouvrement = $this->calculerTauxRecouvrement($caTtc, $totalPaye);
        $caMois = $this->calculerCaMois($exerciceId, $now);
        $enRetard = $this->compterFacturesEnRetard($exerciceId, $now);
        $totalImpaye = $this->calculerTotalImpaye($exerciceId);
        $seuilRecouvrement = $this->seuilRecouvrement();

        return [
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee', 'statut']),
            'exercice_courant' => $exerciceId,
            'kpi' => [
                'ca_mois' => $caMois,
                'tva_collectee' => $tvaCollectee,
                'taux_recouvrement' => $tauxRecouvrement,
                'seuil_recouvrement' => $seuilRecouvrement,
            ],
            'alertes' => $this->genererAlertes($enRetard, $caTtc, $tauxRecouvrement, $seuilRecouvrement),
            'entreprises' => [
                'total' => Entreprise::count(),
                'clients' => Entreprise::where('statut', 'client')->count(),
                'prospects' => Entreprise::where('statut', 'prospect')->count(),
            ],
            'missions' => [
                'total' => $this->missionQuery($exerciceId)->count(),
                'en_cours' => $this->missionQuery($exerciceId)->where('statut', 'en_cours')->count(),
                'terminees' => $this->missionQuery($exerciceId)->where('statut', 'terminee')->count(),
                'ca_ht' => (float) $this->missionQuery($exerciceId)->sum('prix_ht'),
            ],
            'factures' => [
                'total' => $this->factureQuery($exerciceId)->count(),
                'en_attente' => $this->factureQuery($exerciceId)->where('statut_paiement', 'en_attente')->count(),
                'partielles' => $this->factureQuery($exerciceId)->where('statut_paiement', 'partiel')->count(),
                'soldees' => $this->factureQuery($exerciceId)->where('statut_paiement', 'solde')->count(),
                'ca_ttc' => $caTtc,
                'total_paye' => $totalPaye,
                'total_impaye' => $totalImpaye,
                'en_retard' => $enRetard,
            ],
            'devis' => [
                'total' => Devis::count(),
                'en_attente' => Devis::whereIn('statut', ['brouillon', 'envoye'])->count(),
                'acceptes' => Devis::where('statut', 'accepte')->count(),
                'ca_potentiel' => (float) Devis::whereIn('statut', ['brouillon', 'envoye'])->sum('montant_ttc'),
            ],
            'recentes' => [
                'factures' => Facture::with('entreprise')
                    ->where('type', 'FF')
                    ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
                    ->latest('date_facture')
                    ->take(5)
                    ->get(['id', 'entreprise_id', 'numero', 'montant_ttc', 'montant_paye', 'statut_paiement', 'date_facture', 'date_echeance']),
                'missions' => Mission::with('entreprise', 'prestation')
                    ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
                    ->latest('created_at')
                    ->take(5)
                    ->get(['id', 'entreprise_id', 'prestation_id', 'reference', 'prix_ht', 'statut', 'date_debut']),
            ],
        ];
    }

    private function factureQuery(?int $exerciceId): Builder
    {
        return Facture::where('type', 'FF')
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId));
    }

    private function missionQuery(?int $exerciceId): Builder
    {
        return Mission::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId));
    }

    private function calculerTauxRecouvrement(float $caTtc, float $totalPaye): float
    {
        if ($caTtc <= 0) {
            return 0.0;
        }

        return round(($totalPaye / $caTtc) * 100, 1);
    }

    private function calculerCaMois(?int $exerciceId, Carbon $now): float
    {
        return (float) $this->factureQuery($exerciceId)
            ->whereYear('date_facture', $now->year)
            ->whereMonth('date_facture', $now->month)
            ->sum('montant_ttc');
    }

    private function compterFacturesEnRetard(?int $exerciceId, Carbon $now): int
    {
        return $this->factureQuery($exerciceId)
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->where('date_echeance', '<', $now)
            ->count();
    }

    private function calculerTotalImpaye(?int $exerciceId): float
    {
        return (float) $this->factureQuery($exerciceId)
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->selectRaw('COALESCE(SUM(montant_ttc - montant_paye), 0) as total')
            ->value('total');
    }

    private function seuilRecouvrement(): float
    {
        return (float) (Setting::where('key', 'seuil_alerte_recouvrement')->value('value') ?? 70);
    }

    private function genererAlertes(int $enRetard, float $caTtc, float $tauxRecouvrement, float $seuilRecouvrement): array
    {
        $alertes = [];

        if ($enRetard > 0) {
            $alertes[] = [
                'type' => 'danger',
                'message' => "{$enRetard} facture(s) en retard de paiement.",
            ];
        }

        if ($caTtc > 0 && $tauxRecouvrement < $seuilRecouvrement) {
            $alertes[] = [
                'type' => 'warn',
                'message' => "Taux de recouvrement faible : {$tauxRecouvrement}% (seuil : {$seuilRecouvrement}%).",
            ];
        }

        return $alertes;
    }

    public function getCollaborateurStats(User $user): array
    {
        $missionIds = Mission::whereHas('collaborateurs', fn ($q) => $q->where('users.id', $user->id))
            ->pluck('id');

        $totalMissions = $missionIds->count();
        $enCours = Mission::whereIn('id', $missionIds)->where('statut', 'en_cours')->count();
        $terminees = Mission::whereIn('id', $missionIds)->where('statut', 'terminee')->count();

        $totalTaches = Tache::where('assigned_to', $user->id)->count();
        $aFaire = Tache::where('assigned_to', $user->id)->where('statut', 'a_faire')->count();
        $tachesEnCours = Tache::where('assigned_to', $user->id)->where('statut', 'en_cours')->count();
        $tachesTerminees = Tache::where('assigned_to', $user->id)->where('statut', 'termine')->count();
        $bloquees = Tache::where('assigned_to', $user->id)->where('statut', 'bloque')->count();
        $tauxCompletion = $totalTaches > 0 ? round($tachesTerminees / $totalTaches * 100, 1) : 0;

        $mesMissions = Mission::with('entreprise', 'prestation')
            ->whereIn('id', $missionIds)
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(function (Mission $m) {
                $tTotal = $m->taches()->count();
                $tDone = $m->taches()->where('statut', 'termine')->count();

                return [
                    'id' => $m->id,
                    'reference' => $m->reference,
                    'statut' => $m->statut,
                    'entreprise' => $m->entreprise?->raison_sociale,
                    'prestation' => $m->prestation?->designation,
                    'date_fin' => $m->date_fin,
                    'taches_total' => $tTotal,
                    'taches_terminees' => $tDone,
                    'progression' => $tTotal > 0 ? round($tDone / $tTotal * 100) : 0,
                ];
            });

        $mesTachesUrgentes = Tache::with('mission.entreprise')
            ->where('assigned_to', $user->id)
            ->whereIn('statut', ['a_faire', 'en_cours'])
            ->orderByRaw("CASE WHEN statut = 'en_cours' THEN 0 ELSE 1 END")
            ->orderBy('date_echeance')
            ->take(5)
            ->get()
            ->map(fn (Tache $t) => [
                'id' => $t->id,
                'mission_id' => $t->mission_id,
                'titre' => $t->titre,
                'statut' => $t->statut,
                'date_fin' => $t->date_echeance,
                'mission_reference' => $t->mission?->reference,
                'entreprise' => $t->mission?->entreprise?->raison_sociale,
                'en_retard' => $t->date_echeance && Carbon::parse($t->date_echeance)->isPast(),
            ]);

        return [
            'missions' => [
                'total' => $totalMissions,
                'en_cours' => $enCours,
                'terminees' => $terminees,
            ],
            'taches' => [
                'total' => $totalTaches,
                'a_faire' => $aFaire,
                'en_cours' => $tachesEnCours,
                'terminees' => $tachesTerminees,
                'bloquees' => $bloquees,
                'taux_completion' => $tauxCompletion,
            ],
            'mes_missions' => $mesMissions,
            'mes_taches_urgentes' => $mesTachesUrgentes,
        ];
    }
}
