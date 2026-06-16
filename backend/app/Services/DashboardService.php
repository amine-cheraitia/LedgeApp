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
        $caMensuel = $this->calculerCaMensuel($exerciceId);

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

    /**
     * Serie du CA TTC facture par mois (12 mois) pour l'annee de l'exercice
     * selectionne, ou l'annee en cours si aucun exercice n'est filtre.
     *
     * @return array{annee: int, data: list<float>}
     */
    private function calculerCaMensuel(?int $exerciceId): array
    {
        $annee = $exerciceId
            ? (int) (Exercice::find($exerciceId)?->annee ?? Carbon::now()->year)
            : Carbon::now()->year;

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

        $creances = Facture::with(['entreprise', 'avoirs', 'relances'])
            ->where('type', 'FF')
            ->whereIn('statut_paiement', ['en_attente', 'partiel'])
            ->get();

        $totalImpaye = 0.0;
        $clientsDebiteurs = $creances->pluck('entreprise_id')->unique()->count();
        $aging = ['retard_15_30' => 0.0, 'retard_30_60' => 0.0, 'retard_60_plus' => 0.0];
        $debiteursMap = [];

        foreach ($creances as $facture) {
            $restant = $facture->montantRestant();
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

        $topDebiteurs = collect($debiteursMap)
            ->sortByDesc('montant_impaye')
            ->take(5)
            ->values()
            ->map(fn (array $row) => [
                'entreprise_id' => $row['entreprise_id'],
                'raison_sociale' => $row['raison_sociale'],
                'montant_impaye' => round($row['montant_impaye'], 2),
            ])
            ->all();

        $relancesDues = $this->compterRelancesDues($creances, $now);

        $facturesEmisesMoisCourant = $this->compterFacturesEmises($now->copy()->startOfMonth(), $now->copy()->endOfMonth());
        $facturesEmisesMoisPrecedent = $this->compterFacturesEmises(
            $now->copy()->subMonth()->startOfMonth(),
            $now->copy()->subMonth()->endOfMonth()
        );

        $enRetard = $creances->filter(
            fn (Facture $f) => $f->date_echeance && $f->date_echeance->lt($now) && $f->montantRestant() > 0
        )->count();

        $recentesCreances = $creances
            ->filter(fn (Facture $f) => $f->montantRestant() > 0)
            ->sortBy('date_echeance')
            ->take(5)
            ->map(fn (Facture $f) => [
                'id' => $f->id,
                'numero' => $f->numero,
                'entreprise' => $f->entreprise?->raison_sociale,
                'montant_restant' => round($f->montantRestant(), 2),
                'date_echeance' => $f->date_echeance?->toDateString(),
                'statut_paiement' => $f->statut_paiement,
                'en_retard' => $f->date_echeance && $f->date_echeance->lt($now),
            ])
            ->values()
            ->all();

        $facturation = $this->compterFacturation($now);
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

        $actions = $this->construireWorklist($enRetard, $totalRelancesDues, $facturation);

        return [
            'alertes' => $alertes,
            'actions' => $actions,
            'creances' => [
                'total_impaye' => round($totalImpaye, 2),
                'clients_debiteurs' => $clientsDebiteurs,
                'en_retard' => $enRetard,
            ],
            'aging' => [
                'retard_15_30' => round($aging['retard_15_30'], 2),
                'retard_30_60' => round($aging['retard_30_60'], 2),
                'retard_60_plus' => round($aging['retard_60_plus'], 2),
            ],
            'relances_dues' => $relancesDues,
            'top_debiteurs' => $topDebiteurs,
            'factures_emises' => [
                'mois_courant' => $facturesEmisesMoisCourant,
                'mois_precedent' => $facturesEmisesMoisPrecedent,
            ],
            'facturation' => $facturation,
            'recentes_creances' => $recentesCreances,
        ];
    }

    /**
     * Volet facturation/production du dashboard secretaire.
     *
     * @return array{devis_en_attente: array{count: int, montant: float}, devis_a_convertir: int, devis_expirant: int, encaissements_mois: array{count: int, montant: float}}
     */
    private function compterFacturation(Carbon $now): array
    {
        $devisEnAttente = Devis::where('statut', 'envoye');
        $encaissements = Paiement::whereBetween('date_paiement', [
            $now->copy()->startOfMonth()->toDateString(),
            $now->copy()->endOfMonth()->toDateString(),
        ]);

        return [
            'devis_en_attente' => [
                'count' => (clone $devisEnAttente)->count(),
                'montant' => round((float) (clone $devisEnAttente)->sum('montant_ttc'), 2),
            ],
            'devis_a_convertir' => Devis::where('statut', 'accepte')
                ->whereDoesntHave('mission')
                ->count(),
            'devis_expirant' => Devis::where('statut', 'envoye')
                ->whereNotNull('date_validite')
                ->whereBetween('date_validite', [
                    $now->toDateString(),
                    $now->copy()->addDays(7)->toDateString(),
                ])
                ->count(),
            'encaissements_mois' => [
                'count' => (clone $encaissements)->count(),
                'montant' => round((float) (clone $encaissements)->sum('montant'), 2),
            ],
        ];
    }

    /**
     * Construit la liste « A faire » (worklist) orientee action.
     *
     * @param  array{devis_en_attente: array{count: int, montant: float}, devis_a_convertir: int, devis_expirant: int, encaissements_mois: array{count: int, montant: float}}  $facturation
     * @return list<array{key: string, label: string, count: int, severity: string, icon: string, route: string}>
     */
    private function construireWorklist(int $enRetard, int $totalRelancesDues, array $facturation): array
    {
        $actions = [];

        if ($enRetard > 0) {
            $actions[] = ['key' => 'factures_retard', 'label' => 'Factures en retard a recouvrer', 'count' => $enRetard, 'severity' => 'danger', 'icon' => 'pi-exclamation-triangle', 'route' => '/creances'];
        }
        if ($totalRelancesDues > 0) {
            $actions[] = ['key' => 'relances', 'label' => 'Relances a envoyer', 'count' => $totalRelancesDues, 'severity' => 'warn', 'icon' => 'pi-bell', 'route' => '/creances'];
        }
        if ($facturation['devis_expirant'] > 0) {
            $actions[] = ['key' => 'devis_expirant', 'label' => 'Devis expirant sous 7 jours', 'count' => $facturation['devis_expirant'], 'severity' => 'warn', 'icon' => 'pi-hourglass', 'route' => '/devis'];
        }
        if ($facturation['devis_en_attente']['count'] > 0) {
            $actions[] = ['key' => 'devis_attente', 'label' => 'Devis en attente de reponse', 'count' => $facturation['devis_en_attente']['count'], 'severity' => 'info', 'icon' => 'pi-file', 'route' => '/devis'];
        }
        if ($facturation['devis_a_convertir'] > 0) {
            $actions[] = ['key' => 'devis_convertir', 'label' => 'Devis acceptes a convertir en mission', 'count' => $facturation['devis_a_convertir'], 'severity' => 'info', 'icon' => 'pi-sync', 'route' => '/devis'];
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

    /**
     * @return array{count: int, montant_ttc: float}
     */
    private function compterFacturesEmises(Carbon $debut, Carbon $fin): array
    {
        $baseQuery = Facture::where('type', 'FF')
            ->whereBetween('date_facture', [$debut->toDateString(), $fin->toDateString()]);

        return [
            'count' => (clone $baseQuery)->count(),
            'montant_ttc' => round((float) (clone $baseQuery)->sum('montant_ttc'), 2),
        ];
    }
}
