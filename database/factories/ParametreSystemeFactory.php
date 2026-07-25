<?php

namespace Database\Factories;

use App\Models\ParametreSysteme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParametreSysteme>
 */
class ParametreSystemeFactory extends Factory
{
    protected $model = ParametreSysteme::class;

    public function definition(): array
    {
        return [
            'duree_conservation_donnees' => 60,
        ];
    }
}
