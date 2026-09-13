<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNiveauDomaineRequest;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Niveau;
use App\Models\NiveauDomaine;
use Illuminate\Http\RedirectResponse;

/**
 * Per-année programme de domaines d'évaluation d'un niveau de maternelle —
 * voir NiveauDomaine. Équivalent de NiveauMatiereController, sans
 * coefficient à modifier (donc pas de update()) : ajouter ou retirer un
 * domaine suffit à composer le programme.
 */
class NiveauDomaineController extends Controller
{
    public function store(StoreNiveauDomaineRequest $request, AnneeAcademique $anneeAcademique): RedirectResponse
    {
        $validated = $request->validated();
        $niveau = Niveau::findOrFail($validated['niveau_id']);

        foreach ($validated['domaines'] as $domaineId) {
            NiveauDomaine::create([
                'niveau_id' => $niveau->id,
                'domaine_evaluation_id' => $domaineId,
                'annee_academique_id' => $anneeAcademique->id,
            ]);
        }

        // Une classe n'hérite normalement le programme de son niveau qu'à sa
        // création/modification (voir ClasseController::
        // synchroniserDomainesDepuisNiveau()) — sans ceci, une classe déjà
        // créée avant cet ajout resterait avec un programme de domaines vide
        // (classe_domaine), bloquant par exemple l'affectation d'un
        // enseignant tant qu'on ne rouvre pas la classe pour la
        // ré-enregistrer.
        Classe::query()
            ->where('niveau_id', $niveau->id)
            ->where('annee_academique_id', $anneeAcademique->id)
            ->get()
            ->each(fn (Classe $classe) => $classe->domaines()->syncWithoutDetaching($validated['domaines']));

        $nombre = count($validated['domaines']);
        $message = $nombre > 1
            ? "{$nombre} domaines ajoutés au programme de « {$niveau->libelle} » pour « {$anneeAcademique->libelle} »."
            : "Domaine ajouté au programme de « {$niveau->libelle} » pour « {$anneeAcademique->libelle} ».";

        return back()->with('toast', $message);
    }

    public function destroy(NiveauDomaine $niveauDomaine): RedirectResponse
    {
        $niveauDomaine->delete();

        return back()->with('toast', 'Domaine retiré du programme de ce niveau.');
    }
}
