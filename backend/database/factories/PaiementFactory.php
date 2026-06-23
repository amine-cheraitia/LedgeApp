<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Facture;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paiement>
 */
class PaiementFactory extends Factory
{
    protected $model = Paiement::class;

    public function definition(): array
    {
        return [
            'facture_id'    => Facture::factory(),
            'recorded_by'   => User::factory(),
            'montant'       => fake()->randomFloat(2, 1000, 50000),
            'date_paiement' => fake()->dateThisYear()->format('Y-m-d'),
            'mode_paiement' => fake()->randomElement(['virement', 'cheque', 'autre']),
            'reference'     => null,
            'notes'         => null,
        ];
    }
}
