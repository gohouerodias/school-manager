<?php

namespace App\Console\Commands;

use App\Enums\StatutEleve;
use App\Models\Eleve;
use App\Models\ParametreSysteme;
use Illuminate\Console\Command;

/**
 * Supprime définitivement le dossier des apprenants archivés depuis plus
 * longtemps que la durée de conservation configurée (voir
 * ParametreSysteme::duree_conservation_donnees, éditable depuis
 * Niveaux & matières → Paramètres académiques). Toutes les données liées
 * (inscriptions, notes, bulletins, documents numériques, observations…)
 * sont supprimées en cascade par les contraintes de clé étrangère — voir
 * les migrations des tables correspondantes.
 *
 * Une durée de 0 (ou absente) désactive complètement la purge : rien n'est
 * jamais supprimé automatiquement dans ce cas.
 */
class PurgerDonneesExpireesCommand extends Command
{
    protected $signature = 'donnees:purger-expirees';

    protected $description = 'Supprime définitivement les apprenants archivés au-delà de la durée de conservation configurée';

    public function handle(): int
    {
        $duree = ParametreSysteme::query()->value('duree_conservation_donnees');

        if (empty($duree)) {
            $this->components->info('Durée de conservation des données non configurée (ou à 0) — purge automatique désactivée.');

            return self::SUCCESS;
        }

        $dateLimite = now()->subMonths($duree)->toDateString();

        $eleves = Eleve::query()
            ->where('statut', StatutEleve::Archive)
            ->whereNotNull('date_archivage')
            ->whereDate('date_archivage', '<=', $dateLimite)
            ->get();

        if ($eleves->isEmpty()) {
            $this->components->info("Aucun dossier archivé depuis plus de {$duree} mois (avant le {$dateLimite}) — rien à purger.");

            return self::SUCCESS;
        }

        foreach ($eleves as $eleve) {
            $this->components->info("Purge définitive du dossier de {$eleve->nomComplet()} (matricule {$eleve->matricule}, archivé le {$eleve->date_archivage->toDateString()}).");
            $eleve->delete();
        }

        $this->components->info("{$eleves->count()} dossier(s) archivé(s) supprimé(s) définitivement (durée de conservation : {$duree} mois).");

        return self::SUCCESS;
    }
}
