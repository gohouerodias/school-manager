<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMatiereRequest;
use App\Http\Requests\UpdateMatiereRequest;
use App\Models\Matiere;
use Illuminate\Http\RedirectResponse;

/**
 * Matiere half of the "Niveaux & matières" settings page — see
 * Academique\NiveauController::index() for the shared view.
 */
class MatiereController extends Controller
{
    public function store(StoreMatiereRequest $request): RedirectResponse
    {
        Matiere::create($request->validated());

        return back()->with('toast', 'Matière ajoutée.');
    }

    public function update(UpdateMatiereRequest $request, Matiere $matiere): RedirectResponse
    {
        $matiere->update($request->validated());

        return back()->with('toast', 'Matière mise à jour.');
    }

    public function destroy(Matiere $matiere): RedirectResponse
    {
        if ($matiere->classes()->exists() || $matiere->niveaux()->exists()) {
            return back()->with('toast', "« {$matiere->nom} » est déjà utilisée dans une classe ou un programme et ne peut pas être supprimée.");
        }

        $matiere->delete();

        return back()->with('toast', 'Matière supprimée.');
    }
}
