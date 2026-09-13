<?php

namespace Database\Factories;

use App\Models\ClasseMatiere;
use App\Models\Inscription;
use App\Models\MoyenneAnnuelleMatiere;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoyenneAnnuelleMatiere>
 */
class MoyenneAnnuelleMatiereFactory extends Factory
{
    protected $model = MoyenneAnnuelleMatiere::class;

    public function definition(): array
    {
        return [
            'inscription_id' => Inscription::factory(),
            'classe_matiere_id' => ClasseMatiere::factory(),
            'moyenne' => fake()->randomFloat(2, 4, 20),
            'calculee_at' => now(),
        ];
    }
}
