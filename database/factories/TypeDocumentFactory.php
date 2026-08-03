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
            'formats_acceptes' => fake()->randomElements(['PDF', 'JPG', 'PNG'], fake()->numberBetween(1, 3)),
            'obligatoire' => fake()->boolean(70),
            'protege' => false,
        ];
    }

    /**
     * A type de document locked against edits/deletion in "Paramètres des
     * dossiers" — see TypeDocumentController.
     */
    public function protege(): static
    {
        return $this->state(fn () => ['protege' => true]);
    }
}
