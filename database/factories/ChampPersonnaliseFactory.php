<?php

namespace Database\Factories;

use App\Enums\TypeChampPersonnalise;
use App\Models\ChampPersonnalise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChampPersonnalise>
 */
class ChampPersonnaliseFactory extends Factory
{
    protected $model = ChampPersonnalise::class;

    public function definition(): array
    {
        return [
            'libelle' => fake()->unique()->randomElement([
                'Lieu de naissance', 'Nationalité', 'Adresse', 'Quartier', 'Allergies',
            ]),
            'type' => TypeChampPersonnalise::Texte,
            'options' => null,
            'obligatoire' => fake()->boolean(60),
            'ordre' => fake()->numberBetween(1, 10),
        ];
    }

    public function listeDeroulante(array $options): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TypeChampPersonnalise::ListeDeroulante,
            'options' => $options,
        ]);
    }
}
