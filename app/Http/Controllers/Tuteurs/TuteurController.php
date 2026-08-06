<?php

namespace App\Http\Controllers\Tuteurs;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTuteurInfoRequest;
use App\Models\ParentTuteur;
use App\Support\MultiWordSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TuteurController extends Controller
{
    public const PER_PAGE = 50;

    /**
     * Standalone list of every ParentTuteur on file (distinct from the
     * "Parents/Tuteurs" tab of a specific fiche élève, which only shows the
     * tuteurs linked to *that* élève) — filtered server-side by search
     * (nom/prénom/téléphone/email), paginated.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $query = ParentTuteur::query()
            ->withCount('eleves')
            ->orderBy('nom')
            ->orderBy('prenom');

        if ($search !== '') {
            // MultiWordSearch (not a plain single LIKE) so typing "nom
            // prénom" together still matches — see its docblock.
            MultiWordSearch::apply($query, $search, ['nom', 'prenom', 'telephone', 'email']);
        }

        $tuteurs = $query->paginate(self::PER_PAGE)->withQueryString();

        // Live search (resources/js/live-search.js): as the user types, the
        // request is re-fired via fetch with this header instead of a full
        // page reload, so only the table + pagination fragment is needed —
        // same pattern as EleveController::index().
        if ($request->ajax()) {
            return view('tuteurs.partials.table', [
                'tuteurs' => $tuteurs,
            ]);
        }

        return view('tuteurs.index', [
            'tuteurs' => $tuteurs,
            'search' => $search,
            'subtitle' => sprintf('%d tuteurs/parents enregistrés', ParentTuteur::query()->count()),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Dossier élève et documents' => null,
                'Liste des tuteurs' => null,
            ],
        ]);
    }

    /**
     * Edits the tuteur's own shared info (nom, prénom, téléphone, email) —
     * no lien_parente here, since that's a per-élève pivot attribute, not a
     * property of the person. A ParentTuteur can be linked to several
     * élèves (fratrie), so this updates the same record everywhere it's
     * already used — same convention as Eleves\TuteurController::update().
     */
    public function update(UpdateTuteurInfoRequest $request, ParentTuteur $parentTuteur): RedirectResponse
    {
        $validated = $request->validated();

        $mots = preg_split('/\s+/', trim($validated['nom_prenom'])) ?: [];
        $nom = array_pop($mots) ?? $validated['nom_prenom'];
        $prenom = implode(' ', $mots);

        $parentTuteur->update([
            'nom' => $nom,
            'prenom' => $prenom,
            'telephone' => $validated['telephone'],
            'email' => $validated['email'] ?? null,
        ]);

        return back()->with('toast', "Les informations de {$validated['nom_prenom']} ont été mises à jour.");
    }

    /**
     * Élèves linked to this tuteur + the lien de parenté for each — powers
     * the "Voir les enfants" modal (tuteur-list.js).
     */
    public function enfants(ParentTuteur $parentTuteur): JsonResponse
    {
        $parentTuteur->load(['eleves' => fn ($query) => $query->orderBy('nom')->orderBy('prenom')]);

        return response()->json([
            'tuteur' => [
                'nom' => $parentTuteur->nom,
                'prenom' => $parentTuteur->prenom,
                'telephone' => $parentTuteur->telephone,
                'email' => $parentTuteur->email,
            ],
            'enfants' => $parentTuteur->eleves->map(fn ($eleve) => [
                'id' => $eleve->id,
                'nom_complet' => $eleve->nomComplet(),
                'matricule' => $eleve->matricule,
                'lien_parente' => $eleve->pivot->lien_parente,
                'liste_url' => route('eleves.index', ['search' => $eleve->matricule]),
            ])->values(),
        ]);
    }
}
