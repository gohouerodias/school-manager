<?php

namespace Database\Factories;

use App\Enums\StatutGenerationBulletin;
use App\Models\Classe;
use App\Models\DemandeGenerationBulletin;
use App\Models\Examen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemandeGenerationBulletin>
 */
class DemandeGenerationBulletinFactory extends Factory
{
    protected $model = DemandeGenerationBulletin::class;

    public function definition(): array
    {
        return [
            'classe_id' => Classe::factory(),
            'examen_id' => Examen::factory(),
            'demande_par_id' => User::factory()->administrateur(),
            'demande_at' => now(),
            'statut' => StatutGenerationBulletin::EnAttente,
            'genere_at' => null,
            'nb_bulletins_generes' => null,
            'total' => 0,
            'traites' => 0,
            'chemin_pdf' => null,
            'erreur' => null,
        ];
    }

    /**
     * Une demande déjà traitée avec succès par GenererBulletinsClasseJob.
     */
    public function generee(): static
    {
        return $this->state(fn () => [
            'statut' => StatutGenerationBulletin::Termine,
            'genere_at' => now(),
            'nb_bulletins_generes' => fake()->numberBetween(10, 40),
            'total' => fake()->numberBetween(10, 40),
            'traites' => fn (array $attributes) => $attributes['total'],
            'chemin_pdf' => 'bulletins/demo.zip',
        ]);
    }

    /**
     * Une demande en cours de traitement par le worker de la file.
     */
    public function enCours(int $traites = 3, int $total = 10): static
    {
        return $this->state(fn () => [
            'statut' => StatutGenerationBulletin::EnCours,
            'total' => $total,
            'traites' => $traites,
        ]);
    }

    /**
     * Une demande dont le job a échoué.
     */
    public function echouee(string $erreur = 'Erreur inattendue.'): static
    {
        return $this->state(fn () => [
            'statut' => StatutGenerationBulletin::Echec,
            'erreur' => $erreur,
        ]);
    }
}
