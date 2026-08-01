<?php

namespace App\Http\Controllers\Eleves;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChampPersonnaliseRequest;
use App\Http\Requests\UpdateChampPersonnaliseRequest;
use App\Models\ChampPersonnalise;
use Illuminate\Http\RedirectResponse;

class ChampPersonnaliseController extends Controller
{
    public function store(StoreChampPersonnaliseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['obligatoire'] = $request->boolean('obligatoire');
        $validated['ordre'] = ChampPersonnalise::query()->max('ordre') + 1;

        ChampPersonnalise::create($validated);

        return back()->with('toast', 'Champ ajouté au formulaire apprenant.');
    }

    /**
     * Also used by the "obligatoire" toggle switch in the list, which
     * resubmits the champ's current libelle/type/options via hidden fields.
     */
    public function update(UpdateChampPersonnaliseRequest $request, ChampPersonnalise $champPersonnalise): RedirectResponse
    {
        $validated = $request->validated();
        $validated['obligatoire'] = $request->boolean('obligatoire');

        $champPersonnalise->update($validated);

        return back()->with('toast', 'Champ mis à jour.');
    }

    public function destroy(ChampPersonnalise $champPersonnalise): RedirectResponse
    {
        $champPersonnalise->delete();

        return back()->with('toast', 'Champ supprimé du formulaire apprenant.');
    }
}
