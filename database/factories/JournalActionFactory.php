<?php

namespace Database\Factories;

use App\Models\JournalAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalAction>
 */
class JournalActionFactory extends Factory
{
    protected $model = JournalAction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                'connexion', 'creation_eleve', 'modification_eleve', 'archivage_eleve',
                'saisie_note', 'generation_bulletin', 'creation_compte',
            ]),
            'date_heure' => fake()->dateTimeBetween('-6 months', 'now'),
            'details' => fake()->optional()->sentence(),
            'adresse_ip' => fake()->ipv4(),
        ];
    }
}
