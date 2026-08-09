<?php

namespace App\Services;

use App\Enums\DecisionAnnuelle;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Niveau;
use Illuminate\Support\Str;

/**
 * Runs the year-end "passage" when an année académique is démarrée (see
 * Academique\AnneeAcademiqueController::demarrer()): every élève with an
 * Inscription in the outgoing année is re-inscribed into the new année, in
 * whichever Classe the rules below resolve to — so the administrateur
 * doesn't have to manually reassign every élève's classe by hand.
 *
 * Rules:
 * - decision = Admis    -> next niveau (Niveau::ordre + 1) in the new année.
 * - decision = Redouble -> same niveau, in the new année (redo the year).
 * - decision = Exclu, or no decision recorded at all -> left untouched; no
 *   Inscription is created for them and they don't appear in the report —
 *   only élèves who *should* have been promoted but couldn't be resolved
 *   automatically are reported, via `non_resolus`.
 *
 * Within the target niveau, the specific Classe is picked by matching the
 * élève's current classe's "section" (the last word of its nom — e.g.
 * "CM1 A" -> "A") against classes of the target niveau in the new année; if
 * none matches, the first classe (by nom) of that niveau/année is used
 * instead. If the target niveau has no classe at all yet in the new année,
 * or (for an Admis élève) there is no next niveau, the élève is left
 * unpromoted and reported so the administrateur can assign them manually
 * (via the existing per-élève "Classe" dropdown).
 *
 * Idempotent: an élève who already has an Inscription in the new année is
 * skipped, so re-running this (e.g. after adding a missing classe and
 * démarrer-ing again) never creates duplicates.
 */
class PromotionAnnuelleService
{
    /**
     * @return array{promus: int, redoublants: int, non_resolus: array<int, array{eleve: Eleve, raison: string}>}
     */
    public function promouvoir(AnneeAcademique $anneeSource, AnneeAcademique $anneeCible): array
    {
        $inscriptions = Inscription::query()
            ->whereHas('classe', fn ($query) => $query->where('annee_academique_id', $anneeSource->id))
            ->with(['eleve', 'classe.niveau'])
            ->get();

        $promus = 0;
        $redoublants = 0;
        $nonResolus = [];

        foreach ($inscriptions as $inscription) {
            $eleve = $inscription->eleve;
            $classeActuelle = $inscription->classe;
            $niveauActuel = $classeActuelle?->niveau;

            if (! $eleve || ! $classeActuelle || ! $niveauActuel) {
                continue;
            }

            if (! in_array($inscription->decision, [DecisionAnnuelle::Admis, DecisionAnnuelle::Redouble], true)) {
                // Exclu, or no decision recorded yet: not this service's call
                // to make — left for the administrateur to handle manually.
                continue;
            }

            if ($this->dejaInscritPour($eleve, $anneeCible)) {
                continue;
            }

            $estAdmis = $inscription->decision === DecisionAnnuelle::Admis;

            if ($estAdmis) {
                $niveauCible = Niveau::query()->where('ordre', $niveauActuel->ordre + 1)->first();

                if (! $niveauCible) {
                    $nonResolus[] = [
                        'eleve' => $eleve,
                        'raison' => "« {$niveauActuel->libelle} » est le dernier niveau : aucun niveau supérieur n'existe pour {$eleve->nomComplet()}.",
                    ];

                    continue;
                }
            } else {
                $niveauCible = $niveauActuel;
            }

            $classeCible = $this->resoudreClasseCible($classeActuelle, $niveauCible, $anneeCible);

            if (! $classeCible) {
                $nonResolus[] = [
                    'eleve' => $eleve,
                    'raison' => "Aucune classe de « {$niveauCible->libelle} » n'existe encore dans la nouvelle année pour {$eleve->nomComplet()}.",
                ];

                continue;
            }

            Inscription::create([
                'eleve_id' => $eleve->id,
                'classe_id' => $classeCible->id,
                'date_inscription' => now()->toDateString(),
            ]);

            if ($estAdmis) {
                $promus++;
            } else {
                $redoublants++;
            }
        }

        return ['promus' => $promus, 'redoublants' => $redoublants, 'non_resolus' => $nonResolus];
    }

    private function dejaInscritPour(Eleve $eleve, AnneeAcademique $anneeCible): bool
    {
        return Inscription::query()
            ->where('eleve_id', $eleve->id)
            ->whereHas('classe', fn ($query) => $query->where('annee_academique_id', $anneeCible->id))
            ->exists();
    }

    /**
     * Same "section" (last word of the nom, e.g. "CM1 A" -> "A") as the
     * élève's current classe, within the target niveau/année; falls back to
     * the first classe (by nom) of that niveau/année if no section matches.
     */
    private function resoudreClasseCible(Classe $classeActuelle, Niveau $niveauCible, AnneeAcademique $anneeCible): ?Classe
    {
        $classesCandidates = Classe::query()
            ->where('niveau_id', $niveauCible->id)
            ->where('annee_academique_id', $anneeCible->id)
            ->orderBy('nom')
            ->get();

        if ($classesCandidates->isEmpty()) {
            return null;
        }

        $section = Str::of($classeActuelle->nom)->afterLast(' ')->toString();

        return $classesCandidates->first(fn (Classe $classe) => Str::of($classe->nom)->afterLast(' ')->toString() === $section)
            ?? $classesCandidates->first();
    }
}
