<?php

namespace Database\Factories;

use App\Models\TypeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TypeDocument>
 */
class TypeDocumentFactory extends Factory
{
    protected $model = TypeDocument::class;

    public function definition(): array
    {
        return [
            'libelle' => fake()->randomElement(['Photo d\'identité', 'Acte de naissance', 'CIP', 'NPI', 'Certificat médical']),
            'description' => fake()->optional()->sentence(),
            'obligatoire' => fake()->boolean(70),
        ];
    }
}
