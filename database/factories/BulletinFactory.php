<?php

namespace Database\Factories;

use App\Enums\ResultatMensuel;
use App\Enums\StatutBulletin;
use App\Models\Bulletin;
use App\Models\Examen;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bulletin>
 */
class BulletinFactory extends Factory
{
    protected $model = Bulletin::class;

    public function definition(): array
    {
        return [
            'inscription_id' => Inscription::factory(),
            'examen_id' => Examen::factory(),
            'moyenne_generale' => fake()->randomFloat(2, 5, 20),
            'appreciation' => fake()->randomElement(['Excellent mois', 'Bon travail, continuez', 'Peut mieux faire', 'Résultats en baisse, attention']),
            'resultat_global' => fake()->randomElement(ResultatMensuel::cases()),
            'assiduite' => fake()->randomElement(['Bonne', 'Assez bonne', 'Irrégulière']),
            'conduite' => fake()->randomElement(['Bonne', 'Assez bonne', 'À améliorer']),
            'defauts_majeurs' => fake()->optional()->sentence(4),
            'qualites' => fake()->randomElement(['Sérieux, ponctuel', 'Dynamique et attentif', 'Calme et appliqué']),
            'decision_pedagogique' => fake()->optional()->randomElement(['lecture et en écriture', 'calcul et en orthographe']),
            'statut' => StatutBulletin::Brouillon,
            'rang' => fake()->numberBetween(1, 40),
            'date_generation' => fake()->date(),
        ];
    }

    /**
     * A bulletin already "signé" by a titulaire (see US C.2) — notes and
     * commentaires of its période sont verrouillés tant qu'il reste Validé.
     */
    public function valide(): static
    {
        return $this->state(fn () => [
            'statut' => StatutBulletin::Valide,
            'valide_at' => now(),
        ]);
    }
}
