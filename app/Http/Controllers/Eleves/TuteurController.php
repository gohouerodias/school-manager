<?php

namespace App\Http\Controllers\Eleves;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTuteurRequest;
use App\Http\Requests\UpdateTuteurRequest;
use App\Models\Eleve;
use App\Models\ParentTuteur;
use App\Support\TuteurResolver;
use Illuminate\Http\RedirectResponse;

class TuteurController extends Controller
{
    /**
     * Add a parent/tuteur to an existing fiche élève, from the "Parents /
     * Tuteurs" tab of the fiche modal. The form collects a single "Nom et
     * prénom" field (matching the mockup); we split it the same way the
     * account-edit screen does — last word = nom, everything before = prénom
     * — since `parent_tuteurs` keeps them as separate columns.
     *
     * A parent/tuteur may already be on file (e.g. a sibling's file was
     * created first): either the agent confirmed a match proposed by the
     * "does this parent already exist" quick-search (existing_id, see
     * resources/js/tuteur-quick-search.js), or an existing ParentTuteur
     * matches on nom + prénom + téléphone — either way App\Support\
     * TuteurResolver reuses that record instead of creating a duplicate
     * person. Either way, `syncWithoutDetaching` sets the lien_parente for
     * *this* élève — so resubmitting for a tuteur already linked here simply
     * updates their role rather than failing on the unique pivot pair.
     */
    public function store(StoreTuteurRequest $request, Eleve $eleve): RedirectResponse
    {
        $validated = $request->validated();

        $tuteur = TuteurResolver::resolveOrCreate($validated);
        $estExistant = ! $tuteur->wasRecentlyCreated;

        $eleve->parents()->syncWithoutDetaching([$tuteur->id => ['lien_parente' => $validated['lien_parente']]]);

        $message = $estExistant
            ? "{$validated['nom_prenom']} (déjà enregistré) a été lié(e) comme {$validated['lien_parente']} de la fiche de {$eleve->nomComplet()}."
            : "{$validated['nom_prenom']} a été ajouté(e) comme tuteur de {$eleve->nomComplet()}.";

        return back()->with('toast', $message);
    }

    /**
     * Edit a parent/tuteur's own information (nom, prénom, téléphone,
     * email) from the "Parents / Tuteurs" tab, plus their lien de parenté
     * with *this* élève. Since a ParentTuteur record can be shared across
     * siblings (see store()), editing it here updates the same record
     * everywhere it's linked — correct, since it's the same real person.
     *
     * If the agent confirmed a match from the "does this parent already
     * exist" quick-search (existing_id) pointing at a *different* tuteur, or
     * the edited nom + prénom + téléphone happen to match a different
     * ParentTuteur already on file, that one is linked instead (mirroring
     * store()'s dedup) rather than turning this record into a duplicate of
     * it; the original record is simply detached from this élève.
     */
    public function update(UpdateTuteurRequest $request, Eleve $eleve, ParentTuteur $parentTuteur): RedirectResponse
    {
        abort_unless($eleve->parents()->where('parent_tuteurs.id', $parentTuteur->id)->exists(), 404);

        $validated = $request->validated();

        [$nom, $prenom] = TuteurResolver::splitNomPrenom($validated['nom_prenom']);

        if (! empty($validated['existing_id']) && (int) $validated['existing_id'] !== $parentTuteur->id) {
            $autreExistant = ParentTuteur::find($validated['existing_id']);
        } else {
            $autreExistant = ParentTuteur::query()
                ->where('nom', $nom)
                ->where('prenom', $prenom)
                ->where('telephone', $validated['telephone'])
                ->whereKeyNot($parentTuteur->id)
                ->first();
        }

        if ($autreExistant) {
            $eleve->parents()->detach($parentTuteur->id);
            $eleve->parents()->syncWithoutDetaching([$autreExistant->id => ['lien_parente' => $validated['lien_parente']]]);

            return back()->with('toast', "{$validated['nom_prenom']} correspond à un tuteur déjà enregistré : la fiche de {$eleve->nomComplet()} a été liée à ce dossier existant.");
        }

        $parentTuteur->update([
            'nom' => $nom,
            'prenom' => $prenom,
            'telephone' => $validated['telephone'],
            'email' => $validated['email'] ?? null,
        ]);

        $eleve->parents()->updateExistingPivot($parentTuteur->id, ['lien_parente' => $validated['lien_parente']]);

        return back()->with('toast', "Les informations de {$validated['nom_prenom']} ont été mises à jour.");
    }

    /**
     * Remove a parent/tuteur from this fiche élève only (detaches the
     * `eleve_parent` pivot). The ParentTuteur record itself is kept — they
     * may still be linked to other élèves (siblings).
     */
    public function destroy(Eleve $eleve, ParentTuteur $parentTuteur): RedirectResponse
    {
        $eleve->parents()->detach($parentTuteur->id);

        return back()->with('toast', "{$parentTuteur->nom} {$parentTuteur->prenom} a été retiré(e) de la fiche de {$eleve->nomComplet()}.");
    }
}
