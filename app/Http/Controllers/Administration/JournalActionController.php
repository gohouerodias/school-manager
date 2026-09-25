<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\JournalAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran de consultation du journal des actions (audit log) — lecture seule
 * pour l'instant : n'ajoute aucun nouvel enregistrement, se contente
 * d'afficher/filtrer ce que d'autres écrans écrivent déjà dans JournalAction
 * (voir par ex. Academique\DecisionPassageController::update()).
 */
class JournalActionController extends Controller
{
    public const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $utilisateurId = $request->input('user_id', '');
        $action = trim((string) $request->input('action', ''));
        $dateDebut = $request->input('date_debut', '');
        $dateFin = $request->input('date_fin', '');

        $query = JournalAction::query()
            ->with('user')
            ->latest('date_heure');

        if ($utilisateurId !== '') {
            $query->where('user_id', $utilisateurId);
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($dateDebut !== '') {
            $query->whereDate('date_heure', '>=', $dateDebut);
        }

        if ($dateFin !== '') {
            $query->whereDate('date_heure', '<=', $dateFin);
        }

        $entrees = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('administration.journal.index', [
            'entrees' => $entrees,
            'utilisateurId' => $utilisateurId,
            'action' => $action,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'utilisateurs' => User::query()->orderBy('name')->get(['id', 'name']),
            'actionsDisponibles' => JournalAction::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
