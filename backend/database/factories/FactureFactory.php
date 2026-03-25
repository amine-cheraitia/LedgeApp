<?php

namespace Database\Factories;

use App\Models\Entreprise;
use App\Models\Exercice;
use App\Models\Facture;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facture>
 */
class FactureFactory extends Factory
{
    protected $model = Facture::class;

    public function definition(): array
    {
        return [
            'entreprise_id' => Entreprise::factory(),
            'exercice_id' => Exercice::factory(),
            'created_by' => User::factory(),
            'tva_rate_id' => null, // set in tests
            'timbre_rate_id' => null, // set in tests
            'numero' => 'FF'.now()->year.'-'.str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'type' => 'FF',
            'date_facture' => now()->toDateString(),
            'date_echeance' => now()->addDays(30)->toDateString(),
            'montant_ht' => fake()->randomFloat(2, 10000, 500000),
            'taux_tva' => 19.00,
            'montant_tva' => fake()->randomFloat(2, 1000, 100000),
            'montant_timbre' => fake()->randomFloat(2, 100, 2500),
            'montant_ttc' => fake()->randomFloat(2, 15000, 600000),
            'montant_paye' => 0,
            'statut_paiement' => 'en_attente',
            'notes' => null,
        ];
    }

    public function solde(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut_paiement' => 'solde',
            'montant_paye' => $attributes['montant_ttc'] ?? 100000,
        ]);
    }

    public function partiel(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut_paiement' => 'partiel',
        ]);
    }

    public function avoir(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'FA',
            'numero' => 'FA'.now()->year.'-'.str_pad(fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
        ]);
    }
}
