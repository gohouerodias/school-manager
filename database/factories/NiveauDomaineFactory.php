<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use App\Models\DomaineEvaluation;
use App\Models\Niveau;
use App\Models\NiveauDomaine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NiveauDomaine>
 */
class NiveauDomaineFactory extends Factory
{
    protected $model = NiveauDomaine::class;

    public function definition(): array
    {
        return [
            'niveau_id' => Niveau::factory(),
            'domaine_evaluation_id' => DomaineEvaluation::factory(),
            'annee_academique_id' => AnneeAcademique::factory(),
        ];
    }
}
