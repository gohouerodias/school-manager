<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classe>
 */
class ClasseFactory extends Factory
{
    protected $model = Classe::class;

    public function definition(): array
    {
        return [
            'niveau_id' => Niveau::factory(),
            'annee_academique_id' => AnneeAcademique::factory(),
            'nom' => fake()->randomElement(['A', 'B', 'C']),
        ];
    }
}
