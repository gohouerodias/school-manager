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
 *
 * A niveau's `ordre` (used by PromotionAnnuelleService to find "the niveau
 * supérieur") is never typed by hand: a new niveau is always appended last
 * (store()), and monter()/descendre() swap a niveau's ordre with its
 * immediate neighbour — so the sequence stays a clean permutation instead of
 * risking gaps or duplicate values an admin could introduce by hand.
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
        $validated['ordre'] = (int) (Niveau::query()->max('ordre') ?? 0) + 1;

        Niveau::create($validated);

        return back()->with('toast', "Niveau « {$validated['libelle']} » ajouté en dernière position.");
    }

    public function update(UpdateNiveauRequest $request, Niveau $niveau): RedirectResponse
    {
        $validated = $request->validated();
        $validated['premiere_scolarisation'] = $request->boolean('premiere_scolarisation');

        $niveau->update($validated);

        return back()->with('toast', 'Niveau mis à jour.');
    }

    public function monter(Niveau $niveau): RedirectResponse
    {
        $precedent = Niveau::query()->where('ordre', '<', $niveau->ordre)->orderByDesc('ordre')->first();

        if ($precedent) {
            $this->permuterOrdre($niveau, $precedent);
        }

        return back()->with('toast', "« {$niveau->libelle} » déplacé.");
    }

    public function descendre(Niveau $niveau): RedirectResponse
    {
        $suivant = Niveau::query()->where('ordre', '>', $niveau->ordre)->orderBy('ordre')->first();

        if ($suivant) {
            $this->permuterOrdre($niveau, $suivant);
        }

        return back()->with('toast', "« {$niveau->libelle} » déplacé.");
    }

    public function destroy(Niveau $niveau): RedirectResponse
    {
        if ($niveau->classes()->exists()) {
            return back()->with('toast', "« {$niveau->libelle} » a déjà des classes rattachées et ne peut pas être supprimé.");
        }

        $niveau->delete();

        return back()->with('toast', 'Niveau supprimé.');
    }

    private function permuterOrdre(Niveau $a, Niveau $b): void
    {
        [$ordreA, $ordreB] = [$a->ordre, $b->ordre];

        $a->update(['ordre' => $ordreB]);
        $b->update(['ordre' => $ordreA]);
    }
}
