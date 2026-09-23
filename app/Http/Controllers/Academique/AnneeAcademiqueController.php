<?php

namespace App\Http\Controllers\Academique;

use App\Enums\ProfilUtilisateur;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnneeAcademiqueRequest;
use App\Http\Requests\UpdateAnneeAcademiqueRequest;
use App\Models\AnneeAcademique;
use App\Models\DomaineEvaluation;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\User;
use App\Services\PromotionAnnuelleService;
use App\Services\RapportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Années académiques: create a new année, set it up (curriculum per niveau,
 * classes, affectations enseignant — see the nested controllers below), then
 * "démarrer" it once ready, which promotes élèves from the outgoing active
 * année (see PromotionAnnuelleService) and makes this one active instead.
 */
class AnneeAcademiqueController extends Controller
{
    public function index(): View
    {
        return view('academique.annees.index', [
            'annees' => AnneeAcademique::query()->orderByDesc('date_debut')->get(),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Académique' => null,
                'Années académiques' => null,
            ],
        ]);
    }

    public function store(StoreAnneeAcademiqueRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['promouvoir_automatiquement'] = $request->boolean('promouvoir_automatiquement');

        $anneeAcademique = AnneeAcademique::create($validated);

        return redirect()->route('academique.annees.show', $anneeAcademique)->with('toast', "Année académique « {$anneeAcademique->libelle} » créée. Configurez son programme, ses classes et ses affectations avant de la démarrer.");
    }

    /**
     * Dates de début/fin et nombre d'évaluations mensuelles — voir
     * UpdateAnneeAcademiqueRequest, qui refuse toute nouvelle fenêtre de
     * dates qui exclurait un examen déjà créé pour cette année.
     */
    public function update(UpdateAnneeAcademiqueRequest $request, AnneeAcademique $anneeAcademique): RedirectResponse
    {
        $anneeAcademique->update($request->validated());

        return back()->with('toast', "Dates de « {$anneeAcademique->libelle} » mises à jour.");
    }

    public function show(AnneeAcademique $anneeAcademique, RapportService $rapportService): View
    {
        $anneeAcademique->load([
            'niveauMatieres.niveau',
            'niveauMatieres.matiere',
            'niveauDomaines.niveau',
            'niveauDomaines.domaineEvaluation',
            'classes.niveau',
            'classes.matieres',
            'classes.domaines',
            'classes.inscriptions.eleve',
            'affectations.enseignant',
            'affectations.classe',
            'affectations.matiere',
        ]);

        return view('academique.annees.show', [
            'anneeAcademique' => $anneeAcademique,
            'anneeActive' => AnneeAcademique::query()->where('est_active', true)->where('id', '!=', $anneeAcademique->id)->first(),
            'examens' => $anneeAcademique->examens()->orderByDesc('date_examen')->get(),
            'statistiquesEvaluations' => $rapportService->statistiquesEvaluations($anneeAcademique),
            'niveaux' => Niveau::query()->orderBy('ordre')->get(),
            'matieres' => Matiere::query()->orderBy('nom')->get(),
            'domaines' => DomaineEvaluation::query()->orderBy('nom')->get(),
            'enseignants' => User::query()->where('profil', ProfilUtilisateur::Enseignant->value)->orderBy('name')->get(),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Académique' => null,
                'Années académiques' => route('academique.annees.index'),
                $anneeAcademique->libelle => null,
            ],
        ]);
    }

    public function demarrer(AnneeAcademique $anneeAcademique, PromotionAnnuelleService $promotionAnnuelleService): RedirectResponse
    {
        if ($anneeAcademique->est_active) {
            return back()->with('toast', "« {$anneeAcademique->libelle} » est déjà l'année active.");
        }

        $anneeSource = AnneeAcademique::query()
            ->where('est_active', true)
            ->where('id', '!=', $anneeAcademique->id)
            ->first();

        $rapport = ($anneeSource && $anneeAcademique->promouvoir_automatiquement)
            ? $promotionAnnuelleService->promouvoir($anneeSource, $anneeAcademique)
            : ['promus' => 0, 'redoublants' => 0, 'non_resolus' => []];

        $anneeAcademique->activer();

        $message = "« {$anneeAcademique->libelle} » est maintenant l'année active.";

        if ($rapport['promus'] || $rapport['redoublants']) {
            $message .= " {$rapport['promus']} élève(s) admis promu(s) au niveau supérieur, {$rapport['redoublants']} redoublant(s) réinscrit(s).";
        }

        if (! empty($rapport['non_resolus'])) {
            $message .= ' '.count($rapport['non_resolus'])." élève(s) n'ont pas pu être affectés automatiquement à une classe (voir le détail ci-dessous) : à corriger manuellement.";
        }

        return redirect()->route('academique.annees.show', $anneeAcademique)
            ->with('toast', $message)
            ->with('nonResolus', $rapport['non_resolus']);
    }
}
