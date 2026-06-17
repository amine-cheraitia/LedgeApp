<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Mission;
use App\Models\Tache;

class CalendarService
{
    /**
     * Retourne les événements (missions + tâches) pour la plage de dates donnée.
     *
     * Un overlap mission/range se produit si :
     *   - date_debut est dans le range, OU
     *   - date_fin   est dans le range, OU
     *   - la mission englobe tout le range (date_debut <= from ET date_fin >= to)
     */
    public function getEvents(array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];
        $collaborateurId = isset($filters['collaborateur_id']) ? (int) $filters['collaborateur_id'] : null;

        $missions = $this->fetchMissions($from, $to, $collaborateurId);
        $taches = $this->fetchTaches($from, $to, $collaborateurId);

        return [
            'missions' => $missions,
            'taches' => $taches,
        ];
    }

    private function fetchMissions(string $from, string $to, ?int $collaborateurId): array
    {
        $query = Mission::with(['entreprise', 'prestation'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('date_debut', [$from, $to])
                    ->orWhereBetween('date_fin', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->where('date_debut', '<=', $from)
                            ->where('date_fin', '>=', $to);
                    });
            });

        if ($collaborateurId !== null) {
            $query->whereHas('collaborateurs', fn ($q) => $q->where('users.id', $collaborateurId));
        }

        return $query->get()->map(fn (Mission $m) => [
            'id' => $m->id,
            'type' => 'mission',
            'reference' => $m->reference,
            'titre' => $m->entreprise?->raison_sociale.' — '.$m->prestation?->designation,
            'date_debut' => $m->date_debut?->toDateString(),
            'date_fin' => $m->date_fin?->toDateString(),
            'statut' => $m->statut,
            'prix_ht' => (float) $m->prix_ht,
            'entreprise' => $m->entreprise?->raison_sociale,
            'prestation' => $m->prestation?->designation,
        ])->all();
    }

    private function fetchTaches(string $from, string $to, ?int $collaborateurId): array
    {
        $query = Tache::with(['mission.entreprise', 'assignee'])
            ->whereNotNull('date_echeance')
            ->whereBetween('date_echeance', [$from, $to]);

        if ($collaborateurId !== null) {
            $query->where('assigned_to', $collaborateurId);
        }

        return $query->get()->map(fn (Tache $t) => [
            'id' => $t->id,
            'type' => 'tache',
            'titre' => $t->titre,
            'date_echeance' => $t->date_echeance?->toDateString(),
            'statut' => $t->statut,
            'priorite' => $t->priorite,
            'mission_id' => $t->mission_id,
            'mission_ref' => $t->mission?->reference,
            'entreprise' => $t->mission?->entreprise?->raison_sociale,
            'assignee' => $t->assignee?->name,
        ])->all();
    }
}
