<?php

namespace Database\Factories;

use App\Enums\SystemeScolaire;
use App\Enums\TypeEvaluation;
use App\Models\AnneeAcademique;
use App\Models\Examen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Examen>
 */
class ExamenFactory extends Factory
{
    protected $model = Examen::class;

    public function definition(): array
    {
        return [
            'annee_academique_id' => AnneeAcademique::factory(),
            'systeme' => SystemeScolaire::Primaire,
            'type' => TypeEvaluation::EvaluationMensuelle,
            'date_examen' => fake()->dateTimeBetween('now', '+1 month'),
            'date_limite_saisie' => fake()->dateTimeBetween('+1 month', '+2 months'),
        ];
    }
}
