<?php

namespace App\Http\Controllers\Eleves;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTuteurRequest;
use App\Models\Eleve;
use App\Models\ParentTuteur;
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
     * created first): if an existing ParentTuteur matches on nom + prénom +
     * téléphone, that record is reused instead of creating a duplicate
     * person. Either way, `syncWithoutDetaching` sets the lien_parente for
     * *this* élève — so resubmitting for a tuteur already linked here simply
     * updates their role rather than failing on the unique pivot pair.
     */
    public function store(StoreTuteurRequest $request, Eleve $eleve): RedirectResponse
    {
        $validated = $request->validated();

        $mots = preg_split('/\s+/', trim($validated['nom_prenom'])) ?: [];
        $nom = array_pop($mots) ?? $validated['nom_prenom'];
        $prenom = implode(' ', $mots);

        $tuteur = ParentTuteur::query()
            ->where('nom', $nom)
            ->where('prenom', $prenom)
            ->where('telephone', $validated['telephone'])
            ->first();

        $estExistant = $tuteur !== null;

        if (! $estExistant) {
            $tuteur = ParentTuteur::create([
                'nom' => $nom,
                'prenom' => $prenom,
                'telephone' => $validated['telephone'],
                'email' => $validated['email'] ?? null,
            ]);
        }

        $eleve->parents()->syncWithoutDetaching([$tuteur->id => ['lien_parente' => $validated['lien_parente']]]);

        $message = $estExistant
            ? "{$validated['nom_prenom']} (déjà enregistré) a été lié(e) comme {$validated['lien_parente']} de la fiche de {$eleve->nomComplet()}."
            : "{$validated['nom_prenom']} a été ajouté(e) comme tuteur de {$eleve->nomComplet()}.";

        return back()->with('toast', $message);
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
