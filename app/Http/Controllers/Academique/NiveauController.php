<?php

namespace App\Http\Controllers\Academique;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNiveauRequest;
use App\Http\Requests\UpdateNiveauRequest;
use App\Models\Matiere;
use App\Models\Niveau;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * "Niveaux & matières" settings page: the master lists of Niveau (CI, CP...)
 * and Matiere (Français, Maths...) reused every année académique — see
 * Academique\NiveauMatiereController for the per-année curriculum
 * (Niveau + Matiere + coefficient) built from these two lists.
 */
class NiveauController extends Controller
{
    public function index(): View
    {
        return view('academique.niveaux-matieres', [
            'niveaux' => Niveau::query()->orderBy('ordre')->get(),
            'matieres' => Matiere::query()->orderBy('nom')->get(),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Académique' => null,
                'Niveaux & matières' => null,
            ],
        ]);
    }

    public function store(StoreNiveauRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['premiere_scolarisation'] = $request->boolean('premiere_scolarisation');

        Niveau::create($validated);

        return back()->with('toast', 'Niveau ajouté.');
    }

    public function update(UpdateNiveauRequest $request, Niveau $niveau): RedirectResponse
    {
        $validated = $request->validated();
        $validated['premiere_scolarisation'] = $request->boolean('premiere_scolarisation');

        $niveau->update($validated);

        return back()->with('toast', 'Niveau mis à jour.');
    }

    public function destroy(Niveau $niveau): RedirectResponse
    {
        if ($niveau->classes()->exists()) {
            return back()->with('toast', "« {$niveau->libelle} » a déjà des classes rattachées et ne peut pas être supprimé.");
        }

        $niveau->delete();

        return back()->with('toast', 'Niveau supprimé.');
    }
}
