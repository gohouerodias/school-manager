<?php

namespace Database\Factories;

use App\Models\ClasseMatiere;
use App\Models\CommentaireMatiere;
use App\Models\Eleve;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentaireMatiere>
 */
class CommentaireMatiereFactory extends Factory
{
    protected $model = CommentaireMatiere::class;

    public function definition(): array
    {
        return [
            'eleve_id' => Eleve::factory(),
            'classe_matiere_id' => ClasseMatiere::factory(),
            'examen_id' => Examen::factory(),
            'enseignant_id' => User::factory()->enseignant(),
            'commentaire' => fake()->sentence(),
        ];
    }
}
