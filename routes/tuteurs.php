<?php

use App\Http\Controllers\Tuteurs\TuteurController;
use Illuminate\Support\Facades\Route;

// Standalone "Liste des tuteurs" page — distinct from routes/eleves.php's
// eleves.tuteurs.* routes, which manage a tuteur's link to one specific
// fiche élève (add/edit/detach) rather than the tuteur record itself.
Route::middleware(['auth', 'account.active', '2fa', 'password.changed', 'profile:administrateur,agent_scolarite'])
    ->prefix('tuteurs')
    ->name('tuteurs.')
    ->group(function () {
        Route::get('/', [TuteurController::class, 'index'])->name('index');
        Route::patch('{parentTuteur}', [TuteurController::class, 'update'])->name('update');
        Route::get('{parentTuteur}/enfants', [TuteurController::class, 'enfants'])->name('enfants');
    });
