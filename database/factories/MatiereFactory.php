<?php

namespace Database\Factories;

use App\Models\Matiere;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matiere>
 */
class MatiereFactory extends Factory
{
    protected $model = Matiere::class;

    public function definition(): array
    {
        return [
            'nom' => fake()->randomElement([
                'Français', 'Mathématiques', 'Sciences de la Vie et de la Terre', 'Histoire-Géographie',
                'Anglais', 'Éducation Civique et Morale', 'Arts Plastiques', 'Éducation Physique et Sportive',
                'Informatique', 'Physique-Chimie',
            ]),
        ];
    }
}
