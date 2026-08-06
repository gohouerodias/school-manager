<?php

namespace App\Http\Controllers\Eleves;

use App\Enums\StatutEleve;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveEleveWizardRequest;
use App\Models\ChampPersonnalise;
use App\Models\DocumentNumerique;
use App\Models\Eleve;
use App\Models\Niveau;
use App\Models\ParentTuteur;
use App\Models\TypeDocument;
use App\Models\ValeurChampPersonnalise;
use App\Support\MultiWordSearch;
use App\Support\TuteurResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Full-page, 4-étapes wizard (classe désirée → infos perso → parents/tuteurs
 * → documents) that fully replaces the old "Nouvel apprenant" / "Modifier la
 * fiche" slide panels. Every step lives on the same page and travels in a
 * single submission (see SaveEleveWizardRequest), always fully validated —
 * there is no "brouillon" (draft) save anymore: "Terminer" is the only way
 * to persist a fiche, whether creating a new one or editing an existing one.
 */
class EleveWizardController extends Controller
{
    public function create(): View
    {
        return view('eleves.wizard', $this->donneesFormulaire(null));
    }

    public function edit(Eleve $eleve): View
    {
        $eleve->load(['parents', 'documents.typeDocument', 'valeursPersonnalisees', 'niveauSouhaite']);

        return view('eleves.wizard', $this->donneesFormulaire($eleve));
    }

    public function store(SaveEleveWizardRequest $request): RedirectResponse
    {
        $eleve = $this->enregistrer($request, null);

        return $this->rediriger($request, $eleve);
    }

    public function update(SaveEleveWizardRequest $request, Eleve $eleve): RedirectResponse
    {
        $eleve = $this->enregistrer($request, $eleve);

        return $this->rediriger($request, $eleve);
    }

    /**
     * Quick "does this parent already exist" lookup used by étape 3 as the
     * agent types a tuteur's nom + prénom — same multi-mot matching as the
     * "Liste des tuteurs" page's own search bar.
     */
    public function rechercheTuteur(Request $request): JsonResponse
    {
        $recherche = trim((string) $request->input('q', ''));

        if ($recherche === '') {
            return response()->json([]);
        }

        $tuteurs = MultiWordSearch::apply(ParentTuteur::query(), $recherche, ['nom', 'prenom', 'telephone'])
            ->limit(5)
            ->get()
            ->map(fn (ParentTuteur $tuteur) => [
                'id' => $tuteur->id,
                'nom_prenom' => "{$tuteur->nom} {$tuteur->prenom}",
                'telephone' => $tuteur->telephone,
                'email' => $tuteur->email,
            ])
            ->values();

        return response()->json($tuteurs);
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesFormulaire(?Eleve $eleve): array
    {
        return [
            'eleve' => $eleve,
            'niveaux' => Niveau::query()->orderBy('ordre')->get(),
            'champsPersonnalises' => ChampPersonnalise::query()->orderBy('ordre')->get(),
            'typesDocuments' => TypeDocument::query()->orderBy('libelle')->get(),
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Dossier élève et documents' => route('eleves.index'),
                $eleve ? 'Modifier la fiche' : 'Nouvelle fiche élève' => null,
            ],
        ];
    }

    private function enregistrer(SaveEleveWizardRequest $request, ?Eleve $eleve): Eleve
    {
        $validated = $request->validated();

        $donneesEleve = [
            'matricule' => $validated['matricule'] ?? null,
            'nom' => $validated['nom'] ?? null,
            'prenom' => $validated['prenom'] ?? null,
            'sexe' => $validated['sexe'] ?? null,
            'date_naissance' => $validated['date_naissance'] ?? null,
            'niveau_souhaite_id' => $validated['niveau_souhaite_id'] ?? null,
        ];

        if ($eleve) {
            // A lingering StatutEleve::Brouillon (from before this save-as-
            // draft path was removed) is promoted to Actif once it's fully
            // completed and saved — but an already-Actif or Archive fiche
            // must never have its statut silently changed by an unrelated
            // edit here; only archiver()/desarchiver() may do that.
            if ($eleve->statut === StatutEleve::Brouillon) {
                $donneesEleve['statut'] = StatutEleve::Actif;
            }
            $eleve->update($donneesEleve);
        } else {
            $donneesEleve['statut'] = StatutEleve::Actif;
            $eleve = Eleve::create($donneesEleve);
        }

        foreach ($validated['champs'] ?? [] as $champId => $valeur) {
            if ($valeur === null || $valeur === '') {
                ValeurChampPersonnalise::where('eleve_id', $eleve->id)->where('champ_personnalise_id', $champId)->delete();

                continue;
            }

            ValeurChampPersonnalise::updateOrCreate(
                ['eleve_id' => $eleve->id, 'champ_personnalise_id' => $champId],
                ['valeur' => $valeur],
            );
        }

        foreach ($validated['tuteurs'] ?? [] as $donneesTuteur) {
            $this->enregistrerTuteur($eleve, $donneesTuteur);
        }

        foreach ($request->file('documents', []) as $typeDocumentId => $fichier) {
            if ($fichier instanceof UploadedFile && $fichier->isValid()) {
                $this->enregistrerDocument($eleve, (int) $typeDocumentId, $fichier, $request->user()->id);
            }
        }

        return $eleve;
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function enregistrerTuteur(Eleve $eleve, array $donnees): void
    {
        $tuteur = TuteurResolver::resolveOrCreate($donnees);

        if ($tuteur) {
            $eleve->parents()->syncWithoutDetaching([$tuteur->id => ['lien_parente' => $donnees['lien_parente'] ?? null]]);
        }
    }

    /**
     * Same replace-in-place behaviour as DocumentController::store(): a
     * re-uploaded file for a type de document already on this fiche replaces
     * the old one instead of accumulating a duplicate row.
     */
    private function enregistrerDocument(Eleve $eleve, int $typeDocumentId, UploadedFile $fichier, int $userId): void
    {
        $chemin = $fichier->store('documents-eleves', 'local');

        $existant = $eleve->documents()->where('type_document_id', $typeDocumentId)->first();

        if ($existant) {
            Storage::disk('local')->delete($existant->chemin_fichier);

            $existant->update([
                'televerse_par' => $userId,
                'chemin_fichier' => $chemin,
                'date_ajout' => now()->toDateString(),
            ]);

            return;
        }

        DocumentNumerique::create([
            'eleve_id' => $eleve->id,
            'type_document_id' => $typeDocumentId,
            'televerse_par' => $userId,
            'chemin_fichier' => $chemin,
            'date_ajout' => now()->toDateString(),
        ]);
    }

    private function rediriger(Request $request, Eleve $eleve): RedirectResponse
    {
        $verbe = $request->isMethod('patch') ? 'mise à jour' : 'enregistrée';

        return redirect()->route('eleves.index')->with('toast', "La fiche de {$eleve->nomComplet()} a été {$verbe}.");
    }
}
