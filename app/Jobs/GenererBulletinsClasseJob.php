<?php

namespace App\Jobs;

use App\Enums\StatutGenerationBulletin;
use App\Models\DemandeGenerationBulletin;
use App\Services\BulletinGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Génère les bulletins d'une classe en arrière-plan, sur la file d'attente
 * (voir `composer run dev`, qui lance `php artisan queue:listen` à côté du
 * serveur) — dispatché immédiatement au clic sur « Générer les bulletins de
 * la classe » (voir Eleves\BulletinGenerationController::demanderGeneration()),
 * plutôt que d'exécuter la génération (calculs + rendu PDF, potentiellement
 * long pour une classe chargée) dans la requête HTTP elle-même, ce qui
 * bloquerait le serveur web. La progression est suivie sur la
 * DemandeGenerationBulletin (statut, traites/total) et interrogée par
 * l'écran via polling (voir BulletinGenerationController::statut()).
 *
 * `$tries = 1` : un échec (dompdf, disque plein, etc.) est déjà capturé et
 * enregistré dans `erreur` par le bloc catch ci-dessous — laisser Laravel
 * retenter automatiquement ne ferait que répéter la même erreur.
 */
class GenererBulletinsClasseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public DemandeGenerationBulletin $demande) {}

    public function handle(BulletinGenerationService $service): void
    {
        $this->demande->update(['statut' => StatutGenerationBulletin::EnCours, 'erreur' => null]);

        try {
            $resultat = $service->genererPourClasse($this->demande->classe, $this->demande->examen, $this->demande);

            $this->demande->update([
                'statut' => StatutGenerationBulletin::Termine,
                'genere_at' => now(),
                'nb_bulletins_generes' => $resultat['count'],
                'chemin_pdf' => $resultat['path'],
            ]);
        } catch (Throwable $e) {
            report($e);

            $this->demande->update([
                'statut' => StatutGenerationBulletin::Echec,
                'erreur' => "Une erreur est survenue pendant la génération : {$e->getMessage()}",
            ]);
        }
    }

    /**
     * Filet de sécurité si le job échoue sans même atteindre le catch
     * ci-dessus (timeout dépassé, worker tué, mémoire épuisée...) — l'écran
     * ne doit jamais rester bloqué indéfiniment sur "En cours".
     */
    public function failed(?Throwable $exception): void
    {
        $this->demande->update([
            'statut' => StatutGenerationBulletin::Echec,
            'erreur' => $exception?->getMessage() ?? 'La génération a été interrompue de manière inattendue.',
        ]);
    }
}
