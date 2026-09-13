<?php

namespace Database\Factories;

use App\Models\DomaineEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DomaineEvaluation>
 */
class DomaineEvaluationFactory extends Factory
{
    protected $model = DomaineEvaluation::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->randomElement([
                'Fréquentation', 'Propreté corporelle et vestimentaire', 'Dessin/Coloriage', 'Langage',
                'Pré Ecriture', 'Education du mouvement', 'Pré-Lecture', 'Pré-mathématique',
                'Poésie et Chant', 'Anglais', 'Autres',
            ]),
        ];
    }
}
