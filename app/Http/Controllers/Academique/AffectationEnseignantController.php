<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAffectationEnseignantRequest;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use Illuminate\Http\RedirectResponse;

/**
 * Assigns a teacher to a classe + matière for a given année académique. A
 * teacher may hold several of these for the same classe (one per matière) —
 * see the composite unique key on `affectations_enseignant`
 * (enseignant_id, classe_id, matiere_id, annee_academique_id) and the class
 * diagram's note on AffectationEnseignant.
 */
class AffectationEnseignantController extends Controller
{
    public function store(StoreAffectationEnseignantRequest $request, AnneeAcademique $anneeAcademique): RedirectResponse
    {
        $validated = $request->validated();
        $validated['annee_academique_id'] = $anneeAcademique->id;
        $validated['est_professeur_principal'] = $request->boolean('est_professeur_principal');

        AffectationEnseignant::create($validated);

        return back()->with('toast', 'Enseignant affecté.');
    }

    public function destroy(AffectationEnseignant $affectationEnseignant): RedirectResponse
    {
        $affectationEnseignant->delete();

        return back()->with('toast', 'Affectation retirée.');
    }
}
