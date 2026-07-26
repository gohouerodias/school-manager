<?php

namespace Database\Factories;

use App\Enums\ProfilUtilisateur;
use App\Enums\StatutUtilisateur;
use App\Models\User;
use App\Support\BeninData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => BeninData::nomComplet(),
            'telephone' => '+229 '.fake()->numerify('## ## ## ##'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'profil' => fake()->randomElement(ProfilUtilisateur::cases()),
            'statut' => StatutUtilisateur::Actif,
            'deux_fa_actif' => false,
            'secret_2fa' => null,
            'doit_changer_mot_de_passe' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function administrateur(): static
    {
        return $this->state(fn (array $attributes) => ['profil' => ProfilUtilisateur::Administrateur]);
    }

    public function agentScolarite(): static
    {
        return $this->state(fn (array $attributes) => ['profil' => ProfilUtilisateur::AgentScolarite]);
    }

    public function enseignant(): static
    {
        return $this->state(fn (array $attributes) => ['profil' => ProfilUtilisateur::Enseignant]);
    }

    public function direction(): static
    {
        return $this->state(fn (array $attributes) => ['profil' => ProfilUtilisateur::Direction]);
    }
}
