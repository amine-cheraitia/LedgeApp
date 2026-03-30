<?php

namespace Database\Factories;

use App\Models\Devis;
use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Prestation;
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
        $prixHt = fake()->randomFloat(2, 80000, 600000);

        return [
            'entreprise_id' => Entreprise::factory(),
            'prestation_id' => Prestation::factory(),
            'exercice_id' => Exercice::factory(),
            'created_by' => User::factory(),
            'numero' => 'DV'.now()->year.'-'.str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'date_devis' => now()->toDateString(),
            'date_validite' => now()->addDays(30)->toDateString(),
            'prix_ht' => $prixHt,
            'montant_ht' => $prixHt,
            'montant_tva' => round($prixHt * 0.19, 2),
            'montant_timbre' => min(round($prixHt * 0.01, 2), 2500),
            'montant_ttc' => round($prixHt * 1.19 + min(round($prixHt * 0.01, 2), 2500), 2),
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
