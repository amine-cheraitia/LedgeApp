<?php

namespace Database\Factories;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Mission;
use App\Models\Prestation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mission>
 */
class MissionFactory extends Factory
{
    protected $model = Mission::class;

    public function definition(): array
    {
        $debut = fake()->dateTimeBetween('now', '+3 months');
        $fin = fake()->dateTimeBetween($debut, '+6 months');

        return [
            'entreprise_id' => Entreprise::factory()->client(),
            'exercice_id' => Exercice::factory(),
            'prestation_id' => Prestation::first()?->id ?? 1,
            'reference' => 'M'.now()->year.'-'.str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'prix_ht' => fake()->randomFloat(2, 50000, 500000),
            'date_debut' => $debut->format('Y-m-d'),
            'date_fin' => $fin->format('Y-m-d'),
            'statut' => 'en_cours',
            'notes' => null,
        ];
    }

    public function terminee(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'terminee',
        ]);
    }

    public function suspendue(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'suspendue',
        ]);
    }
}
