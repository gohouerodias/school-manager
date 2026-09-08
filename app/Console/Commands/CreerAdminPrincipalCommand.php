<?php

namespace App\Console\Commands;

use App\Enums\ProfilUtilisateur;
use App\Enums\StatutUtilisateur;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Crée le tout premier compte (administrateur) d'une instance en
 * production — celui qui sert ensuite, une fois connecté, à inviter tous
 * les autres comptes (agent de scolarité, direction, enseignants) via
 * l'écran "Gestion des comptes" (voir UserAccountController::store()).
 *
 * Contrairement à ce flux d'invitation normal (qui envoie un lien de
 * réinitialisation par e-mail et ne révèle jamais le mot de passe), cette
 * commande affiche directement un mot de passe temporaire dans la sortie
 * console : le tout premier compte ne peut pas dépendre de l'envoi d'un
 * e-mail, puisque la messagerie (MAIL_MAILER) n'est pas forcément déjà
 * configurée à ce stade du déploiement. `doit_changer_mot_de_passe` force
 * son changement dès la première connexion (voir EnsurePasswordIsChanged).
 *
 * Usage : php artisan admin:creer-principal "Nom Complet" email@ecole.bj "+229 00 00 00 00"
 */
class CreerAdminPrincipalCommand extends Command
{
    protected $signature = 'admin:creer-principal {name : Nom complet} {email : Adresse e-mail (identifiant de connexion)} {telephone : Numéro de téléphone}';

    protected $description = "Crée le compte administrateur principal, à utiliser une seule fois pour démarrer l'instance en production";

    public function handle(): int
    {
        $data = [
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'telephone' => $this->argument('telephone'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        if (User::query()->where('profil', ProfilUtilisateur::Administrateur)->exists()) {
            $this->components->warn('Un compte administrateur existe déjà. Utilisez plutôt "Gestion des comptes" une fois connecté pour en inviter un autre.');

            return self::FAILURE;
        }

        $motDePasseTemporaire = Str::password(16);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'password' => $motDePasseTemporaire,
            'profil' => ProfilUtilisateur::Administrateur,
            'statut' => StatutUtilisateur::Actif,
            'doit_changer_mot_de_passe' => true,
        ]);

        $this->components->info('Compte administrateur principal créé.');
        $this->line('');
        $this->line("  Email : {$data['email']}");
        $this->line("  Mot de passe temporaire : {$motDePasseTemporaire}");
        $this->line('');
        $this->components->warn('Notez ce mot de passe maintenant — il ne sera plus affiché. Il devra être changé dès la première connexion.');

        return self::SUCCESS;
    }
}
