<?php

namespace App\Http\Controllers\Eleves;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStatutInscriptionRequest;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Http\RedirectResponse;

/**
 * Correction manuelle du statut d'une ligne du parcours scolaire (voir
 * App\Enums\StatutInscription) — "Normal" et "Redoublant" sont fixés
 * automatiquement par PromotionAnnuelleService au démarrage d'une année,
 * mais un transfert entrant/sortant ou un abandon ne peut être déduit
 * automatiquement : c'est ici que la direction l'enregistre, depuis la
 * frise du parcours scolaire de la fiche apprenant (eleve-fiche.js).
 */
class InscriptionStatutController extends Controller
{
    public function update(UpdateStatutInscriptionRequest $request, Eleve $eleve, Inscription $inscription): RedirectResponse
    {
        abort_unless($inscription->eleve_id === $eleve->id, 404);

        $inscription->update($request->validated());

        $annee = $inscription->classe?->anneeAcademique?->libelle ?? 'cette année';

        return back()->with('toast', "Statut de {$eleve->nomComplet()} pour {$annee} mis à jour.");
    }
}
