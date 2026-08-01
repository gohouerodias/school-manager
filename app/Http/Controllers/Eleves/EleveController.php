<?php

namespace App\Http\Controllers\Eleves;

use App\Enums\StatutEleve;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEleveRequest;
use App\Http\Requests\UpdateEleveRequest;
use App\Models\ChampPersonnalise;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\TypeDocument;
use App\Models\ValeurChampPersonnalise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EleveController extends Controller
{
    public const PER_PAGE = 50;

    /**
     * List élèves, filtered server-side by search/classe/statut/date de
     * création (all optional query params), fixed page size, always sorted
     * alphabetically — same pattern as the account management screen.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $classeFilter = (string) $request->input('classe', '');
        $statutFilter = (string) $request->input('statut', '');
        $dateFilter = (string) $request->input('date_creation', '');

        $query = Eleve::query()
            ->with([
                'inscriptions' => fn ($q) => $q->latest('date_inscription')->limit(1)->with('classe.niveau'),
                'documents',
                'valeursPersonnalisees',
                'niveauSouhaite',
            ])
            ->orderBy('nom')
            ->orderBy('prenom');

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        if ($classeFilter === 'sans_classe') {
            $query->whereDoesntHave('inscriptions');
        } elseif ($classeFilter !== '') {
            $query->whereHas('inscriptions', fn ($q) => $q->where('classe_id', $classeFilter));
        }

        if ($statutFilter === 'archive') {
            $query->where('statut', StatutEleve::Archive);
        } elseif ($statutFilter === 'actif') {
            $query->where('statut', StatutEleve::Actif);
        }

        if ($dateFilter !== '') {
            $query->whereDate('created_at', $dateFilter);
        }

        $eleves = $query->paginate(self::PER_PAGE)->withQueryString();

        $obligatoireTypeIds = TypeDocument::query()->where('obligatoire', true)->pluck('id');

        $classes = Classe::query()
            ->whereHas('anneeAcademique', fn ($q) => $q->where('est_active', true))
            ->with('niveau')
            ->get()
            ->sortBy(fn (Classe $classe) => [$classe->niveau->ordre, $classe->nom]);

        $champsPersonnalises = ChampPersonnalise::query()->orderBy('ordre')->get();
        $typesDocuments = TypeDocument::query()->orderBy('libelle')->get();
        $niveaux = Niveau::query()->orderBy('ordre')->get();

        return view('eleves.index', [
            'eleves' => $eleves,
            'classes' => $classes,
            'niveaux' => $niveaux,
            'champsPersonnalises' => $champsPersonnalises,
            'typesDocuments' => $typesDocuments,
            'obligatoireTypeIds' => $obligatoireTypeIds,
            'search' => $search,
            'classeFilter' => $classeFilter,
            'statutFilter' => $statutFilter,
            'dateFilter' => $dateFilter,
            'subtitle' => sprintf(
                '%d apprenants · %d actifs, %d archivés',
                Eleve::query()->count(),
                Eleve::query()->where('statut', StatutEleve::Actif)->count(),
                Eleve::query()->where('statut', StatutEleve::Archive)->count(),
            ),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Dossier élève et documents' => null,
                'Liste des apprenants' => null,
            ],
        ]);
    }

    /**
     * Create a new fiche élève: fixed fields (nom/prénom/sexe/date de
     * naissance), values for the configurable champs personnalisés, and an
     * optional "classe désirée" (niveau souhaité). The niveau souhaité is
     * purely informational — it does NOT create an Inscription, so the
     * élève stays "sans classe attribuée" until the censeur assigns a real
     * classe during the répartition for the année académique.
     */
    public function store(StoreEleveRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $eleve = Eleve::create([
            'matricule' => Eleve::genererMatricule(),
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'sexe' => $validated['sexe'],
            'date_naissance' => $validated['date_naissance'],
            'niveau_souhaite_id' => $validated['niveau_souhaite_id'] ?? null,
            'statut' => StatutEleve::Actif,
        ]);

        foreach ($validated['champs'] ?? [] as $champId => $valeur) {
            if ($valeur === null || $valeur === '') {
                continue;
            }

            ValeurChampPersonnalise::create([
                'eleve_id' => $eleve->id,
                'champ_personnalise_id' => $champId,
                'valeur' => $valeur,
            ]);
        }

        return back()->with('toast', "La fiche de {$eleve->nomComplet()} a été créée (matricule {$eleve->matricule}).");
    }

    public function update(UpdateEleveRequest $request, Eleve $eleve): RedirectResponse
    {
        $validated = $request->validated();

        $eleve->update([
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'sexe' => $validated['sexe'],
            'date_naissance' => $validated['date_naissance'],
            'niveau_souhaite_id' => $validated['niveau_souhaite_id'] ?? null,
        ]);

        foreach ($validated['champs'] ?? [] as $champId => $valeur) {
            ValeurChampPersonnalise::updateOrCreate(
                ['eleve_id' => $eleve->id, 'champ_personnalise_id' => $champId],
                ['valeur' => $valeur !== '' ? $valeur : null],
            );
        }

        return back()->with('toast', "La fiche de {$eleve->nomComplet()} a été mise à jour.");
    }

    public function archiver(Eleve $eleve): RedirectResponse
    {
        $eleve->archiver();

        return back()->with('toast', "La fiche de {$eleve->nomComplet()} a été archivée.");
    }

    public function desarchiver(Eleve $eleve): RedirectResponse
    {
        $eleve->desarchiver();

        return back()->with('toast', "La fiche de {$eleve->nomComplet()} a été désarchivée.");
    }

    /**
     * Full fiche apprenant (identité + champs personnalisés, parents,
     * parcours scolaire, documents), fetched by the "Consulter la fiche"
     * modal.
     */
    public function fiche(Eleve $eleve): JsonResponse
    {
        $eleve->load([
            'parents',
            'inscriptions.classe.niveau',
            'inscriptions.classe.anneeAcademique',
            'documents.typeDocument',
            'valeursPersonnalisees',
            'niveauSouhaite',
        ]);

        $champs = ChampPersonnalise::query()->orderBy('ordre')->get();
        $typesDocuments = TypeDocument::query()->orderBy('libelle')->get();

        return response()->json([
            'identite' => [
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'matricule' => $eleve->matricule,
                'sexe' => $eleve->sexe,
                'date_naissance' => $eleve->date_naissance->format('d/m/Y'),
                'statut' => $eleve->statut->value,
                'niveau_souhaite' => $eleve->niveauSouhaite?->libelle,
                'a_une_classe' => $eleve->inscriptions->isNotEmpty(),
                'champs' => $champs->map(fn (ChampPersonnalise $champ) => [
                    'libelle' => $champ->libelle,
                    'valeur' => $eleve->valeursPersonnalisees->firstWhere('champ_personnalise_id', $champ->id)?->valeur,
                ])->values(),
            ],
            'parents' => $eleve->parents->map(fn ($parent) => [
                'id' => $parent->id,
                'nom' => "{$parent->nom} {$parent->prenom}",
                'lien' => $parent->pivot->lien_parente,
                'telephone' => $parent->telephone,
                'email' => $parent->email,
            ])->values(),
            'parcours' => $eleve->inscriptions->sortByDesc('date_inscription')->map(fn (Inscription $inscription) => [
                'annee' => $inscription->classe?->anneeAcademique?->libelle,
                'classe' => $inscription->classe?->nom,
                'moyenne_annuelle' => $inscription->moyenne_annuelle,
                'decision' => $inscription->decision?->value,
            ])->values(),
            'documents' => $typesDocuments->map(function (TypeDocument $type) use ($eleve) {
                $document = $eleve->documents->firstWhere('type_document_id', $type->id);

                return [
                    'id' => $document?->id,
                    'libelle' => $type->libelle,
                    'obligatoire' => $type->obligatoire,
                    'fourni' => $document !== null,
                ];
            })->values(),
        ]);
    }
}
