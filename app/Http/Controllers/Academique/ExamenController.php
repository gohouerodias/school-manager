<?php

namespace App\Http\Controllers\Academique;

use App\Enums\SystemeScolaire;
use App\Enums\TypeEvaluation;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamenRequest;
use App\Models\AnneeAcademique;
use App\Models\Examen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
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
    public function index(): View
    {
        return view('academique.examens.index', [
            'examens' => Examen::query()->with('anneeAcademique')->latest('date_examen')->get(),
            'anneeActive' => AnneeAcademique::query()->where('est_active', true)->first(),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Académique' => null,
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

        $dateLimite = Carbon::parse($examen->date_limite_saisie)->format('d/m/Y');

        return back()->with('toast', "Examen mensuel du système {$systeme->label()} créé — les enseignants ont jusqu'au {$dateLimite} pour saisir les notes.");
    }
}
