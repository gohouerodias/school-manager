<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChampFormationRequest;
use App\Http\Requests\UpdateChampFormationRequest;
use App\Models\ChampFormation;
use Illuminate\Http\RedirectResponse;

/**
 * « Champs de formation » (catégories de matières du bulletin) de la page
 * « Niveaux & matières » — voir Academique\NiveauController::index() pour la
 * vue partagée. Un champ ajouté est placé en dernière position sur le
 * bulletin ; supprimer un champ détache simplement ses matières (elles
 * restent valides, sans catégorie).
 */
class ChampFormationController extends Controller
{
    public function store(StoreChampFormationRequest $request): RedirectResponse
    {
        ChampFormation::create([
            'nom' => $request->validated('nom'),
            'ordre' => (int) (ChampFormation::query()->max('ordre') ?? 0) + 1,
        ]);

        return back()->with('toast', 'Champ de formation ajouté.');
    }

    public function update(UpdateChampFormationRequest $request, ChampFormation $champFormation): RedirectResponse
    {
        $champFormation->update($request->validated());

        return back()->with('toast', 'Champ de formation mis à jour.');
    }

    public function destroy(ChampFormation $champFormation): RedirectResponse
    {
        $champFormation->delete();

        return back()->with('toast', 'Champ de formation supprimé : ses matières ne sont plus classées.');
    }
}
