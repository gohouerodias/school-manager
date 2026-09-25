<?php

namespace App\Http\Controllers\Rapports;

use App\Enums\FormatRapport;
use App\Enums\TypeRapport;
use App\Exports\RapportArchivesExport;
use App\Exports\RapportEffectifsExport;
use App\Exports\RapportResultatsExport;
use App\Http\Controllers\Controller;
use App\Models\AnneeAcademique;
use App\Models\Rapport;
use App\Services\RapportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rapports statistiques (Direction) : "Générer des rapports statistiques" et
 * "Exporter en Excel / PDF" (voir le diagramme de cas d'utilisation) —
 * générés à la demande, de façon synchrone (contrairement aux bulletins :
 * chaque rapport est un unique document agrégé, pas des centaines de PDF
 * par apprenant). Chaque export (PDF ou Excel) journalise une ligne Rapport.
 */
class RapportController extends Controller
{
    public function index(Request $request, RapportService $service): View
    {
        $type = TypeRapport::tryFrom((string) $request->query('type'));
        $annees = AnneeAcademique::query()->orderByDesc('date_debut')->get();

        $anneeAcademique = null;
        if ($type?->necessiteAnneeAcademique()) {
            $anneeAcademique = AnneeAcademique::query()->find($request->integer('annee_academique_id'))
                ?? $annees->firstWhere('est_active', true)
                ?? $annees->first();
        }

        $donnees = null;
        if ($type && (! $type->necessiteAnneeAcademique() || $anneeAcademique)) {
            $donnees = match ($type) {
                TypeRapport::Effectifs => $service->effectifs($anneeAcademique),
                TypeRapport::Resultats => $service->resultats($anneeAcademique),
                TypeRapport::Archives => $service->archives(),
            };
        }

        return view('rapports.index', [
            'types' => TypeRapport::cases(),
            'type' => $type,
            'annees' => $annees,
            'anneeAcademique' => $anneeAcademique,
            'donnees' => $donnees,
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Rapports' => null,
            ],
        ]);
    }

    public function exporterPdf(Request $request, RapportService $service): Response
    {
        [$type, $anneeAcademique, $donnees] = $this->donneesValidees($request, $service);

        $this->journaliser($type, FormatRapport::Pdf);

        return Pdf::loadView("rapports.pdf.{$type->value}", ['donnees' => $donnees])
            ->download($this->nomFichier($type, $anneeAcademique, 'pdf'));
    }

    public function exporterExcel(Request $request, RapportService $service): Response
    {
        [$type, $anneeAcademique, $donnees] = $this->donneesValidees($request, $service);

        $this->journaliser($type, FormatRapport::Excel);

        $export = match ($type) {
            TypeRapport::Effectifs => new RapportEffectifsExport($donnees),
            TypeRapport::Resultats => new RapportResultatsExport($donnees),
            TypeRapport::Archives => new RapportArchivesExport($donnees),
        };

        return Excel::download($export, $this->nomFichier($type, $anneeAcademique, 'xlsx'));
    }

    /**
     * @return array{0: TypeRapport, 1: ?AnneeAcademique, 2: array}
     */
    private function donneesValidees(Request $request, RapportService $service): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(TypeRapport::class)],
            'annee_academique_id' => ['required_if:type,effectifs,resultats', 'nullable', 'exists:annees_academiques,id'],
        ]);

        $type = TypeRapport::from($validated['type']);
        $anneeAcademique = $type->necessiteAnneeAcademique()
            ? AnneeAcademique::findOrFail($validated['annee_academique_id'])
            : null;

        $donnees = match ($type) {
            TypeRapport::Effectifs => $service->effectifs($anneeAcademique),
            TypeRapport::Resultats => $service->resultats($anneeAcademique),
            TypeRapport::Archives => $service->archives(),
        };

        return [$type, $anneeAcademique, $donnees];
    }

    private function journaliser(TypeRapport $type, FormatRapport $format): void
    {
        Rapport::create([
            'genere_par' => Auth::id(),
            'type' => $type,
            'format' => $format,
            'date_generation' => now()->toDateString(),
        ]);
    }

    private function nomFichier(TypeRapport $type, ?AnneeAcademique $anneeAcademique, string $extension): string
    {
        $suffixe = $anneeAcademique ? '-'.str_replace([' ', '/'], '-', $anneeAcademique->libelle) : '';

        return "rapport-{$type->value}{$suffixe}.{$extension}";
    }
}
