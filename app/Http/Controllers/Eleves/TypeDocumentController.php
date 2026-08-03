<?php

namespace App\Http\Controllers\Eleves;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTypeDocumentRequest;
use App\Http\Requests\UpdateTypeDocumentRequest;
use App\Models\TypeDocument;
use Illuminate\Http\RedirectResponse;

class TypeDocumentController extends Controller
{
    public function store(StoreTypeDocumentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['obligatoire'] = $request->boolean('obligatoire');

        TypeDocument::create($validated);

        return back()->with('toast', 'Type de document ajouté.');
    }

    /**
     * Also used by the "obligatoire" toggle switch in the list, which
     * resubmits the type's current libelle/formats via hidden fields.
     *
     * Protected types (e.g. "Photo d'identité", tied to the avatar shown on
     * the fiche/list — see Eleve::photoIdentite()) can't be renamed, have
     * their formats changed, or have "obligatoire" toggled from here. This
     * is enforced server-side, not just hidden in the UI — a direct POST
     * can't bypass it either.
     */
    public function update(UpdateTypeDocumentRequest $request, TypeDocument $typeDocument): RedirectResponse
    {
        if ($typeDocument->protege) {
            return back()->with('toast', "« {$typeDocument->libelle} » est un type de document protégé et ne peut pas être modifié.");
        }

        $validated = $request->validated();
        $validated['obligatoire'] = $request->boolean('obligatoire');

        $typeDocument->update($validated);

        return back()->with('toast', 'Type de document mis à jour.');
    }

    public function destroy(TypeDocument $typeDocument): RedirectResponse
    {
        if ($typeDocument->protege) {
            return back()->with('toast', "« {$typeDocument->libelle} » est un type de document protégé et ne peut pas être supprimé.");
        }

        $typeDocument->delete();

        return back()->with('toast', 'Type de document supprimé.');
    }
}
