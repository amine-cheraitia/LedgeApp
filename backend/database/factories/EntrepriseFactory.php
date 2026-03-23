<?php

namespace Database\Factories;

use App\Models\Entreprise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entreprise>
 */
class EntrepriseFactory extends Factory
{
    protected $model = Entreprise::class;

    public function definition(): array
    {
        return [
            'raison_sociale'    => $this->faker->company(),
            'nif'               => $this->faker->unique()->numerify('##########'),
            'nis'               => $this->faker->unique()->numerify('##############'),
            'num_rc'            => $this->faker->numerify('##/##-######B##'),
            'article_imposition'=> $this->faker->numerify('######'),
            'regime_fiscal'     => $this->faker->randomElement(['forfait', 'reel']),
            'categorie'         => $this->faker->randomElement(['TPE', 'PME', 'GE']),
            'secteur_activite'  => $this->faker->sentence(3),
            'adresse'           => $this->faker->streetAddress(),
            'ville'             => $this->faker->city(),
            'wilaya'            => $this->faker->randomElement(['Alger', 'Oran', 'Constantine', 'Annaba']),
            'telephone'         => $this->faker->numerify('0#########'),
            'email'             => $this->faker->unique()->companyEmail(),
            'contact_principal' => $this->faker->name(),
            'statut'            => 'prospect',
            'notes'             => null,
        ];
    }

    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'client',
        ]);
    }

    public function prospect(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'prospect',
        ]);
    }
}
