<?php

namespace Database\Factories;

use App\Models\DocumentNumerique;
use App\Models\Eleve;
use App\Models\TypeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentNumerique>
 */
class DocumentNumeriqueFactory extends Factory
{
    protected $model = DocumentNumerique::class;

    public function definition(): array
    {
        return [
            'eleve_id' => Eleve::factory(),
            'type_document_id' => TypeDocument::factory(),
            'televerse_par' => User::factory()->agentScolarite(),
            'chemin_fichier' => 'documents/'.fake()->uuid().'.pdf',
            'date_ajout' => fake()->date(),
        ];
    }
}
