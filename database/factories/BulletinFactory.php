<?php

namespace Database\Factories;

use App\Models\Bulletin;
use App\Models\Inscription;
use App\Models\Trimestre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bulletin>
 */
class BulletinFactory extends Factory
{
    protected $model = Bulletin::class;

    public function definition(): array
    {
        return [
            'inscription_id' => Inscription::factory(),
            'trimestre_id' => Trimestre::factory(),
            'moyenne_generale' => fake()->randomFloat(2, 5, 20),
            'appreciation' => fake()->randomElement(['Excellent trimestre', 'Bon travail, continuez', 'Peut mieux faire', 'Résultats en baisse, attention']),
            'rang' => fake()->numberBetween(1, 40),
            'date_generation' => fake()->date(),
        ];
    }
}
