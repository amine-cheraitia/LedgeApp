<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\KpiObjectif;
use App\Models\Mission;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            'objectifs' => $user->kpiObjectifs->keyBy('type')->map(fn ($o) => (float) $o->valeur),
            'realise' => $this->calculerRealise($user, $exerciceId),
        ]);
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
     * Calcule les KPI réalisés pour un collaborateur sur un exercice.
     *
     * @return array<string, float>
     */
    private function calculerRealise(User $user, ?int $exerciceId): array
    {
        // Missions assignées à ce collaborateur via la table pivot mission_user
        $missionIds = DB::table('mission_user')
            ->where('user_id', $user->id)
            ->pluck('mission_id');

        $missionsTerminees = Mission::whereIn('id', $missionIds)
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
            ->map(fn (Tache $t) => $t->created_at->diffInDays($t->updated_at))
            ->filter(fn (int $d) => $d >= 0);

        if ($delais->isEmpty()) {
            return 0.0;
        }

        return round((float) $delais->average(), 1);
    }
}
