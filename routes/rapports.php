<?php

use App\Http\Controllers\Rapports\RapportController;
use Illuminate\Support\Facades\Route;

// Rapports statistiques : Direction et Administrateur (voir App\Enums\
// ProfilUtilisateur) — "Générer des rapports statistiques" et "Exporter en
// Excel / PDF" restent hors de portée de l'Agent de scolarité.
Route::middleware(['auth', 'account.active', '2fa', 'password.changed', 'profile:direction,administrateur'])
    ->prefix('rapports')
    ->name('rapports.')
    ->group(function () {
        Route::get('/', [RapportController::class, 'index'])->name('index');
        Route::get('export/pdf', [RapportController::class, 'exporterPdf'])->name('export.pdf');
        Route::get('export/excel', [RapportController::class, 'exporterExcel'])->name('export.excel');
    });
