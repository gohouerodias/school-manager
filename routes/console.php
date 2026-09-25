<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Voir App\Console\Commands\NotifierNotesIncompletesCommand : nécessite
// `php artisan schedule:work` (dev) ou une entrée CRON appelant
// `php artisan schedule:run` chaque minute (production) pour s'exécuter
// réellement — voir le guide de déploiement.
Schedule::command('notifications:notes-incompletes')->dailyAt('06:00');

// Voir App\Console\Commands\PurgerDonneesExpireesCommand : supprime
// définitivement les apprenants archivés au-delà de la durée de
// conservation configurée (ParametreSysteme::duree_conservation_donnees).
Schedule::command('donnees:purger-expirees')->dailyAt('03:00');
