<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateParametreAcademiqueRequest;
use App\Models\ParametreSysteme;
use Illuminate\Http\RedirectResponse;

/**
 * The single "Paramètres académiques" settings row (see ParametreSysteme) —
 * only `seuil_passage` is editable from here for now: the moyenne annuelle
 * threshold used by Inscription::determinerPassage() to propose "Admis" vs
 * "Redouble" (see Academique\DecisionPassageController).
 */
class ParametreAcademiqueController extends Controller
{
    public function update(UpdateParametreAcademiqueRequest $request): RedirectResponse
    {
        $parametre = ParametreSysteme::query()->firstOrFail();
        $parametre->update(['seuil_passage' => $request->validated('seuil_passage')]);

        return back()->with('toast', 'Seuil de passage mis à jour.');
    }
}
