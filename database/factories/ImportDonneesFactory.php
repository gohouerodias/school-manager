<?php

namespace Database\Factories;

use App\Enums\StatutImport;
use App\Enums\TypeImportDonnees;
use App\Models\ImportDonnees;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportDonnees>
 */
class ImportDonneesFactory extends Factory
{
    protected $model = ImportDonnees::class;

    public function definition(): array
    {
        return [
            'importe_par' => User::factory()->administrateur(),
            'type_import' => fake()->randomElement(TypeImportDonnees::cases()),
            'fichier_source' => fake()->word().'.xlsx',
            'date_import' => fake()->date(),
            'nombre_lignes_importees' => fake()->numberBetween(10, 200),
            'nombre_erreurs' => fake()->numberBetween(0, 5),
            'statut' => StatutImport::Termine,
        ];
    }
}
