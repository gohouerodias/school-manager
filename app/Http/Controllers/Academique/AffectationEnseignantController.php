<?php

namespace App\Http\Controllers\Academique;

use App\Enums\CycleNiveau;
use App\Http\Controllers\Controller;
use App\Http\Requests\DesignerTitulaireRequest;
use App\Http\Requests\StoreAffectationEnseignantRequest;
use App\Models\AffectationEnseignant;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Assigns a teacher to a classe for a given année académique — the shape of
 * that assignment depends on the classe's cycle (see class diagram note on
 * AffectationEnseignant):
 *  - Maternelle/Primaire: a classe has exactly one teacher, who teaches
 *    every matière of its programme to its élèves — picking a teacher here
 *    replaces whichever teacher (and matières) were previously affected to
 *    that classe/année, and always marks them professeur principal.
 *  - Collège: unchanged — one teacher per matière, several teachers per
 *    classe, "professeur principal" is a deliberate admin choice.
 */
class AffectationEnseignantController extends Controller
{
    public function store(StoreAffectationEnseignantRequest $request, AnneeAcademique $anneeAcademique): RedirectResponse
    {
        $classe = Classe::with(['niveau', 'matieres'])->findOrFail($request->validated('classe_id'));
        $enseignant = User::findOrFail($request->validated('enseignant_id'));

        if (in_array($classe->niveau->cycle, [CycleNiveau::Maternelle, CycleNiveau::Primaire], true)) {
            AffectationEnseignant::query()
                ->where('classe_id', $classe->id)
                ->where('annee_academique_id', $anneeAcademique->id)
                ->delete();

            foreach ($classe->matieres as $matiere) {
                AffectationEnseignant::create([
                    'enseignant_id' => $enseignant->id,
                    'classe_id' => $classe->id,
                    'matiere_id' => $matiere->id,
                    'annee_academique_id' => $anneeAcademique->id,
                    'est_professeur_principal' => true,
                ]);
            }

            return back()->with('toast', "{$enseignant->name} est maintenant l'enseignant de « {$classe->nom} » pour toutes ses matières.");
        }

        $matiereIds = $request->validated('matiere_ids');
        $estPrincipal = $request->boolean('est_professeur_principal');

        if ($estPrincipal) {
            // Une classe ne peut avoir qu'un seul titulaire à la fois (US
            // A.4) — le désigner ici retire le badge à qui l'avait avant.
            AffectationEnseignant::query()
                ->where('classe_id', $classe->id)
                ->where('annee_academique_id', $anneeAcademique->id)
                ->update(['est_professeur_principal' => false]);
        }

        // US A.3 — un enseignant peut être affecté à plusieurs matières d'une
        // même classe en une seule action.
        foreach ($matiereIds as $matiereId) {
            AffectationEnseignant::create([
                'enseignant_id' => $enseignant->id,
                'classe_id' => $classe->id,
                'matiere_id' => $matiereId,
                'annee_academique_id' => $anneeAcademique->id,
                'est_professeur_principal' => $estPrincipal,
            ]);
        }

        $toast = count($matiereIds) > 1
            ? "{$enseignant->name} affecté à ".count($matiereIds)." matières de « {$classe->nom} »."
            : 'Enseignant affecté.';

        return back()->with('toast', $toast);
    }

    /**
     * Désigne le titulaire d'une classe collège parmi les enseignants déjà
     * affectés à cette classe (US A.4) — indépendant de l'affectation d'une
     * matière : ne crée ni ne modifie aucune matière, ne fait que déplacer le
     * badge "Titulaire" d'un enseignant affecté à un autre.
     */
    public function designerTitulaire(DesignerTitulaireRequest $request, Classe $classe): RedirectResponse
    {
        $enseignantId = $request->validated('enseignant_id');

        AffectationEnseignant::query()
            ->where('classe_id', $classe->id)
            ->where('annee_academique_id', $classe->annee_academique_id)
            ->update(['est_professeur_principal' => false]);

        AffectationEnseignant::query()
            ->where('classe_id', $classe->id)
            ->where('annee_academique_id', $classe->annee_academique_id)
            ->where('enseignant_id', $enseignantId)
            ->update(['est_professeur_principal' => true]);

        $enseignant = User::findOrFail($enseignantId);

        return back()->with('toast', "{$enseignant->name} est maintenant titulaire de « {$classe->nom} ».");
    }

    /**
     * Retire un enseignant d'une classe, avec toutes les matières qu'il y
     * enseigne en une seule action (voir le panneau « Gérer » de la page
     * Affectation des enseignants) — refusé si c'est le titulaire actuel et
     * qu'un autre enseignant reste affecté (désignez d'abord un autre
     * titulaire).
     */
    public function destroyEnseignant(Classe $classe, User $enseignant): RedirectResponse
    {
        $estTitulaire = AffectationEnseignant::query()
            ->where('classe_id', $classe->id)
            ->where('enseignant_id', $enseignant->id)
            ->where('est_professeur_principal', true)
            ->exists();

        $autresEnseignants = AffectationEnseignant::query()
            ->where('classe_id', $classe->id)
            ->where('enseignant_id', '!=', $enseignant->id)
            ->exists();

        if ($estTitulaire && $autresEnseignants) {
            return back()->with('toast', "Impossible de retirer {$enseignant->name} : désignez d'abord un autre titulaire pour « {$classe->nom} ».");
        }

        AffectationEnseignant::query()
            ->where('classe_id', $classe->id)
            ->where('enseignant_id', $enseignant->id)
            ->where('annee_academique_id', $classe->annee_academique_id)
            ->delete();

        return back()->with('toast', "{$enseignant->name} retiré de « {$classe->nom} ».");
    }

    public function destroy(AffectationEnseignant $affectationEnseignant): RedirectResponse
    {
        $classe = $affectationEnseignant->classe()->with('niveau')->first();

        if ($classe && in_array($classe->niveau->cycle, [CycleNiveau::Maternelle, CycleNiveau::Primaire], true)) {
            AffectationEnseignant::query()
                ->where('classe_id', $affectationEnseignant->classe_id)
                ->where('annee_academique_id', $affectationEnseignant->annee_academique_id)
                ->delete();

            return back()->with('toast', "Enseignant retiré de « {$classe->nom} ».");
        }

        $affectationEnseignant->delete();

        return back()->with('toast', 'Affectation retirée.');
    }
}
