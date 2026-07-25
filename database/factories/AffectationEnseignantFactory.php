<?php

namespace Database\Factories;

use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Matiere;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffectationEnseignant>
 */
class AffectationEnseignantFactory extends Factory
{
    protected $model = AffectationEnseignant::class;

    public function definition(): array
    {
        return [
            'enseignant_id' => User::factory()->enseignant(),
            'classe_id' => Classe::factory(),
            'matiere_id' => Matiere::factory(),
            'annee_academique_id' => AnneeAcademique::factory(),
            'est_professeur_principal' => false,
        ];
    }
}
