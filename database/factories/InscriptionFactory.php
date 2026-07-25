<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscription>
 */
class InscriptionFactory extends Factory
{
    protected $model = Inscription::class;

    public function definition(): array
    {
        return [
            'eleve_id' => Eleve::factory(),
            'classe_id' => Classe::factory(),
            'date_inscription' => fake()->date(),
            'moyenne_annuelle' => null,
            'decision' => null,
        ];
    }
}
