<?php

use App\Http\Controllers\Administration\JournalActionController;
use Illuminate\Support\Facades\Route;

// Fonctionnalités transverses réservées à l'administrateur, ne relevant
// d'aucun domaine métier précis (éleves/comptes/académique) — voir la
// carte "Sécurité et Administration" du menu (components/sidebar-nav.blade.php).
Route::middleware(['auth', 'account.active', '2fa', 'password.changed', 'profile:administrateur'])
    ->prefix('administration')
    ->name('administration.')
    ->group(function () {
        Route::get('journal', [JournalActionController::class, 'index'])->name('journal.index');
    });
