<?php

namespace Database\Factories;

use App\Models\Mission;
use App\Models\Tache;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tache>
 */
class TacheFactory extends Factory
{
    protected $model = Tache::class;

    public function definition(): array
    {
        return [
            'mission_id' => Mission::factory(),
            'assigned_to' => null,
            'titre' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'statut' => 'a_faire',
            // date_debut <= date_echeance par construction (intervalles disjoints)
            'date_debut' => fake()->optional()->dateTimeBetween('now', '+1 month')?->format('Y-m-d'),
            'date_echeance' => fake()->optional()->dateTimeBetween('+1 month', '+2 months')?->format('Y-m-d'),
            'priorite' => fake()->numberBetween(1, 4),
        ];
    }

    public function enCours(): static
    {
        return $this->state(fn () => ['statut' => 'en_cours']);
    }

    public function terminee(): static
    {
        return $this->state(fn () => ['statut' => 'terminee']);
    }

    public function assigneA(User $user): static
    {
        return $this->state(fn () => ['assigned_to' => $user->id]);
    }
}
