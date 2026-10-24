<?php

namespace Database\Factories;

use App\Models\ChampFormation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChampFormation>
 */
class ChampFormationFactory extends Factory
{
    protected $model = ChampFormation::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->randomElement([
                'Français', 'Mathématique', 'EA', 'Anglais', 'ES', 'EST', 'EPS',
            ]),
            'ordre' => fake()->numberBetween(1, 20),
        ];
    }
}
