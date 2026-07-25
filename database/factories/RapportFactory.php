<?php

namespace Database\Factories;

use App\Enums\FormatRapport;
use App\Enums\TypeRapport;
use App\Models\Rapport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rapport>
 */
class RapportFactory extends Factory
{
    protected $model = Rapport::class;

    public function definition(): array
    {
        return [
            'genere_par' => User::factory(),
            'type' => fake()->randomElement(TypeRapport::cases()),
            'format' => fake()->randomElement(FormatRapport::cases()),
            'date_generation' => fake()->date(),
        ];
    }
}
