<?php

namespace Database\Factories;

use App\Enums\StatutTrimestre;
use App\Models\AnneeAcademique;
use App\Models\Trimestre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trimestre>
 */
class TrimestreFactory extends Factory
{
    protected $model = Trimestre::class;

    public function definition(): array
    {
        return [
            'annee_academique_id' => AnneeAcademique::factory(),
            'nom' => 'Trimestre 1',
            'ordre' => 1,
            'date_debut' => fake()->date(),
            'date_fin' => fake()->date(),
            'statut' => StatutTrimestre::Ferme,
        ];
    }
}
