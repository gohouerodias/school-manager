<?php

namespace App\Http\Controllers\Eleves;

use App\Http\Controllers\Controller;
use App\Models\ChampPersonnalise;
use App\Models\TypeDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ParametresDossiersController extends Controller
{
    public function index(): View
    {
        return view('eleves.parametres', [
            // nb_apprenants : combien d'apprenants ont déjà déposé ce document — un
            // type déjà utilisé ne peut pas être supprimé (voir TypeDocumentController::destroy()).
            'typesDocuments' => TypeDocument::query()
                ->withCount(['documents as nb_apprenants' => fn ($query) => $query->select(DB::raw('count(distinct eleve_id)'))])
                ->orderBy('libelle')
                ->get(),
            'champsPersonnalises' => ChampPersonnalise::query()->orderBy('ordre')->get(),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Dossier élève et documents' => route('eleves.index'),
                'Paramètres des dossiers' => null,
            ],
        ]);
    }
}
