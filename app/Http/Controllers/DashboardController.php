<?php

namespace App\Http\Controllers;

use App\Enums\ProfilUtilisateur;
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

        return view('dashboard');
    }
}
