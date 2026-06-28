<?php

declare(strict_types=1);

namespace App\Http\Requests\Planning\Concerns;

use App\Models\Mission;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Regles de validation partagees entre Store/UpdateTacheRequest :
 * - bornage des dates de la tache par rapport a sa mission (avec exception "mission en retard")
 * - restriction de l'assignation aux roles admin / collaborateur.
 *
 * @mixin FormRequest
 */
trait ValidatesTacheDates
{
    /**
     * Regles dynamiques sur date_debut / date_echeance selon la mission de la route.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function tacheDateRules(): array
    {
        $mission = $this->route('mission');

        // before_or_equal:date_echeance reste en tete (preserve les tests existants).
        $debutRules = ['nullable', 'date', 'before_or_equal:date_echeance'];
        $echeanceRules = ['nullable', 'date'];

        if ($mission instanceof Mission) {
            if ($mission->date_debut !== null) {
                $debutRules[] = 'after_or_equal:'.$mission->date_debut->toDateString();
            }
            // Borne haute uniquement si la mission a une fin planifiee ET n'est pas en retard.
            if ($mission->date_fin !== null && ! $this->missionEnRetard($mission)) {
                $echeanceRules[] = 'before_or_equal:'.$mission->date_fin->toDateString();
            }
        }

        return [
            'date_debut' => $debutRules,
            'date_echeance' => $echeanceRules,
        ];
    }

    /**
     * Mission en retard = fin planifiee deja depassee (comparaison a la journee).
     */
    protected function missionEnRetard(Mission $mission): bool
    {
        return $mission->date_fin !== null
            && Carbon::today()->gt($mission->date_fin->copy()->startOfDay());
    }

    /**
     * Regle d'assignation : une tache ne peut etre confiee qu'a un collaborateur ou un admin.
     */
    protected function assignableRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $user = User::find($value);
            if ($user === null) {
                return; // la regle 'exists' s'en charge deja
            }

            if (! $user->hasAnyRole(['admin', 'collaborateur'])) {
                $fail("Une tâche ne peut être assignée qu'à un collaborateur ou un administrateur.");
            }
        };
    }

    /**
     * Messages FR pour les regles de dates tache/mission.
     *
     * @return array<string, string>
     */
    protected function tacheDateMessages(): array
    {
        return [
            'date_debut.after_or_equal' => 'La date de début de la tâche ne peut pas précéder le début de la mission (:date).',
            'date_debut.before_or_equal' => "La date de début doit être antérieure ou égale à l'échéance.",
            'date_echeance.before_or_equal' => "L'échéance de la tâche ne peut pas dépasser la fin de la mission (:date).",
        ];
    }
}
