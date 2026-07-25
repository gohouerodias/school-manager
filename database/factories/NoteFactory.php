<?php

namespace Database\Factories;

use App\Enums\TypeEvaluation;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Note;
use App\Models\Trimestre;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'eleve_id' => Eleve::factory(),
            'classe_matiere_id' => ClasseMatiere::factory(),
            'trimestre_id' => Trimestre::factory(),
            'enseignant_id' => User::factory()->enseignant(),
            'valeur' => fake()->randomFloat(2, 4, 20),
            'type' => fake()->randomElement(TypeEvaluation::cases()),
            'numero' => fake()->numberBetween(1, 3),
            'date_saisie' => fake()->date(),
        ];
    }
}
