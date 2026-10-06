<?php

namespace App\Jobs;

use App\Enums\StatutGenerationBulletin;
use App\Models\DemandeGenerationBulletinAnnuel;
use App\Services\BulletinGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Équivalent annuel de GenererBulletinsClasseJob (voir ce job pour le
 * fonctionnement détaillé) : dispatché immédiatement au clic sur « Générer
 * les bulletins annuels de la classe » (voir Eleves\
 * BulletinAnnuelGenerationController::demanderGeneration()), traite toute la
 * classe en arrière-plan sur la file d'attente plutôt que dans la requête
 * HTTP, et suit sa progression sur la DemandeGenerationBulletinAnnuel fournie.
 */
class GenererBulletinsAnnuelsClasseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public DemandeGenerationBulletinAnnuel $demande) {}

    /**
     * Starts the generation: right after the HTTP response by default (no
     * queue worker needed — shared hosting), or through the queue when
     * config('queue.bulletins_en_file_attente') is on.
     */
    public static function lancer(DemandeGenerationBulletinAnnuel $demande): void
    {
        if (config('queue.bulletins_en_file_attente')) {
            self::dispatch($demande);

            return;
        }

        self::dispatchAfterResponse($demande);
    }

    public function handle(BulletinGenerationService $service): void
    {
        // Run after the response (no worker): don't let PHP's default
        // max_execution_time or a closed browser tab cut a long class short.
        @set_time_limit($this->timeout);
        ignore_user_abort(true);

        $this->demande->update(['statut' => StatutGenerationBulletin::EnCours, 'erreur' => null]);

        try {
            $resultat = $service->genererAnnuelsPourClasse($this->demande->classe, $this->demande);

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

    public function failed(?Throwable $exception): void
    {
        $this->demande->update([
            'statut' => StatutGenerationBulletin::Echec,
            'erreur' => $exception?->getMessage() ?? 'La génération a été interrompue de manière inattendue.',
        ]);
    }
}
