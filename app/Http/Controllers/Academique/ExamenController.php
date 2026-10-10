<?php

namespace App\Http\Controllers\Academique;

use App\Enums\SystemeScolaire;
use App\Enums\TypeEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamenRequest;
use App\Http\Requests\UpdateExamenRequest;
use App\Models\AnneeAcademique;
use App\Models\Examen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestion des examens : Maternelle et Primaire sont implémentés — les
 * choisir crée un unique examen mensuel couvrant toutes les classes/élèves
 * du système choisi (matières selon le programme/bulletin de chaque élève)
 * pour l'année académique active. Le système Secondaire affiche un message
 * "en cours de développement" et ne crée rien (voir
 * SystemeScolaire::estDisponible()).
 */
class ExamenController extends Controller
{
    /**
     * Tous les examens, toutes années académiques confondues (voir leur
     * colonne « Année académique »), filtrables via le paramètre GET
     * facultatif `annee_academique_id` — sinon une année passée resterait
     * introuvable dès qu'une autre année devient active.
     */
    public function index(Request $request): View
    {
        $anneeFilter = (string) $request->input('annee_academique_id', '');

        $query = Examen::query()->with('anneeAcademique')->latest('date_examen');

        if ($anneeFilter !== '') {
            $query->where('annee_academique_id', $anneeFilter);
        }

        return view('academique.examens.index', [
            'examens' => $query->get(),
            'annees' => AnneeAcademique::query()->orderByDesc('date_debut')->get(),
            'anneeFilter' => $anneeFilter,
            'anneeActive' => AnneeAcademique::query()->where('est_active', true)->first(),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Académique' => null,
                'Années académiques' => route('academique.annees.index'),
                'Examens' => null,
            ],
        ]);
    }

    public function store(StoreExamenRequest $request): RedirectResponse
    {
        $systeme = SystemeScolaire::from($request->validated('systeme'));

        if (! $systeme->estDisponible()) {
            return back()->with('toast', 'Le système secondaire est en cours de développement et indisponible pour l’instant.');
        }

        $anneeActive = AnneeAcademique::query()->where('est_active', true)->firstOrFail();

        $examen = Examen::create([
            'annee_academique_id' => $anneeActive->id,
            'systeme' => $systeme,
            'type' => TypeEvaluation::EvaluationMensuelle,
            'date_examen' => $request->validated('date_examen'),
            'date_limite_saisie' => $request->validated('date_limite_saisie'),
        ]);

        $dateLimite = $examen->dateLimiteSaisieLibelle();

        return back()->with('toast', "Examen mensuel du système {$systeme->label()} créé — les enseignants ont jusqu'au {$dateLimite} pour saisir les notes.");
    }

    public function update(UpdateExamenRequest $request, Examen $examen): RedirectResponse
    {
        $examen->update([
            'date_examen' => $request->validated('date_examen'),
            'date_limite_saisie' => $request->validated('date_limite_saisie'),
        ]);

        return back()->with('toast', 'Examen mis à jour.');
    }

    /**
     * Cascades to every Note/CommentaireMatiere/Bulletin already tied to
     * this examen (see the `examens` foreign keys' cascadeOnDelete()) — the
     * view's confirmation dialog warns about this explicitly.
     */
    public function destroy(Examen $examen): RedirectResponse
    {
        $examen->delete();

        return back()->with('toast', 'Examen supprimé.');
    }
}
