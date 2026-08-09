<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNiveauMatiereRequest;
use App\Models\AnneeAcademique;
use App\Models\Niveau;
use App\Models\NiveauMatiere;
use Illuminate\Http\RedirectResponse;

/**
 * Per-année curriculum: which matières (and at what coefficient) a niveau
 * has for a given année académique — see NiveauMatiere. Classes created for
 * this niveau/année inherit this list into their own `classe_matiere` (see
 * Academique\ClasseController::store()).
 */
class NiveauMatiereController extends Controller
{
    public function store(StoreNiveauMatiereRequest $request, AnneeAcademique $anneeAcademique): RedirectResponse
    {
        $validated = $request->validated();
        $niveau = Niveau::findOrFail($validated['niveau_id']);

        NiveauMatiere::create([
            'niveau_id' => $niveau->id,
            'matiere_id' => $validated['matiere_id'],
            'annee_academique_id' => $anneeAcademique->id,
            'coefficient' => $validated['coefficient'],
        ]);

        return back()->with('toast', "Matière ajoutée au programme de « {$niveau->libelle} » pour « {$anneeAcademique->libelle} ».");
    }

    public function destroy(NiveauMatiere $niveauMatiere): RedirectResponse
    {
        $niveauMatiere->delete();

        return back()->with('toast', 'Matière retirée du programme de ce niveau.');
    }
}
