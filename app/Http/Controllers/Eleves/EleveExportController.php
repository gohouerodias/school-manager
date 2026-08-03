<?php

namespace App\Http\Controllers\Eleves;

use App\Exports\ElevesExport;
use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Support\EleveFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class EleveExportController extends Controller
{
    /**
     * Exports the same search/classe/statut/date de création filters as
     * the on-screen list (see EleveController::index()) — "export" always
     * means "what's currently filtered", not the full roster.
     */
    public function excel(Request $request): Response
    {
        return Excel::download(new ElevesExport($request), 'apprenants.xlsx');
    }

    public function pdf(Request $request): Response
    {
        $query = Eleve::query()
            ->with(['inscriptions' => fn ($q) => $q->latest('date_inscription')->limit(1)->with('classe')])
            ->orderBy('nom')
            ->orderBy('prenom');

        $eleves = EleveFilters::apply($query, $request)->get();

        return Pdf::loadView('eleves.export-pdf', ['eleves' => $eleves])
            ->download('apprenants.pdf');
    }
}
