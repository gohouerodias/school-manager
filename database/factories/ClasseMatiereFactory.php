<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\Matiere;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClasseMatiere>
 */
class ClasseMatiereFactory extends Factory
{
    protected $model = ClasseMatiere::class;

    public function definition(): array
    {
        return [
            'classe_id' => Classe::factory(),
            'matiere_id' => Matiere::factory(),
            'coefficient' => fake()->randomElement([1, 2, 3, 4]),
        ];
    }
}
