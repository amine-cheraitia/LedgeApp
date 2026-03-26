<?php

namespace Database\Factories;

use App\Models\Exercice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercice>
 */
class ExerciceFactory extends Factory
{
    protected $model = Exercice::class;

    private static int $yearOffset = 0;

    public function definition(): array
    {
        $annee = 2020 + self::$yearOffset++;

        return [
            'annee' => $annee,
            'date_ouverture' => "{$annee}-01-01",
            'date_cloture' => null,
            'statut' => 'ouvert',
        ];
    }

    public function ouvert(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'ouvert',
            'date_cloture' => null,
        ]);
    }

    public function cloture(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'cloture',
            'date_cloture' => $attributes['date_ouverture']
                ? date('Y-12-31', strtotime($attributes['date_ouverture']))
                : now()->toDateString(),
        ]);
    }
}
