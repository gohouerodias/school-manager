<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClasseRequest;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use Illuminate\Http\RedirectResponse;

/**
 * Classes available for a given année académique. A new classe "inherits"
 * from its niveau: on creation, the niveau's curriculum for this année (see
 * NiveauMatiere / Niveau::matieresPour()) is copied into the classe's own
 * `classe_matiere` rows — still adjustable per classe afterwards.
 */
class ClasseController extends Controller
{
    public function store(StoreClasseRequest $request, AnneeAcademique $anneeAcademique): RedirectResponse
    {
        $validated = $request->validated();

        $classe = Classe::create([
            'niveau_id' => $validated['niveau_id'],
            'annee_academique_id' => $anneeAcademique->id,
            'nom' => $validated['nom'],
        ]);

        $classe->load('niveau');

        foreach ($classe->niveau->matieresPour($anneeAcademique) as $matiere) {
            $classe->matieres()->attach($matiere->id, [
                'coefficient' => $matiere->pivot->coefficient,
            ]);
        }

        return back()->with('toast', "Classe « {$classe->nom} » créée".($classe->matieres()->count() ? ", avec son programme de matières hérité de « {$classe->niveau->libelle} »." : '.'));
    }

    public function destroy(Classe $classe): RedirectResponse
    {
        if ($classe->inscriptions()->exists() || $classe->affectations()->exists()) {
            return back()->with('toast', "« {$classe->nom} » a déjà des inscriptions ou des enseignants affectés et ne peut pas être supprimée.");
        }

        $classe->delete();

        return back()->with('toast', 'Classe supprimée.');
    }
}
