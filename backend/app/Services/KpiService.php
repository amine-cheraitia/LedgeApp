<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Exercice;
use App\Models\Facture;
use App\Models\KpiObjectif;
use App\Models\Mission;
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
        $missionIds = DB::table('mission_user')
            ->where('user_id', $user->id)
            ->pluck('mission_id');

        $missionsTerminees = Mission::whereIn('id', $missionIds)
            ->where('statut', 'terminee')
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->get();

        $caHt = (float) $missionsTerminees->sum('prix_ht');
        $missionsCloturees = $missionsTerminees->count();

        $delaiMoyen = $this->calculerDelaiMoyenFacturation($missionIds, $exerciceId);

        return [
            'ca_ht' => $caHt,
            'missions_cloturees' => (float) $missionsCloturees,
            'delai_moyen_facturation' => $delaiMoyen,
        ];
    }

    /**
     * Délai moyen entre date_debut mission et date première facture (en jours).
     */
    private function calculerDelaiMoyenFacturation(Collection $missionIds, ?int $exerciceId): float
    {
        if ($missionIds->isEmpty()) {
            return 0.0;
        }

        $delais = Facture::where('factures.type', 'FF')
            ->whereIn('factures.mission_id', $missionIds)
            ->when($exerciceId, fn ($q) => $q->where('factures.exercice_id', $exerciceId))
            ->join('missions', 'factures.mission_id', '=', 'missions.id')
            ->selectRaw('CAST(JULIANDAY(MIN(factures.date_facture)) - JULIANDAY(missions.date_debut) AS INTEGER) as delai, factures.mission_id')
            ->groupBy('factures.mission_id', 'missions.date_debut')
            ->pluck('delai')
            ->filter(fn ($d) => $d !== null && $d >= 0);

        if ($delais->isEmpty()) {
            return 0.0;
        }

        return round((float) $delais->average(), 1);
    }
}
