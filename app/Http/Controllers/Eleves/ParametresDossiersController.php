<?php

namespace App\Http\Controllers\Eleves;

use App\Http\Controllers\Controller;
use App\Models\ChampPersonnalise;
use App\Models\TypeDocument;
use Illuminate\View\View;

class ParametresDossiersController extends Controller
{
    public function index(): View
    {
        return view('eleves.parametres', [
            'typesDocuments' => TypeDocument::query()->orderBy('libelle')->get(),
            'champsPersonnalises' => ChampPersonnalise::query()->orderBy('ordre')->get(),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Dossier élève et documents' => null,
                'Paramètres des dossiers' => null,
            ],
        ]);
    }
}
