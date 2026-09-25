<?php

namespace App\Http\Controllers\Eleves;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportElevesRequest;
use App\Imports\ElevesImport;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import d'apprenants depuis un fichier Excel/CSV — mêmes colonnes que
 * l'export (voir App\Exports\ElevesExport et EleveExportController::excel()).
 * Une fiche existante (même matricule) est mise à jour plutôt que dupliquée
 * — voir App\Imports\ElevesImport pour le détail du traitement.
 */
class EleveImportController extends Controller
{
    public function create(): View
    {
        return view('eleves.import');
    }

    public function store(ImportElevesRequest $request): View
    {
        $import = new ElevesImport;

        Excel::import($import, $request->file('fichier'));

        return view('eleves.import-rapport', [
            'crees' => $import->crees,
            'misAJour' => $import->misAJour,
            'erreurs' => $import->erreurs,
        ]);
    }
}
