<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDomaineEvaluationRequest;
use App\Http\Requests\UpdateDomaineEvaluationRequest;
use App\Models\DomaineEvaluation;
use Illuminate\Http\RedirectResponse;

/**
 * DomaineEvaluation half of the "Niveaux & matières" settings page (onglet
 * maternelle) — voir Academique\NiveauController::index() pour la vue
 * partagée. Même rôle que MatiereController, pour les domaines d'évaluation
 * de maternelle.
 */
class DomaineEvaluationController extends Controller
{
    public function store(StoreDomaineEvaluationRequest $request): RedirectResponse
    {
        DomaineEvaluation::create($request->validated());

        return back()->with('toast', 'Domaine ajouté.');
    }

    public function update(UpdateDomaineEvaluationRequest $request, DomaineEvaluation $domaineEvaluation): RedirectResponse
    {
        $domaineEvaluation->update($request->validated());

        return back()->with('toast', 'Domaine mis à jour.');
    }

    public function destroy(DomaineEvaluation $domaineEvaluation): RedirectResponse
    {
        if ($domaineEvaluation->classes()->exists() || $domaineEvaluation->niveaux()->exists()) {
            return back()->with('toast', "« {$domaineEvaluation->nom} » est déjà utilisé dans une classe ou un programme et ne peut pas être supprimé.");
        }

        $domaineEvaluation->delete();

        return back()->with('toast', 'Domaine supprimé.');
    }
}
