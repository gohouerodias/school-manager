<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNiveauMatiereRequest;
use App\Http\Requests\UpdateNiveauMatiereRequest;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Niveau;
use App\Models\NiveauMatiere;
use Illuminate\Http\RedirectResponse;

/**
 * Per-année curriculum: which matières (and at what coefficient) a niveau
 * has for a given année académique — see NiveauMatiere. Classes created for
 * this niveau/année inherit this list into their own `classe_matiere` (see
 * Academique\ClasseController::store()/update()) — a classe's matières are
 * always this list, never a hand-picked one at the classe level.
 */
class NiveauMatiereController extends Controller
{
    public function store(StoreNiveauMatiereRequest $request, AnneeAcademique $anneeAcademique): RedirectResponse
    {
        $validated = $request->validated();
        $niveau = Niveau::findOrFail($validated['niveau_id']);

        foreach ($validated['matieres'] as $ligne) {
            NiveauMatiere::create([
                'niveau_id' => $niveau->id,
                'matiere_id' => $ligne['matiere_id'],
                'annee_academique_id' => $anneeAcademique->id,
                'coefficient' => $ligne['coefficient'],
            ]);
        }

        // Les classes déjà créées pour ce niveau/année n'héritent normalement
        // leur programme qu'à leur création/modification (voir
        // ClasseController::synchroniserMatieresDepuisNiveau()) — sans ceci,
        // une matière ajoutée après coup resterait absente de leur
        // classe_matiere tant qu'on ne rouvre pas chaque classe pour la
        // ré-enregistrer (même correctif que NiveauDomaineController::store()
        // pour la maternelle).
        $programme = collect($validated['matieres'])->mapWithKeys(fn (array $l) => [$l['matiere_id'] => ['coefficient' => $l['coefficient']]]);

        Classe::query()
            ->where('niveau_id', $niveau->id)
            ->where('annee_academique_id', $anneeAcademique->id)
            ->get()
            ->each(fn (Classe $classe) => $classe->matieres()->syncWithoutDetaching($programme->all()));

        $nombre = count($validated['matieres']);
        $message = $nombre > 1
            ? "{$nombre} matières ajoutées au programme de « {$niveau->libelle} » pour « {$anneeAcademique->libelle} »."
            : "Matière ajoutée au programme de « {$niveau->libelle} » pour « {$anneeAcademique->libelle} ».";

        return back()->with('toast', $message);
    }

    public function update(UpdateNiveauMatiereRequest $request, NiveauMatiere $niveauMatiere): RedirectResponse
    {
        $validated = $request->validated();
        $niveauMatiere->load(['niveau', 'matiere']);

        $niveauMatiere->update(['coefficient' => $validated['coefficient']]);

        // Classes already created for this niveau/année inherited this
        // matière's coefficient at creation time — keep them in sync rather
        // than leaving them with a now-stale value (see
        // ClasseController::synchroniserMatieresDepuisNiveau()).
        Classe::query()
            ->where('niveau_id', $niveauMatiere->niveau_id)
            ->where('annee_academique_id', $niveauMatiere->annee_academique_id)
            ->get()
            ->each(function (Classe $classe) use ($niveauMatiere, $validated) {
                if ($classe->matieres->contains($niveauMatiere->matiere_id)) {
                    $classe->matieres()->updateExistingPivot($niveauMatiere->matiere_id, ['coefficient' => $validated['coefficient']]);
                }
            });

        return back()->with('toast', "Coefficient de « {$niveauMatiere->matiere->nom} » mis à jour pour « {$niveauMatiere->niveau->libelle} » ({$validated['coefficient']}).");
    }

    public function destroy(NiveauMatiere $niveauMatiere): RedirectResponse
    {
        $niveauMatiere->delete();

        // Retire aussitôt cette matière des classes déjà créées pour ce
        // niveau/année, avec les affectations enseignant qui s'y
        // rattachaient — sans quoi un enseignant resterait affiché comme
        // enseignant/titulaire de la classe pour une matière qui n'est plus
        // dans son programme (voir aussi ClasseController::
        // synchroniserMatieresDepuisNiveau(), qui fait le même nettoyage
        // quand une classe est simplement rouverte/ré-enregistrée).
        Classe::query()
            ->where('niveau_id', $niveauMatiere->niveau_id)
            ->where('annee_academique_id', $niveauMatiere->annee_academique_id)
            ->get()
            ->each(function (Classe $classe) use ($niveauMatiere) {
                $classe->matieres()->detach($niveauMatiere->matiere_id);

                AffectationEnseignant::query()
                    ->where('classe_id', $classe->id)
                    ->where('annee_academique_id', $niveauMatiere->annee_academique_id)
                    ->where('matiere_id', $niveauMatiere->matiere_id)
                    ->delete();
            });

        return back()->with('toast', 'Matière retirée du programme de ce niveau.');
    }
}
