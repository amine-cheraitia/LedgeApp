<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Exercice;
use App\Models\KpiObjectif;
use App\Models\Mission;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class KpiService
{
    /**
     * Retourne la liste des collaborateurs avec leurs objectifs et leur réalisé pour un exercice.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getCollaborateurs(?int $exerciceId): Collection
    {
        $collaborateurs = User::role(['collaborateur', 'admin'])
            ->with(['kpiObjectifs' => fn ($q) => $q->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))])
            ->get();

        return $collaborateurs->map(fn (User $user) => [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'objectifs' => $user->kpiObjectifs->keyBy('type')->map(fn ($o) => ['id' => $o->id, 'valeur' => (float) $o->valeur]),
            'realise' => $this->calculerRealise($user, $exerciceId),
        ]);
    }

    /**
     * Retourne les stats detaillees d'un collaborateur (page /statistiques, onglet KPI) :
     * objectifs, realise global, serie mensuelle du CA HT et repartition des taches.
     *
     * @return array{
     *     user: array{id:int, name:string, email:string},
     *     objectifs: Collection<string, array{id:int, valeur:float}>,
     *     realise: array<string, float>,
     *     realise_mensuel: array{annee:int, data:list<float>},
     *     taches_par_statut: array{a_faire:int, en_cours:int, terminee:int, bloquee:int}
     * }
     */
    public function getCollaborateurStats(User $collaborateur, ?int $exerciceId): array
    {
        $objectifs = $collaborateur->kpiObjectifs()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->get()
            ->keyBy('type')
            ->map(fn (KpiObjectif $o) => ['id' => $o->id, 'valeur' => (float) $o->valeur]);

        return [
            'user' => [
                'id' => $collaborateur->id,
                'name' => $collaborateur->name,
                'email' => $collaborateur->email,
            ],
            'objectifs' => $objectifs,
            'realise' => $this->calculerRealise($collaborateur, $exerciceId),
            'realise_mensuel' => $this->calculerRealiseMensuel($collaborateur, $exerciceId),
            'taches_par_statut' => $this->calculerTachesParStatut($collaborateur, $exerciceId),
            'missions_par_prestation' => $this->calculerMissionsParPrestation($collaborateur, $exerciceId),
        ];
    }

    /**
     * Repartition des missions du collaborateur par prestation (participation :
     * equipe OU tache assignee, tous statuts confondus), scopee par exercice.
     *
     * @return list<array{prestation_id:int, designation:string, total:int}>
     */
    private function calculerMissionsParPrestation(User $collaborateur, ?int $exerciceId): array
    {
        return $this->missionsParticipation($collaborateur)
            ->join('prestations', 'prestations.id', '=', 'missions.prestation_id')
            ->when($exerciceId, fn ($q) => $q->where('missions.exercice_id', $exerciceId))
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
     * Upsert un objectif pour un collaborateur.
     */
    public function upsertObjectif(int $userId, int $exerciceId, string $type, float $valeur): KpiObjectif
    {
        return KpiObjectif::updateOrCreate(
            ['user_id' => $userId, 'exercice_id' => $exerciceId, 'type' => $type],
            ['valeur' => $valeur]
        );
    }

    /**
     * Supprime un objectif.
     */
    public function supprimerObjectif(KpiObjectif $objectif): void
    {
        $objectif->delete();
    }

    /**
     * Missions auxquelles le collaborateur PARTICIPE : membre de l'equipe
     * (pivot mission_user) OU au moins une tache qui lui est assignee.
     * Meme definition metier que MissionService::listerMissions (US-45) :
     * a la creation d'une mission on n'affecte personne — la participation
     * reelle passe par les taches.
     */
    private function missionsParticipation(User $user): Builder
    {
        return Mission::where(fn ($q) => $q
            ->whereHas('collaborateurs', fn ($c) => $c->where('users.id', $user->id))
            ->orWhereHas('taches', fn ($t) => $t->where('assigned_to', $user->id))
        );
    }

    /**
     * Calcule les KPI réalisés pour un collaborateur sur un exercice.
     *
     * @return array<string, float>
     */
    private function calculerRealise(User $user, ?int $exerciceId): array
    {
        $missionsTerminees = $this->missionsParticipation($user)
            ->where('statut', 'terminee')
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->get();

        $caHt = (float) $missionsTerminees->sum('prix_ht');
        $missionsCloturees = $missionsTerminees->count();

        // Tâches assignées à ce collaborateur
        $tachesTerminees = Tache::where('assigned_to', $user->id)
            ->where('statut', 'terminee')
            ->whereHas('mission', fn ($q) => $q
                ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            )
            ->count();

        $tachesEnRetard = Tache::where('assigned_to', $user->id)
            ->whereNotIn('statut', ['terminee'])
            ->whereNotNull('date_echeance')
            ->where('date_echeance', '<', now()->toDateString())
            ->whereHas('mission', fn ($q) => $q
                ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            )
            ->count();

        $delaiMoyenTache = $this->calculerDelaiMoyenTache($user->id, $exerciceId);

        return [
            'ca_ht' => $caHt,
            'missions_cloturees' => (float) $missionsCloturees,
            'taches_terminees' => (float) $tachesTerminees,
            'taches_en_retard' => (float) $tachesEnRetard,
            'delai_moyen_tache' => $delaiMoyenTache,
        ];
    }

    /**
     * Serie du CA HT des missions TERMINEES du collaborateur, bucketee par mois de
     * date_fin (12 valeurs). Annee = celle de l'exercice filtre, sinon annee courante.
     * Regroupement en PHP (portable SQLite/MySQL), meme approche que
     * DashboardService::calculerCaMensuel.
     *
     * @return array{annee: int, data: list<float>}
     */
    private function calculerRealiseMensuel(User $user, ?int $exerciceId): array
    {
        $exercice = $exerciceId ? Exercice::find($exerciceId) : null;
        $annee = $exercice ? (int) $exercice->annee : Carbon::now()->year;

        $data = array_fill(0, 12, 0.0);

        $this->missionsParticipation($user)
            ->where('statut', 'terminee')
            ->whereNotNull('date_fin')
            ->whereYear('date_fin', $annee)
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->get(['date_fin', 'prix_ht'])
            ->each(function (Mission $mission) use (&$data): void {
                $mois = (int) $mission->date_fin->format('n');
                $data[$mois - 1] += (float) $mission->prix_ht;
            });

        return [
            'annee' => $annee,
            'data' => array_map(fn (float $total) => round($total, 2), $data),
        ];
    }

    /**
     * Repartition des taches assignees au collaborateur par statut, scopee exercice
     * via la mission rattachee.
     *
     * @return array{a_faire:int, en_cours:int, terminee:int, bloquee:int}
     */
    private function calculerTachesParStatut(User $user, ?int $exerciceId): array
    {
        $tacheQuery = fn () => Tache::where('assigned_to', $user->id)
            ->whereHas('mission', fn ($q) => $q
                ->when($exerciceId, fn ($q2) => $q2->where('exercice_id', $exerciceId))
            );

        return [
            'a_faire' => $tacheQuery()->where('statut', 'a_faire')->count(),
            'en_cours' => $tacheQuery()->where('statut', 'en_cours')->count(),
            'terminee' => $tacheQuery()->where('statut', 'terminee')->count(),
            'bloquee' => $tacheQuery()->where('statut', 'bloquee')->count(),
        ];
    }

    /**
     * Délai moyen de traitement d'une tâche (de la création à la clôture).
     * Calculé en PHP via Carbon pour être compatible MySQL et SQLite (tests).
     */
    private function calculerDelaiMoyenTache(int $userId, ?int $exerciceId): float
    {
        $delais = Tache::where('assigned_to', $userId)
            ->where('statut', 'terminee')
            ->whereHas('mission', fn ($q) => $q
                ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            )
            ->get(['created_at', 'updated_at'])
            // Carbon 3 : diffInDays renvoie un float (jours decimaux) — ne pas typer int
            ->map(fn (Tache $t) => $t->created_at->diffInDays($t->updated_at))
            ->filter(fn (float $d) => $d >= 0);

        if ($delais->isEmpty()) {
            return 0.0;
        }

        return round((float) $delais->average(), 1);
    }
}
