<?php

namespace App\Services;

use App\Enums\DecisionAnnuelle;
use App\Enums\StatutEleve;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Support\Collection;

/**
 * Computes the aggregate statistics behind the 3 rapports (Direction's
 * "Générer des rapports statistiques" use case — see le diagramme de cas
 * d'utilisation) : Effectifs et Résultats se lisent pour une année
 * académique donnée, Archives ne dépend d'aucune année. Les apprenants
 * archivés (App\Enums\StatutEleve::Archive) sont exclus des effectifs et
 * résultats — ils ne font plus partie de l'effectif "actuel" d'une classe.
 */
class RapportService
{
    /**
     * @return array{
     *     annee_academique: AnneeAcademique,
     *     par_classe: Collection<int, array{niveau: string, classe: string, effectif: int}>,
     *     total: int,
     *     par_sexe: Collection<string, int>,
     *     sans_classe: int,
     * }
     */
    public function effectifs(AnneeAcademique $anneeAcademique): array
    {
        $classes = Classe::query()
            ->where('annee_academique_id', $anneeAcademique->id)
            ->with('niveau')
            ->withCount(['inscriptions as effectif' => fn ($q) => $q->whereHas('eleve', fn ($e) => $e->where('statut', '!=', StatutEleve::Archive))])
            ->get()
            ->sortBy([
                fn ($a, $b) => ($a->niveau->ordre ?? 0) <=> ($b->niveau->ordre ?? 0),
                fn ($a, $b) => $a->nom <=> $b->nom,
            ])
            ->values();

        $parClasse = $classes->map(fn (Classe $classe) => [
            'niveau' => $classe->niveau->libelle,
            'classe' => $classe->nom,
            'effectif' => $classe->effectif,
        ]);

        $eleveIds = Inscription::query()
            ->whereIn('classe_id', $classes->pluck('id'))
            ->whereHas('eleve', fn ($e) => $e->where('statut', '!=', StatutEleve::Archive))
            ->pluck('eleve_id')
            ->unique();

        $parSexe = Eleve::query()
            ->whereIn('id', $eleveIds)
            ->selectRaw('sexe, count(*) as total')
            ->groupBy('sexe')
            ->pluck('total', 'sexe');

        $sansClasse = Eleve::query()
            ->where('statut', '!=', StatutEleve::Archive)
            ->whereDoesntHave('inscriptions', fn ($q) => $q->whereHas('classe', fn ($c) => $c->where('annee_academique_id', $anneeAcademique->id)))
            ->count();

        return [
            'annee_academique' => $anneeAcademique,
            'par_classe' => $parClasse,
            'total' => $parClasse->sum('effectif'),
            'par_sexe' => $parSexe,
            'sans_classe' => $sansClasse,
        ];
    }

    /**
     * @return array{
     *     annee_academique: AnneeAcademique,
     *     par_classe: Collection<int, array{niveau: string, classe: string, effectif: int, moyenne_classe: ?float, taux_admis: ?float, taux_redouble: ?float, taux_exclu: ?float}>,
     * }
     */
    public function resultats(AnneeAcademique $anneeAcademique): array
    {
        $classes = Classe::query()
            ->where('annee_academique_id', $anneeAcademique->id)
            ->with(['niveau', 'inscriptions' => fn ($q) => $q->whereHas('eleve', fn ($e) => $e->where('statut', '!=', StatutEleve::Archive))])
            ->get()
            ->sortBy([
                fn ($a, $b) => ($a->niveau->ordre ?? 0) <=> ($b->niveau->ordre ?? 0),
                fn ($a, $b) => $a->nom <=> $b->nom,
            ])
            ->values();

        $parClasse = $classes->map(function (Classe $classe) {
            $inscriptions = $classe->inscriptions;
            $total = $inscriptions->count();
            $avecMoyenne = $inscriptions->whereNotNull('moyenne_annuelle');

            $tauxDecision = fn (DecisionAnnuelle $decision) => $total > 0
                ? round($inscriptions->where('decision', $decision)->count() / $total * 100, 1)
                : null;

            return [
                'niveau' => $classe->niveau->libelle,
                'classe' => $classe->nom,
                'effectif' => $total,
                'moyenne_classe' => $avecMoyenne->isNotEmpty() ? round((float) $avecMoyenne->avg('moyenne_annuelle'), 2) : null,
                'taux_admis' => $tauxDecision(DecisionAnnuelle::Admis),
                'taux_redouble' => $tauxDecision(DecisionAnnuelle::Redouble),
                'taux_exclu' => $tauxDecision(DecisionAnnuelle::Exclu),
            ];
        });

        return [
            'annee_academique' => $anneeAcademique,
            'par_classe' => $parClasse,
        ];
    }

    /**
     * @return array{lignes: Collection<int, array{nom_complet: string, matricule: ?string, date_archivage: ?string, derniere_classe: string}>}
     */
    public function archives(): array
    {
        $eleves = Eleve::query()
            ->where('statut', StatutEleve::Archive)
            ->with(['inscriptions' => fn ($q) => $q->latest('date_inscription')->limit(1)->with('classe.niveau')])
            ->orderByDesc('date_archivage')
            ->get();

        $lignes = $eleves->map(function (Eleve $eleve) {
            $derniereClasse = $eleve->inscriptions->first()?->classe;

            return [
                'nom_complet' => $eleve->nomComplet(),
                'matricule' => $eleve->matricule,
                'date_archivage' => $eleve->date_archivage?->format('d/m/Y'),
                'derniere_classe' => $derniereClasse ? "{$derniereClasse->niveau->libelle} — {$derniereClasse->nom}" : '—',
            ];
        });

        return ['lignes' => $lignes];
    }
}
