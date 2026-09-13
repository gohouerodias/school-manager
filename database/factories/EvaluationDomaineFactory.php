<?php

namespace Database\Factories;

use App\Enums\NiveauQualitatif;
use App\Models\ClasseDomaine;
use App\Models\Eleve;
use App\Models\EvaluationDomaine;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationDomaine>
 */
class EvaluationDomaineFactory extends Factory
{
    protected $model = EvaluationDomaine::class;

    public function definition(): array
    {
        return [
            'eleve_id' => Eleve::factory(),
            'classe_domaine_id' => ClasseDomaine::factory(),
            'examen_id' => Examen::factory(),
            'enseignant_id' => User::factory()->enseignant(),
            'valeur' => fake()->randomElement(NiveauQualitatif::cases()),
            'observation' => fake()->optional()->sentence(),
            'date_saisie' => fake()->date(),
        ];
    }
}
