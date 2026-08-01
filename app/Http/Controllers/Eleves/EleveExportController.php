<?php

namespace App\Http\Controllers\Eleves;

use App\Exports\ElevesExport;
use App\Http\Controllers\Controller;
use App\Models\Eleve;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class EleveExportController extends Controller
{
    public function excel(): Response
    {
        return Excel::download(new ElevesExport, 'apprenants.xlsx');
    }

    public function pdf(): Response
    {
        $eleves = Eleve::query()
            ->with(['inscriptions' => fn ($q) => $q->latest('date_inscription')->limit(1)->with('classe')])
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        return Pdf::loadView('eleves.export-pdf', ['eleves' => $eleves])
            ->download('apprenants.pdf');
    }
}
