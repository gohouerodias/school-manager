<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateParametreAcademiqueRequest;
use App\Models\ParametreSysteme;
use Illuminate\Http\RedirectResponse;

/**
 * The single "Paramètres académiques" settings row (see ParametreSysteme):
 * `seuil_passage`, the moyenne annuelle threshold used by
 * Inscription::determinerPassage() to propose "Admis" vs "Redouble" (see
 * Academique\DecisionPassageController), and `duree_conservation_donnees`,
 * the number of months an archived apprenant's dossier is kept before
 * App\Console\Commands\PurgerDonneesExpireesCommand deletes it permanently
 * (0 = purge automatique désactivée).
 */
class ParametreAcademiqueController extends Controller
{
    public function update(UpdateParametreAcademiqueRequest $request): RedirectResponse
    {
        $parametre = ParametreSysteme::query()->firstOrFail();
        $parametre->update($request->validated());

        return back()->with('toast', 'Paramètres académiques mis à jour.');
    }
}
