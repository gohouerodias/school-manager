<?php

namespace App\Http\Controllers\Eleves;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateEleveClasseRequest;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class EleveClasseController extends Controller
{
    /**
     * Assigns, changes, or removes an élève's classe for the currently
     * active année académique — triggered by the editable "Classe" dropdown
     * in the élèves list (resources/js/eleve-classe-assign.js), after the
     * user confirms the change in the confirmation modal.
     *
     * Deliberately distinct from `niveau_souhaite_id` (see Eleve::store()):
     * this creates/updates a real Inscription, not the purely informational
     * "classe désirée" field.
     *
     * Only the active année's Inscription is touched — an élève can have
     * historical Inscriptions from past années (visible in "Parcours
     * scolaire"), and those must never be overwritten by a classe change
     * made today.
     */
    public function update(UpdateEleveClasseRequest $request, Eleve $eleve): RedirectResponse
    {
        $classeId = $request->validated('classe_id');

        // Eleve::inscriptionActive() requires this relation chain to already
        // be loaded — it isn't by default on a route-model-bound $eleve.
        $eleve->load('inscriptions.classe.anneeAcademique');
        $inscriptionActuelle = $eleve->inscriptionActive();

        if ($classeId === null) {
            $inscriptionActuelle?->delete();

            return back()->with('toast', "{$eleve->nomComplet()} n'est plus assigné(e) à aucune classe.");
        }

        $classe = Classe::query()->with('niveau')->findOrFail($classeId);

        if (! $classe->anneeAcademique?->est_active) {
            throw ValidationException::withMessages([
                'classe_id' => "Cette classe n'appartient pas à l'année académique active.",
            ]);
        }

        if ($inscriptionActuelle) {
            $inscriptionActuelle->update(['classe_id' => $classe->id]);
        } else {
            Inscription::create([
                'eleve_id' => $eleve->id,
                'classe_id' => $classe->id,
                'date_inscription' => now()->toDateString(),
            ]);
        }

        return back()->with('toast', "{$eleve->nomComplet()} a été affecté(e) à la classe {$classe->niveau->libelle} — {$classe->nom}.");
    }
}
