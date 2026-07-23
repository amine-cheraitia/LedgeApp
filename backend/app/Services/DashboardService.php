<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\Mission;
use App\Models\Paiement;
use App\Models\Setting;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    public function __construct(private readonly StatistiqueService $statistiqueService) {}

    public function getStats(?int $exerciceId): array
    {
        $now = Carbon::now();
        // Lookup unique de l'exercice filtre, partage entre calculerCaMois et calculerCaMensuel
        $exercice = $exerciceId ? Exercice::find($exerciceId) : null;

        $caTtc = (float) $this->factureQuery($exerciceId)->sum('montant_ttc');
        $totalPaye = (float) $this->factureQuery($exerciceId)->sum('montant_paye');

        $tvaCollectee = (float) $this->factureQuery($exerciceId)->sum('montant_tva');
        $tauxRecouvrement = $this->calculerTauxRecouvrement($caTtc, $totalPaye);
        $caMois = $this->calculerCaMois($exerciceId, $now, $exercice);
        $enRetard = $this->compterFacturesEnRetard($exerciceId, $now);
        $totalImpaye = $this->calculerTotalImpaye($exerciceId);
        $seuilRecouvrement = $this->seuilRecouvrement();
        $caMensuel = $this->calculerCaMensuel($exerciceId, $exercice);

        return [
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee', 'statut']),
            'exercice_courant' => $exerciceId,
            'kpi' => [
                'ca_mois' => $caMois,
                'tva_collectee' => $tvaCollectee,
                'taux_recouvrement' => $tauxRecouvrement,
                'seuil_recouvrement' => $seuilRecouvrement,
            ],
            'ca_mensuel' => $caMensuel,
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
                'suspendues' => $this->missionQuery($exerciceId)->where('statut', 'suspendue')->count(),
                'annulees' => $this->missionQuery($exerciceId)->where('statut', 'annulee')->count(),
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
                'total' => Devis::when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))->count(),
                'en_attente' => Devis::whereIn('statut', ['brouillon', 'envoye'])
                    ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
                    ->count(),
                'acceptes' => Devis::where('statut', 'accepte')
                    ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
                    ->count(),
                'ca_potentiel' => (float) Devis::whereIn('statut', ['brouillon', 'envoye'])
                    ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
                    ->sum('montant_ttc'),
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

    /**
     * CA TTC facture du mois calendaire courant. Un exercice filtre dont l'annee
     * ne correspond pas a l'annee en cours rend la notion de « mois courant » incoherente
     * (on ne facture pas en avance sur un exercice passe/futur) -> null dans ce cas.
     */
    private function calculerCaMois(?int $exerciceId, Carbon $now, ?Exercice $exercice): ?float
    {
        if ($exercice && (int) $exercice->annee !== $now->year) {
            return null;
        }

        return (float) $this->factureQuery($exerciceId)
            ->whereYear('date_facture', $now->year)
            ->whereMonth('date_facture', $now->month)
            ->sum('montant_ttc');
    }

    /**
     * Serie du CA TTC facture par mois (12 mois) pour l'annee de l'exercice
     * selectionne, ou l'annee en cours si aucun exercice n'est filtre.
     *
     * @return array{annee: int, data: list<float>}
     */
    private function calculerCaMensuel(?int $exerciceId, ?Exercice $exercice): array
    {
        $annee = $exercice ? (int) $exercice->annee : Carbon::now()->year;

        // Regroupement en PHP (portable SQLite/MySQL, pas de fonction SQL specifique)
        $data = array_fill(0, 12, 0.0);

        $this->factureQuery($exerciceId)
            ->whereYear('date_facture', $annee)
            ->get(['date_facture', 'montant_ttc'])
            ->each(function (Facture $facture) use (&$data): void {
                $mois = (int) $facture->date_facture->format('n');
                $data[$mois - 1] += (float) $facture->montant_ttc;
            });

        return [
            'annee' => $annee,
            'data' => array_map(fn (float $total) => round($total, 2), $data),
        ];
    }

    private function compterFacturesEnRetard(?int $exerciceId, Carbon $now): int
    {
        return $this->factureQuery($exerciceId)
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->where('date_echeance', '<', $now)
            ->count();
    }

    /**
     * Total impaye, avoirs deduits par facture (max(0, ttc - paye - avoirs)), aligne
     * sur la semantique de Facture::montantRestant() utilisee par le dashboard secretaire.
     * withSum evite le N+1 (une seule requete, agregat correle en sous-requete).
     */
    private function calculerTotalImpaye(?int $exerciceId): float
    {
        $total = $this->factureQuery($exerciceId)
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->withSum('avoirs as avoirs_sum', 'montant_ttc')
            ->get()
            ->sum(fn (Facture $facture) => max(
                0.0,
                (float) $facture->montant_ttc - (float) $facture->montant_paye - (float) ($facture->avoirs_sum ?? 0)
            ));

        return round((float) $total, 2);
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

        $now = Carbon::now();

        $totalTaches = Tache::where('assigned_to', $user->id)->count();
        $aFaire = Tache::where('assigned_to', $user->id)->where('statut', 'a_faire')->count();
        $tachesEnCours = Tache::where('assigned_to', $user->id)->where('statut', 'en_cours')->count();
        $tachesTerminees = Tache::where('assigned_to', $user->id)->where('statut', 'terminee')->count();
        $bloquees = Tache::where('assigned_to', $user->id)->where('statut', 'bloquee')->count();
        $tauxCompletion = $totalTaches > 0 ? round($tachesTerminees / $totalTaches * 100, 1) : 0;
        $tachesEnRetard = Tache::where('assigned_to', $user->id)
            ->whereIn('statut', ['a_faire', 'en_cours'])
            ->whereNotNull('date_echeance')
            ->where('date_echeance', '<', $now)
            ->count();

        $mesMissions = Mission::with('entreprise', 'prestation')
            ->whereIn('id', $missionIds)
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(function (Mission $m) {
                $tTotal = $m->taches()->count();
                $tDone = $m->taches()->where('statut', 'terminee')->count();

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
                'priorite' => $t->priorite ?? 1,
                'date_fin' => $t->date_echeance,
                'mission_reference' => $t->mission?->reference,
                'entreprise' => $t->mission?->entreprise?->raison_sociale,
                'en_retard' => $t->date_echeance && Carbon::parse($t->date_echeance)->isPast(),
            ]);

        $echeances = collect()
            ->merge(
                Mission::whereIn('id', $missionIds)
                    ->whereNotNull('date_fin')
                    ->where('date_fin', '>=', $now->copy()->startOfDay())
                    ->where('date_fin', '<=', $now->copy()->addDays(30))
                    ->where('statut', 'en_cours')
                    ->with('entreprise')
                    ->get()
                    ->map(fn (Mission $m) => [
                        'type' => 'mission',
                        'id' => $m->id,
                        'titre' => $m->reference,
                        'entreprise' => $m->entreprise?->raison_sociale,
                        'mission_id' => $m->id,
                        'mission_reference' => $m->reference,
                        'date' => $m->date_fin,
                        'en_retard' => Carbon::parse($m->date_fin)->isPast(),
                    ])
            )
            ->merge(
                Tache::where('assigned_to', $user->id)
                    ->whereIn('statut', ['a_faire', 'en_cours'])
                    ->whereNotNull('date_echeance')
                    ->where('date_echeance', '>=', $now->copy()->startOfDay())
                    ->where('date_echeance', '<=', $now->copy()->addDays(14))
                    ->with('mission.entreprise')
                    ->get()
                    ->map(fn (Tache $t) => [
                        'type' => 'tache',
                        'id' => $t->id,
                        'titre' => $t->titre,
                        'entreprise' => $t->mission?->entreprise?->raison_sociale,
                        'mission_id' => $t->mission_id,
                        'mission_reference' => $t->mission?->reference,
                        'date' => $t->date_echeance,
                        'en_retard' => Carbon::parse($t->date_echeance)->isPast(),
                    ])
            )
            ->sortBy('date')
            ->take(8)
            ->values();

        $activiteRecente = Tache::where('assigned_to', $user->id)
            ->where('statut', 'terminee')
            ->with('mission')
            ->latest('updated_at')
            ->take(5)
            ->get()
            ->map(fn (Tache $t) => [
                'id' => $t->id,
                'titre' => $t->titre,
                'mission_reference' => $t->mission?->reference,
                'mission_id' => $t->mission_id,
                'date' => $t->updated_at,
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
                'en_retard' => $tachesEnRetard,
            ],
            'mes_missions' => $mesMissions,
            'mes_taches_urgentes' => $mesTachesUrgentes,
            'echeances' => $echeances,
            'activite_recente' => $activiteRecente,
        ];
    }

    public function getSecretaireStats(): array
    {
        $now = Carbon::now();

        // Requete + agregats partages avec StatistiqueService (stats cabinet) : aucun
        // filtre exercice ici, le dashboard secretaire porte sur toutes les creances.
        $creances = $this->statistiqueService->creancesQuery(null)
            ->with('relances')
            ->get();

        $clientsDebiteurs = $creances->pluck('entreprise_id')->unique()->count();

        $agrege = $this->statistiqueService->calculerAgingEtTopDebiteurs($creances, $now);

        $relancesDues = $this->compterRelancesDues($creances, $now);

        $enRetard = $creances->filter(
            fn (Facture $f) => $f->date_echeance && $f->date_echeance->lt($now) && $this->statistiqueService->restantAvecAvoirsSum($f) > 0
        )->count();

        $recentesCreances = $creances
            ->filter(fn (Facture $f) => $this->statistiqueService->restantAvecAvoirsSum($f) > 0)
            ->sortBy('date_echeance')
            ->take(5)
            ->map(fn (Facture $f) => [
                'id' => $f->id,
                'numero' => $f->numero,
                'entreprise' => $f->entreprise?->raison_sociale,
                'montant_restant' => round($this->statistiqueService->restantAvecAvoirsSum($f), 2),
                'date_echeance' => $f->date_echeance?->toDateString(),
                'statut_paiement' => $f->statut_paiement,
                'en_retard' => $f->date_echeance && $f->date_echeance->lt($now),
            ])
            ->values()
            ->all();

        $encaissementsMois = $this->compterEncaissements($now);
        $totalRelancesDues = array_sum($relancesDues);

        $alertes = [];
        if ($enRetard > 0) {
            $alertes[] = [
                'type' => 'danger',
                'message' => "{$enRetard} facture(s) en retard de paiement.",
            ];
        }
        if ($totalRelancesDues > 0) {
            $alertes[] = [
                'type' => 'warn',
                'message' => "{$totalRelancesDues} relance(s) automatique(s) due(s) aujourd'hui.",
            ];
        }

        $actions = $this->construireWorklist($enRetard, $totalRelancesDues);

        return [
            'alertes' => $alertes,
            'actions' => $actions,
            'creances' => [
                'total_impaye' => $agrege['total_impaye'],
                'clients_debiteurs' => $clientsDebiteurs,
                'en_retard' => $enRetard,
            ],
            'aging' => $agrege['aging'],
            'relances_dues' => $relancesDues,
            'top_debiteurs' => $agrege['top_debiteurs'],
            'encaissements_mois' => $encaissementsMois,
            'recentes_creances' => $recentesCreances,
        ];
    }

    /**
     * Encaissements du mois courant — indicateur de recouvrement du dashboard secretaire.
     *
     * @return array{count: int, montant: float}
     */
    private function compterEncaissements(Carbon $now): array
    {
        // whereYear+whereMonth (et non un whereBetween de dates) : un date_paiement
        // compare comme datetime ('...-30 00:00:00') depassait la borne haute '...-30',
        // ce qui excluait les encaissements faits le dernier jour du mois.
        $encaissements = Paiement::whereYear('date_paiement', $now->year)
            ->whereMonth('date_paiement', $now->month);

        return [
            'count' => (clone $encaissements)->count(),
            'montant' => round((float) (clone $encaissements)->sum('montant'), 2),
        ];
    }

    /**
     * Construit la liste « A faire » (worklist) orientee recouvrement.
     *
     * @return list<array{key: string, label: string, count: int, severity: string, icon: string, route: string}>
     */
    private function construireWorklist(int $enRetard, int $totalRelancesDues): array
    {
        $actions = [];

        if ($enRetard > 0) {
            $actions[] = ['key' => 'factures_retard', 'label' => 'Factures en retard a recouvrer', 'count' => $enRetard, 'severity' => 'danger', 'icon' => 'pi-exclamation-triangle', 'route' => '/creances'];
        }
        if ($totalRelancesDues > 0) {
            $actions[] = ['key' => 'relances', 'label' => 'Relances a envoyer', 'count' => $totalRelancesDues, 'severity' => 'warn', 'icon' => 'pi-bell', 'route' => '/creances'];
        }

        $ordre = ['danger' => 0, 'warn' => 1, 'info' => 2];
        usort($actions, fn (array $a, array $b) => $ordre[$a['severity']] <=> $ordre[$b['severity']]);

        return $actions;
    }

    /**
     * @param  Collection<int, Facture>  $creances
     * @return array{niveau_1: int, niveau_2: int, niveau_3: int}
     */
    private function compterRelancesDues(Collection $creances, Carbon $now): array
    {
        $delais = [
            1 => (int) Setting::get('relance_niveau1_jours', '15'),
            2 => (int) Setting::get('relance_niveau2_jours', '30'),
            3 => (int) Setting::get('relance_niveau3_jours', '45'),
        ];

        $compteurs = ['niveau_1' => 0, 'niveau_2' => 0, 'niveau_3' => 0];

        foreach ($creances as $facture) {
            if (! $facture->date_echeance) {
                continue;
            }

            $joursDepuisEcheance = (int) Carbon::parse($facture->date_echeance)->diffInDays($now);

            if ($joursDepuisEcheance <= 0) {
                continue;
            }

            $niveauAttendu = null;
            foreach (array_reverse($delais, true) as $niveau => $delai) {
                if ($joursDepuisEcheance >= $delai) {
                    $niveauAttendu = $niveau;
                    break;
                }
            }

            if ($niveauAttendu === null) {
                continue;
            }

            $dejaEnvoyee = $facture->relances
                ->where('niveau', $niveauAttendu)
                ->whereIn('statut', ['en_attente', 'envoyee'])
                ->isNotEmpty();

            if ($dejaEnvoyee) {
                continue;
            }

            if ($niveauAttendu > 1) {
                $niveauPrecedentEnvoye = $facture->relances
                    ->where('niveau', $niveauAttendu - 1)
                    ->where('statut', 'envoyee')
                    ->isNotEmpty();

                if (! $niveauPrecedentEnvoye) {
                    continue;
                }
            }

            $compteurs["niveau_{$niveauAttendu}"]++;
        }

        return $compteurs;
    }
}
