<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClasseRequest;
use App\Http\Requests\UpdateClasseRequest;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Niveau;
use Illuminate\Http\RedirectResponse;

/**
 * Classes available for a given année académique. A classe's nom is always
 * "{niveau->libelle} {lettre}" (never freely typed — see StoreClasseRequest)
 * and its matières always mirror its niveau's programme for this année (see
 * NiveauMatiere / Niveau::matieresPour()): never a hand-picked list at the
 * classe level, kept in sync on both creation and every edit.
 */
class ClasseController extends Controller
{
    public function store(StoreClasseRequest $request, AnneeAcademique $anneeAcademique): RedirectResponse
    {
        $validated = $request->validated();
        $niveau = Niveau::findOrFail($validated['niveau_id']);

        $classe = Classe::create([
            'niveau_id' => $niveau->id,
            'annee_academique_id' => $anneeAcademique->id,
            'nom' => "{$niveau->libelle} {$validated['lettre']}",
        ]);

        $this->synchroniserMatieresDepuisNiveau($classe, $anneeAcademique);

        $message = "Classe « {$classe->nom} » créée"
            .($classe->matieres()->count() ? ", avec son programme de matières hérité de « {$niveau->libelle} »." : '.');

        return back()->with('toast', $message);
    }

    /**
     * The niveau itself can't be changed here — only the lettre — so this
     * amounts to renaming the classe and resynchronizing its programme with
     * whatever the niveau's programme currently is (it may have changed
     * since the classe was created — see NiveauMatiereController::update()).
     * To move a classe to a different niveau, delete it and recreate it
     * there instead (blocked once it has inscriptions/affectations anyway).
     */
    public function update(UpdateClasseRequest $request, Classe $classe): RedirectResponse
    {
        $validated = $request->validated();
        $classe->load('niveau', 'anneeAcademique');

        $classe->update(['nom' => "{$classe->niveau->libelle} {$validated['lettre']}"]);

        $this->synchroniserMatieresDepuisNiveau($classe, $classe->anneeAcademique);

        return back()->with('toast', "Classe renommée en « {$classe->nom} », programme de matières resynchronisé depuis « {$classe->niveau->libelle} ».");
    }

    public function destroy(Classe $classe): RedirectResponse
    {
        if ($classe->inscriptions()->exists() || $classe->affectations()->exists()) {
            return back()->with('toast', "« {$classe->nom} » a déjà des inscriptions ou des enseignants affectés et ne peut pas être supprimée.");
        }

        $classe->delete();

        return back()->with('toast', 'Classe supprimée.');
    }

    /**
     * sync() (not detach()+attach()) so classe_matiere pivot rows for
     * matières that stay in the programme keep their id — only their
     * coefficient is refreshed — instead of being deleted and recreated.
     * Matters once Note starts referencing classe_matiere_id: a naive
     * detach-then-attach would silently orphan/cascade-delete existing
     * grades every time a classe is edited.
     */
    private function synchroniserMatieresDepuisNiveau(Classe $classe, AnneeAcademique $anneeAcademique): void
    {
        $classe->load('niveau');

        $programme = $classe->niveau->matieresPour($anneeAcademique)
            ->mapWithKeys(fn ($matiere) => [$matiere->id => ['coefficient' => $matiere->pivot->coefficient]])
            ->all();

        $classe->matieres()->sync($programme);
    }
}
