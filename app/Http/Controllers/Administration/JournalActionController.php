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
        // filled() plutôt qu'une comparaison à '' : robuste même si le
        // paramètre est absent, null, ou une chaîne d'espaces (un champ de
        // filtre vidé peut arriver dans l'un ou l'autre état selon le
        // navigateur) — évite de transmettre une valeur vide à whereDate(),
        // qui lève "Illegal operator and value combination" si elle reçoit
        // null avec un opérateur autre que =/<>/!=.
        $utilisateurId = $request->filled('user_id') ? $request->input('user_id') : null;
        $action = $request->filled('action') ? trim((string) $request->input('action')) : null;
        $dateDebut = $request->filled('date_debut') ? $request->input('date_debut') : null;
        $dateFin = $request->filled('date_fin') ? $request->input('date_fin') : null;

        $query = JournalAction::query()
            ->with('user')
            ->latest('date_heure');

        if ($utilisateurId) {
            $query->where('user_id', $utilisateurId);
        }

        if ($action) {
            $query->where('action', $action);
        }

        if ($dateDebut) {
            $query->whereDate('date_heure', '>=', $dateDebut);
        }

        if ($dateFin) {
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
