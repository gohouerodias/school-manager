<?php

namespace Database\Factories;

use App\Models\Eleve;
use App\Models\ObservationAdministrative;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ObservationAdministrative>
 */
class ObservationAdministrativeFactory extends Factory
{
    protected $model = ObservationAdministrative::class;

    public function definition(): array
    {
        return [
            'eleve_id' => Eleve::factory(),
            'auteur_id' => User::factory()->administrateur(),
            'date' => fake()->date(),
            'contenu' => fake()->randomElement([
                'Absence justifiée par les parents.',
                'Retard répété, à surveiller.',
                'Bon comportement en classe.',
                'Frais de scolarité en retard.',
                'Changement d\'adresse signalé par le tuteur.',
            ]),
        ];
    }
}
