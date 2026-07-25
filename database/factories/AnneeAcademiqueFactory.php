<?php

namespace Database\Factories;

use App\Models\AnneeAcademique;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnneeAcademique>
 */
class AnneeAcademiqueFactory extends Factory
{
    protected $model = AnneeAcademique::class;

    public function definition(): array
    {
        $anneeDebut = fake()->numberBetween(2020, 2026);

        return [
            'libelle' => "{$anneeDebut}-".($anneeDebut + 1),
            'date_debut' => "{$anneeDebut}-10-01",
            'date_fin' => ($anneeDebut + 1).'-07-31',
        ];
    }
}
