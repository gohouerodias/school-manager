<?php

namespace App\Console\Commands;

use App\Enums\ProfilUtilisateur;
use App\Enums\StatutUtilisateur;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crée le tout premier compte (profil Administrateur) d'une instance en
 * production, démarrée avec `ProductionSeeder` (qui ne crée volontairement
 * aucun utilisateur — voir son docblock). Une fois connecté avec ce compte,
 * tous les autres comptes (agent de scolarité, direction, enseignants)
 * s'invitent depuis l'écran "Gestion des comptes"
 * (Comptes\UserAccountController::store) — pas depuis cette commande.
 *
 * Pensé pour être lancé depuis un environnement non interactif (ex. l'onglet
 * Artisan de LaravelToolkit sur Plesk, qui exécute une commande complète
 * sans invite) : tous les paramètres se passent en options, avec des valeurs
 * par défaut raisonnables plutôt que des prompts.
 *
 * Exemple : php artisan admin:creer-principal --email=admin@ecole.bj --password=changez-moi
 */
class CreerAdminPrincipal extends Command
{
    /**
     * @var string
     */
    protected $signature = 'admin:creer-principal
        {--email= : Adresse e-mail du compte administrateur}
        {--name=Administrateur : Nom complet affiché dans l\'application}
        {--telephone= : Numéro de téléphone (optionnel)}
        {--password=password : Mot de passe initial — à changer à la première connexion}';

    /**
     * @var string
     */
    protected $description = "Crée le compte administrateur principal d'une instance fraîchement démarrée (voir ProductionSeeder)";

    public function handle(): int
    {
        $email = (string) $this->option('email');

        $validator = Validator::make(
            [
                'email' => $email,
                'name' => $this->option('name'),
                'telephone' => $this->option('telephone'),
                'password' => $this->option('password'),
            ],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'name' => ['required', 'string', 'max:150'],
                'telephone' => ['nullable', 'string', 'max:30'],
                'password' => ['required', 'string', 'min:8'],
            ],
            [
                'email.required' => "L'option --email est obligatoire, ex. : --email=admin@ecole.bj",
                'email.unique' => "Un compte existe déjà avec l'adresse {$email}.",
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $erreur) {
                $this->components->error($erreur);
            }

            return self::FAILURE;
        }

        $donnees = $validator->validated();

        $administrateursExistants = User::where('profil', ProfilUtilisateur::Administrateur)->count();

        $admin = User::create([
            'name' => $donnees['name'],
            'telephone' => $donnees['telephone'] ?? null,
            'email' => $donnees['email'],
            'password' => $donnees['password'],
            'profil' => ProfilUtilisateur::Administrateur,
            'statut' => StatutUtilisateur::Actif,
            'email_verified_at' => now(),
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->components->info("Compte administrateur créé : {$admin->email}");
        $this->components->warn('Ce mot de passe est temporaire — un changement sera exigé à la première connexion.');

        if ($administrateursExistants > 0) {
            $this->components->warn("Note : {$administrateursExistants} autre(s) compte(s) administrateur existai(en)t déjà.");
        }

        return self::SUCCESS;
    }
}
