<?php

use App\Http\Controllers\Enseignant\EspaceEnseignantController;
use Illuminate\Support\Facades\Route;

// The espace enseignant (files/tableau-bord-enseignant_1.html) — a teacher's
// own classes + monthly note-entry sheet. Fine-grained ownership checks
// (this teacher is actually affected to the classe/matière, only the
// titulaire may edit the monthly bulletin) live in the controller, since
// they need per-row data the route layer doesn't have.
Route::middleware(['auth', 'account.active', '2fa', 'password.changed', 'profile:enseignant'])
    ->prefix('enseignant')
    ->name('enseignant.')
    ->group(function () {
        Route::get('classes', [EspaceEnseignantController::class, 'index'])->name('classes.index');
        Route::get('classes/{classe}', [EspaceEnseignantController::class, 'show'])->name('classes.show');
        Route::patch('classes/{classe}/notes', [EspaceEnseignantController::class, 'saveNote'])->name('classes.notes.update');
        Route::patch('classes/{classe}/notes/batch', [EspaceEnseignantController::class, 'saveNotesBatch'])->name('classes.notes.batch-update');
        Route::patch('classes/{classe}/commentaires-matiere', [EspaceEnseignantController::class, 'saveCommentaireMatiere'])->name('classes.commentaires-matiere.update');
        Route::patch('classes/{classe}/bulletins', [EspaceEnseignantController::class, 'saveBulletin'])->name('classes.bulletins.update');
        Route::patch('classes/{classe}/bulletins/valider', [EspaceEnseignantController::class, 'validerBulletin'])->name('classes.bulletins.valider');
        Route::patch('classes/{classe}/bulletins/devalider', [EspaceEnseignantController::class, 'devaliderBulletin'])->name('classes.bulletins.devalider');
        Route::get('classes/{classe}/export', [EspaceEnseignantController::class, 'exportNotes'])->name('classes.notes.export');
    });
