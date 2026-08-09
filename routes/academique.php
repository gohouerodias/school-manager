<?php

use App\Http\Controllers\Academique\AffectationEnseignantController;
use App\Http\Controllers\Academique\AnneeAcademiqueController;
use App\Http\Controllers\Academique\ClasseController;
use App\Http\Controllers\Academique\MatiereController;
use App\Http\Controllers\Academique\NiveauController;
use App\Http\Controllers\Academique\NiveauMatiereController;
use Illuminate\Support\Facades\Route;

// Setting up années académiques (créer une année, configurer son programme
// par niveau, ses classes, ses affectations enseignant, puis la démarrer —
// see App\Services\PromotionAnnuelleService for what "démarrer" does) is an
// administrateur-only capability, same as "Paramètres des dossiers".
Route::middleware(['auth', 'account.active', '2fa', 'password.changed', 'profile:administrateur'])
    ->prefix('academique')
    ->name('academique.')
    ->group(function () {
        Route::get('niveaux-matieres', [NiveauController::class, 'index'])->name('niveaux-matieres.index');
        Route::post('niveaux', [NiveauController::class, 'store'])->name('niveaux.store');
        Route::patch('niveaux/{niveau}', [NiveauController::class, 'update'])->name('niveaux.update');
        Route::delete('niveaux/{niveau}', [NiveauController::class, 'destroy'])->name('niveaux.destroy');

        Route::post('matieres', [MatiereController::class, 'store'])->name('matieres.store');
        Route::patch('matieres/{matiere}', [MatiereController::class, 'update'])->name('matieres.update');
        Route::delete('matieres/{matiere}', [MatiereController::class, 'destroy'])->name('matieres.destroy');

        Route::get('annees', [AnneeAcademiqueController::class, 'index'])->name('annees.index');
        Route::post('annees', [AnneeAcademiqueController::class, 'store'])->name('annees.store');
        Route::get('annees/{anneeAcademique}', [AnneeAcademiqueController::class, 'show'])->name('annees.show');
        Route::post('annees/{anneeAcademique}/demarrer', [AnneeAcademiqueController::class, 'demarrer'])->name('annees.demarrer');

        Route::post('annees/{anneeAcademique}/niveau-matieres', [NiveauMatiereController::class, 'store'])->name('annees.niveau-matieres.store');
        Route::delete('niveau-matieres/{niveauMatiere}', [NiveauMatiereController::class, 'destroy'])->name('niveau-matieres.destroy');

        Route::post('annees/{anneeAcademique}/classes', [ClasseController::class, 'store'])->name('annees.classes.store');
        Route::delete('classes/{classe}', [ClasseController::class, 'destroy'])->name('classes.destroy');

        Route::post('annees/{anneeAcademique}/affectations', [AffectationEnseignantController::class, 'store'])->name('annees.affectations.store');
        Route::delete('affectations/{affectationEnseignant}', [AffectationEnseignantController::class, 'destroy'])->name('affectations.destroy');
    });
