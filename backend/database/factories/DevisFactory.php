<?php

namespace Database\Factories;

use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Devis>
 */
class DevisFactory extends Factory
{
    protected $model = Devis::class;

    public function definition(): array
    {
        return [
            'entreprise_id' => Entreprise::factory(),
            'exercice_id' => Exercice::factory(),
            'created_by' => User::factory(),
            'numero' => 'DV'.now()->year.'-'.str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'date_devis' => now()->toDateString(),
            'date_validite' => now()->addDays(30)->toDateString(),
            'montant_ht' => fake()->randomFloat(2, 10000, 500000),
            'montant_tva' => fake()->randomFloat(2, 1000, 100000),
            'montant_timbre' => fake()->randomFloat(2, 100, 2500),
            'montant_ttc' => fake()->randomFloat(2, 15000, 600000),
            'statut' => 'brouillon',
            'notes' => null,
        ];
    }

    public function brouillon(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'brouillon',
        ]);
    }

    public function envoye(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'envoye',
        ]);
    }

    public function accepte(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'accepte',
        ]);
    }
}
