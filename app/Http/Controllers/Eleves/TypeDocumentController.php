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
     */
    public function update(UpdateTypeDocumentRequest $request, TypeDocument $typeDocument): RedirectResponse
    {
        $validated = $request->validated();
        $validated['obligatoire'] = $request->boolean('obligatoire');

        $typeDocument->update($validated);

        return back()->with('toast', 'Type de document mis à jour.');
    }

    public function destroy(TypeDocument $typeDocument): RedirectResponse
    {
        $typeDocument->delete();

        return back()->with('toast', 'Type de document supprimé.');
    }
}
