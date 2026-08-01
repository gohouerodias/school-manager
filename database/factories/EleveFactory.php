<?php

namespace Database\Factories;

use App\Enums\StatutEleve;
use App\Models\Eleve;
use App\Support\BeninData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Eleve>
 */
class EleveFactory extends Factory
{
    protected $model = Eleve::class;

    public function definition(): array
    {
        $sexe = fake()->randomElement(['M', 'F']);
        $prenom = $sexe === 'F'
            ? fake()->randomElement(BeninData::$prenomsFeminins)
            : fake()->randomElement(BeninData::$prenomsMasculins);
        $nom = fake()->randomElement(BeninData::$noms);

        return [
            'matricule' => 'CSC-'.fake()->unique()->numerify('######'),
            'nom' => $nom,
            'prenom' => $prenom,
            'date_naissance' => fake()->dateTimeBetween('-16 years', '-4 years')->format('Y-m-d'),
            'sexe' => $sexe,
            'statut' => StatutEleve::Actif,
            'date_archivage' => null,
        ];
    }

    public function archive(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => StatutEleve::Archive,
            'date_archivage' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ]);
    }
}
