<?php

namespace Database\Factories;

use App\Enums\CycleNiveau;
use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Niveau>
 */
class NiveauFactory extends Factory
{
    protected $model = Niveau::class;

    public function definition(): array
    {
        return [
            'libelle' => fake()->randomElement(['CI', 'CP', 'CE1', 'CE2', 'CM1', 'CM2', '6e', '5e', '4e', '3e']),
            'ordre' => fake()->unique()->numberBetween(1, 10),
            'cycle' => fake()->randomElement([CycleNiveau::Primaire, CycleNiveau::College]),
            'premiere_scolarisation' => false,
        ];
    }

    /**
     * Maternelle 1 / Maternelle 2 — see Niveau::$premiere_scolarisation.
     */
    public function maternelle(): static
    {
        return $this->state(fn () => [
            'libelle' => fake()->randomElement(['Maternelle 1', 'Maternelle 2']),
            'cycle' => CycleNiveau::Maternelle,
            'premiere_scolarisation' => true,
        ]);
    }

    /**
     * Explicit collège niveau — the "one teacher per matière" affectation
     * model (see StoreAffectationEnseignantRequest::classeEstEnModeEntiere()).
     */
    public function college(): static
    {
        return $this->state(fn () => [
            'libelle' => fake()->randomElement(['6e', '5e', '4e', '3e']),
            'cycle' => CycleNiveau::College,
            'premiere_scolarisation' => false,
        ]);
    }

    /**
     * Explicit primaire niveau — the "one teacher for the whole classe"
     * affectation model (see StoreAffectationEnseignantRequest::classeEstEnModeEntiere()).
     */
    public function primaire(): static
    {
        return $this->state(fn () => [
            'libelle' => fake()->randomElement(['CI', 'CP', 'CE1', 'CE2', 'CM1', 'CM2']),
            'cycle' => CycleNiveau::Primaire,
            'premiere_scolarisation' => false,
        ]);
    }
}
