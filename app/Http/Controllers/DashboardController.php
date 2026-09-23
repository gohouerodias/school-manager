<?php

namespace App\Http\Controllers;

use App\Enums\ProfilUtilisateur;
use App\Enums\StatutEleve;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        // Teachers have their own dedicated space (see
        // Enseignant\EspaceEnseignantController) — no generic dashboard to
        // show them yet, so send them straight there instead of an empty
        // placeholder page.
        if ($request->user()->profil === ProfilUtilisateur::Enseignant) {
            return redirect()->route('enseignant.classes.index');
        }

        // Direction : accès rapide en lecture seule au dossier élève et aux
        // rapports statistiques — ses seuls cas d'utilisation en dehors de
        // l'authentification (voir le diagramme de cas d'utilisation).
        if ($request->user()->profil === ProfilUtilisateur::Direction) {
            $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();

            return view('dashboard', [
                'anneeActive' => $anneeActive,
                'totalApprenants' => Eleve::query()->where('statut', '!=', StatutEleve::Archive)->count(),
                'totalClasses' => $anneeActive ? Classe::query()->where('annee_academique_id', $anneeActive->id)->count() : 0,
            ]);
        }

        return view('dashboard');
    }
}
