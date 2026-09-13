<?php

namespace App\Http\Controllers\Academique;

use App\Enums\DecisionAnnuelle;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDecisionPassageRequest;
use App\Models\AnneeAcademique;
use App\Models\Inscription;
use App\Models\JournalAction;
use App\Models\ParametreSysteme;
use App\Services\BulletinGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * US D.1-D.3 — pour chaque apprenant d'une année académique : moyenne
 * annuelle (moyenne simple de ses bulletins mensuels Validés, voir
 * Inscription::calculerMoyenneAnnuelle()), proposition automatique "Admis" /
 * "Redouble" selon le seuil configuré (voir ParametreSysteme::$seuil_passage),
 * et la décision retenue par la direction — qui peut valider la proposition
 * telle quelle ou la modifier (motif alors obligatoire, action tracée dans le
 * journal des actions). "Exclu" reste un choix manuel, jamais proposé.
 */
class DecisionPassageController extends Controller
{
    public function index(AnneeAcademique $anneeAcademique): View
    {
        $seuil = ParametreSysteme::query()->value('seuil_passage') ?? 10;

        $inscriptions = Inscription::query()
            ->whereHas('classe', fn ($query) => $query->where('annee_academique_id', $anneeAcademique->id))
            ->with(['eleve', 'classe'])
            ->get()
            ->sortBy(fn (Inscription $i) => $i->classe->nom.$i->eleve->nom)
            ->map(function (Inscription $inscription) use ($seuil) {
                $moyenne = $inscription->calculerMoyenneAnnuelle();

                return [
                    'inscription' => $inscription,
                    'moyenne' => $moyenne,
                    'proposition' => $moyenne !== null
                        ? ($moyenne >= $seuil ? DecisionAnnuelle::Admis : DecisionAnnuelle::Redouble)
                        : null,
                ];
            })
            ->values();

        return view('academique.annees.decisions', [
            'anneeAcademique' => $anneeAcademique,
            'seuil' => $seuil,
            'lignes' => $inscriptions,
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Académique' => null,
                'Années académiques' => route('academique.annees.index'),
                $anneeAcademique->libelle => route('academique.annees.show', $anneeAcademique),
                'Décisions de passage' => null,
            ],
        ]);
    }

    public function update(UpdateDecisionPassageRequest $request, Inscription $inscription): RedirectResponse
    {
        $moyenne = $inscription->calculerMoyenneAnnuelle();
        $decisionPrecedente = $inscription->decision;

        $inscription->update([
            'moyenne_annuelle' => $moyenne,
            'decision' => $request->validated('decision'),
            'motif_decision' => $request->validated('motif'),
        ]);

        JournalAction::create([
            'user_id' => $request->user()->id,
            'action' => 'decision_passage',
            'date_heure' => now(),
            'details' => sprintf(
                'Décision de passage de %s : %s → %s (moyenne annuelle %s)%s',
                $inscription->eleve->nomComplet(),
                $decisionPrecedente?->label() ?? 'aucune',
                $inscription->decision->label(),
                $moyenne !== null ? number_format($moyenne, 2).'/20' : 'non calculable',
                $request->validated('motif') ? ' — motif : '.$request->validated('motif') : ''
            ),
        ]);

        return back()->with('toast', "Décision enregistrée pour {$inscription->eleve->nomComplet()}.");
    }

    /**
     * Recalcule et persiste, pour chaque inscription de cette année académique
     * (hors maternelle — pas de moyenne chiffrée), la moyenne annuelle
     * générale (inscriptions.moyenne_annuelle) et la moyenne annuelle de
     * chacune de ses matières (voir MoyenneAnnuelleMatiere) — jusqu'ici,
     * moyenne_annuelle ne se renseignait qu'un apprenant à la fois, en
     * enregistrant sa décision de passage : rien ne le mettait à jour en
     * masse, d'où des moyennes vides/à zéro tant qu'aucune décision n'avait
     * été prise pour chacun.
     */
    public function recalculer(AnneeAcademique $anneeAcademique, BulletinGenerationService $service): RedirectResponse
    {
        $count = $service->recalculerMoyennesAnnuellesPourAnnee($anneeAcademique);

        return back()->with('toast', "Moyennes annuelles recalculées pour {$count} apprenant(s).");
    }
}
