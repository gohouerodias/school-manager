<?php

use App\Http\Controllers\Eleves\ChampPersonnaliseController;
use App\Http\Controllers\Eleves\DocumentController;
use App\Http\Controllers\Eleves\EleveClasseController;
use App\Http\Controllers\Eleves\EleveController;
use App\Http\Controllers\Eleves\EleveExportController;
use App\Http\Controllers\Eleves\EleveWizardController;
use App\Http\Controllers\Eleves\ParametresDossiersController;
use App\Http\Controllers\Eleves\TuteurController;
use App\Http\Controllers\Eleves\TypeDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'account.active', '2fa', 'password.changed', 'profile:administrateur,agent_scolarite'])
    ->prefix('eleves')
    ->name('eleves.')
    ->group(function () {
        Route::get('/', [EleveController::class, 'index'])->name('index');
        Route::post('/', [EleveController::class, 'store'])->name('store');
        Route::patch('{eleve}', [EleveController::class, 'update'])->name('update');

        // Fiche élève wizard (création + modification) : voir EleveWizardController.
        Route::get('nouveau', [EleveWizardController::class, 'create'])->name('wizard.create');
        Route::post('nouveau', [EleveWizardController::class, 'store'])->name('wizard.store');
        Route::get('tuteurs/recherche', [EleveWizardController::class, 'rechercheTuteur'])->name('wizard.tuteurs.recherche');
        Route::get('{eleve}/modifier', [EleveWizardController::class, 'edit'])->name('wizard.edit');
        Route::patch('{eleve}/modifier', [EleveWizardController::class, 'update'])->name('wizard.update');

        Route::patch('{eleve}/archiver', [EleveController::class, 'archiver'])->name('archiver');
        Route::patch('{eleve}/classe', [EleveClasseController::class, 'update'])->name('classe.update');
        Route::patch('{eleve}/desarchiver', [EleveController::class, 'desarchiver'])->name('desarchiver');
        Route::get('{eleve}/fiche', [EleveController::class, 'fiche'])->name('fiche');
        Route::post('{eleve}/tuteurs', [TuteurController::class, 'store'])->name('tuteurs.store');
        Route::patch('{eleve}/tuteurs/{parentTuteur}', [TuteurController::class, 'update'])->name('tuteurs.update');
        Route::delete('{eleve}/tuteurs/{parentTuteur}', [TuteurController::class, 'destroy'])->name('tuteurs.destroy');
        Route::post('{eleve}/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('{eleve}/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('{eleve}/documents/{document}/telecharger', [DocumentController::class, 'download'])->name('documents.download');
        Route::delete('{eleve}/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

        Route::get('export/excel', [EleveExportController::class, 'excel'])->name('export.excel');
        Route::get('export/pdf', [EleveExportController::class, 'pdf'])->name('export.pdf');

        // Paramètres des dossiers (types de documents + champs du formulaire
        // apprenant) : réservé aux administrateurs.
        Route::middleware('profile:administrateur')
            ->prefix('parametres')
            ->name('parametres.')
            ->group(function () {
                Route::get('/', [ParametresDossiersController::class, 'index'])->name('index');

                Route::post('types-documents', [TypeDocumentController::class, 'store'])->name('types-documents.store');
                Route::patch('types-documents/{typeDocument}', [TypeDocumentController::class, 'update'])->name('types-documents.update');
                Route::delete('types-documents/{typeDocument}', [TypeDocumentController::class, 'destroy'])->name('types-documents.destroy');

                Route::post('champs', [ChampPersonnaliseController::class, 'store'])->name('champs.store');
                Route::patch('champs/{champPersonnalise}', [ChampPersonnaliseController::class, 'update'])->name('champs.update');
                Route::delete('champs/{champPersonnalise}', [ChampPersonnaliseController::class, 'destroy'])->name('champs.destroy');
            });
    });
