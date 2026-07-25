<?php

namespace Database\Factories;

use App\Models\ParentTuteur;
use App\Support\BeninData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentTuteur>
 */
class ParentTuteurFactory extends Factory
{
    protected $model = ParentTuteur::class;

    public function definition(): array
    {
        $sexe = fake()->randomElement(['M', 'F']);
        $prenom = $sexe === 'F'
            ? fake()->randomElement(BeninData::$prenomsFeminins)
            : fake()->randomElement(BeninData::$prenomsMasculins);
        $nom = fake()->randomElement(BeninData::$noms);

        return [
            'nom' => $nom,
            'prenom' => $prenom,
            'telephone' => '229'.fake()->numerify('#########'),
            'email' => fake()->optional(0.6)->safeEmail(),
            'profession' => fake()->randomElement(['Commerçant(e)', 'Enseignant(e)', 'Fonctionnaire', 'Artisan(e)', 'Cultivateur/trice', 'Chauffeur', 'Couturier/ère', 'Infirmier/ère']),
            'adresse' => fake()->randomElement(BeninData::$villes),
        ];
    }
}
