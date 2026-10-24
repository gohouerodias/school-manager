<?php

namespace App\Http\Controllers\Eleves;

use App\Enums\ActionJournal;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportElevesRequest;
use App\Imports\ElevesImport;
use App\Models\JournalAction;
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

        JournalAction::enregistrer(ActionJournal::ImportEleves, sprintf(
            '%s : %d fiche(s) créée(s), %d mise(s) à jour, %d ligne(s) en erreur',
            $request->file('fichier')->getClientOriginalName(),
            $import->crees,
            $import->misAJour,
            count($import->erreurs),
        ));

        return view('eleves.import-rapport', [
            'crees' => $import->crees,
            'misAJour' => $import->misAJour,
            'erreurs' => $import->erreurs,
        ]);
    }
}
