<?php

use App\Http\Controllers\Rapports\RapportController;
use Illuminate\Support\Facades\Route;

// Rapports statistiques : réservé à la Direction (voir App\Enums\ProfilUtilisateur
// et le diagramme de cas d'utilisation — "Générer des rapports statistiques" et
// "Exporter en Excel / PDF" n'y sont associés qu'à cet acteur, pas à
// l'Administrateur ni à l'Agent de scolarité).
Route::middleware(['auth', 'account.active', '2fa', 'password.changed', 'profile:direction'])
    ->prefix('rapports')
    ->name('rapports.')
    ->group(function () {
        Route::get('/', [RapportController::class, 'index'])->name('index');
        Route::get('export/pdf', [RapportController::class, 'exporterPdf'])->name('export.pdf');
        Route::get('export/excel', [RapportController::class, 'exporterExcel'])->name('export.excel');
    });
