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
use App\Support\EleveFilters;
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
                // Not limited to the latest one: the éditable "Classe"
                // dropdown (see Eleve::inscriptionActive()) needs to find
                // the active-année Inscription specifically, which isn't
                // necessarily the most recent one by date_inscription.
                'inscriptions' => fn ($q) => $q->latest('date_inscription')->with('classe.niveau', 'classe.anneeAcademique'),
                'documents.typeDocument',
                'valeursPersonnalisees',
                'niveauSouhaite',
            ])
            ->orderBy('nom')
            ->orderBy('prenom');

        EleveFilters::apply($query, $request);

        $eleves = $query->paginate(self::PER_PAGE)->withQueryString();

        $obligatoireTypeIds = TypeDocument::query()->where('obligatoire', true)->pluck('id');

        $classes = Classe::query()
            ->whereHas('anneeAcademique', fn ($q) => $q->where('est_active', true))
            ->with('niveau')
            ->get()
            ->sortBy(fn (Classe $classe) => [$classe->niveau->ordre, $classe->nom]);

        // Live search (resources/js/live-search.js): as the user types, the
        // request is re-fired via fetch with this header instead of a full
        // page reload, so only the table + pagination fragment is needed —
        // skips the queries below that the rest of the page doesn't use.
        if ($request->ajax()) {
            return view('eleves.partials.table', [
                'eleves' => $eleves,
                'obligatoireTypeIds' => $obligatoireTypeIds,
                'classes' => $classes,
            ]);
        }

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
            // Not generated here: issued by Educmaster and typed in by staff
            // when they have it — may well be left blank at creation time.
            'matricule' => $validated['matricule'] ?? null,
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

        $message = $eleve->matricule
            ? "La fiche de {$eleve->nomComplet()} a été créée (matricule {$eleve->matricule})."
            : "La fiche de {$eleve->nomComplet()} a été créée.";

        return back()->with('toast', $message);
    }

    public function update(UpdateEleveRequest $request, Eleve $eleve): RedirectResponse
    {
        $validated = $request->validated();

        $eleve->update([
            'matricule' => $validated['matricule'] ?? null,
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
        $inscriptionActuelle = $eleve->inscriptions->sortByDesc('date_inscription')->first();
        $photoIdentite = $eleve->photoIdentite();

        return response()->json([
            'identite' => [
                'nom' => $eleve->nom,
                'prenom' => $eleve->prenom,
                'matricule' => $eleve->matricule,
                'identifiant_virtuel' => $eleve->identifiantVirtuel(),
                'sexe' => $eleve->sexe,
                'date_naissance' => $eleve->date_naissance->format('d/m/Y'),
                // Raw Y-m-d value, alongside the display-formatted one above:
                // needed to prefill the "Modifier" panel's <input type="date">
                // (see eleve-fiche.js's renderFiche()).
                'date_naissance_iso' => $eleve->date_naissance->format('Y-m-d'),
                'date_creation' => $eleve->created_at->format('d/m/Y'),
                'statut' => $eleve->statut->value,
                'classe' => $inscriptionActuelle?->classe
                    ? "{$inscriptionActuelle->classe->niveau->libelle} — {$inscriptionActuelle->classe->nom}"
                    : null,
                'niveau_souhaite' => $eleve->niveauSouhaite?->libelle,
                'niveau_souhaite_id' => $eleve->niveau_souhaite_id,
                'a_une_classe' => $eleve->inscriptions->isNotEmpty(),
                'photo_url' => $photoIdentite
                    ? route('eleves.documents.show', ['eleve' => $eleve, 'document' => $photoIdentite])
                    : null,
                'champs' => $champs->map(fn (ChampPersonnalise $champ) => [
                    'id' => $champ->id,
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
