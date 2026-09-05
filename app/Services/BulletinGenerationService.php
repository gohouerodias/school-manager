<?php

namespace App\Services;

use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ClasseMatiere;
use App\Models\DemandeGenerationBulletin;
use App\Models\Examen;
use App\Models\Inscription;
use App\Models\Note;
use App\Support\ZipWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Building blocks for the "Bulletins" screen (Écran admin, US Rapports/
 * Dossiers — voir files/bulletin.html) : le suivi des signatures d'une
 * classe pour un examen mensuel donné, et la génération effective des
 * bulletins (moyenne + rang) une fois que la classe est prête. La
 * génération elle-même se lance immédiatement en arrière-plan (voir
 * App\Jobs\GenererBulletinsClasseJob, dispatché sur la file d'attente) —
 * genererPourClasse() met à jour la progression sur la
 * DemandeGenerationBulletin fournie au fil du traitement, afin que l'écran
 * puisse l'afficher en direct (polling, voir
 * Eleves\BulletinGenerationController::statut()).
 */
class BulletinGenerationService
{
    /**
     * @return array{
     *     lignes: Collection<int, array<string, mixed>>,
     *     total: int,
     *     signedCount: int,
     *     moyennesEnAttenteCount: int,
     *     pct: int,
     *     plusForte: ?float,
     *     plusFaible: ?float,
     * }
     */
    public function payloadPourClasse(Classe $classe, Examen $examen): array
    {
        $inscriptions = $classe->inscriptions()->with('eleve')->get()
            ->sortBy(fn (Inscription $i) => $i->eleve->nom.$i->eleve->prenom)
            ->values();

        $bulletins = Bulletin::query()
            ->whereIn('inscription_id', $inscriptions->pluck('id'))
            ->where('examen_id', $examen->id)
            ->get()
            ->keyBy('inscription_id');

        $lignes = $inscriptions->map(function (Inscription $inscription) use ($bulletins, $classe, $examen) {
            $bulletin = $bulletins->get($inscription->id);
            $notesCompletes = $classe->notesCompletesPour($inscription->eleve_id, $examen);

            // La moyenne n'est calculée qu'une fois toutes les notes du
            // programme renseignées (voir Classe::notesCompletesPour()) —
            // pas besoin qu'un Bulletin existe déjà en base : on utilise un
            // Bulletin non sauvegardé le temps du calcul (calculerMoyenne()
            // ne lit que la relation inscription + l'examen), avec la
            // relation déjà en mémoire pour éviter une requête N+1.
            $moyenne = null;
            if ($notesCompletes) {
                $bulletinPourCalcul = ($bulletin ?? new Bulletin(['examen_id' => $examen->id]))
                    ->setRelation('inscription', $inscription);
                $moyenne = $bulletinPourCalcul->calculerMoyenne();
            }

            return [
                'inscription' => $inscription,
                'bulletin' => $bulletin,
                'signed' => $bulletin?->estValide() ?? false,
                'moyenneEnAttente' => ! $notesCompletes,
                'moyenne' => $moyenne,
            ];
        });

        $classement = $lignes
            ->filter(fn (array $ligne) => $ligne['moyenne'] !== null)
            ->sortByDesc('moyenne')
            ->values();

        $rangParInscription = [];
        foreach ($classement as $position => $ligne) {
            $rangParInscription[$ligne['inscription']->id] = $position + 1;
        }

        $lignes = $lignes->map(function (array $ligne) use ($rangParInscription, $classement) {
            $ligne['rang'] = $rangParInscription[$ligne['inscription']->id] ?? null;
            $ligne['totalClasse'] = $classement->count();

            return $ligne;
        });

        $moyennes = $classement->pluck('moyenne');
        $signedCount = $lignes->filter(fn (array $ligne) => $ligne['signed'])->count();
        $total = $lignes->count();
        $moyennesEnAttenteCount = $lignes->filter(fn (array $ligne) => $ligne['moyenneEnAttente'])->count();

        return [
            'lignes' => $lignes,
            'total' => $total,
            'signedCount' => $signedCount,
            'moyennesEnAttenteCount' => $moyennesEnAttenteCount,
            'pct' => $total > 0 ? (int) round($signedCount / $total * 100) : 0,
            'plusForte' => $moyennes->isNotEmpty() ? $moyennes->max() : null,
            'plusFaible' => $moyennes->isNotEmpty() ? $moyennes->min() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function papierPourInscription(Classe $classe, Examen $examen, Inscription $inscription): array
    {
        $payload = $this->payloadPourClasse($classe, $examen);
        $ligne = $payload['lignes']->firstWhere(fn (array $l) => $l['inscription']->is($inscription));

        abort_unless($ligne, 404);

        $matieres = $this->matieresAvecNotes($classe, $examen, $inscription);

        return [
            'classe' => $classe,
            'examen' => $examen,
            'inscription' => $inscription,
            'eleve' => $inscription->eleve,
            'bulletin' => $ligne['bulletin'],
            'moyenne' => $ligne['moyenne'],
            'rang' => $ligne['rang'],
            'totalClasse' => $ligne['totalClasse'],
            'plusForte' => $payload['plusForte'],
            'plusFaible' => $payload['plusFaible'],
            'matieres' => $matieres,
        ];
    }

    /**
     * Génère à la volée le PDF d'un seul bulletin, sans rien figer en base
     * (contrairement à genererPourClasse()) — utilisé pour le téléchargement
     * individuel depuis l'écran d'aperçu (voir
     * Eleves\BulletinGenerationController::telechargerIndividuel()), à
     * n'importe quel moment, même avant la génération groupée de la classe.
     *
     * @return array{chemin: string, nomFichier: string}
     */
    public function pdfIndividuel(Classe $classe, Examen $examen, Inscription $inscription): array
    {
        $fiche = $this->papierPourInscription($classe, $examen, $inscription);

        return [
            'chemin' => $this->rendrePdfFiche($fiche),
            'nomFichier' => Str::slug($fiche['eleve']->nomComplet()).'.pdf',
        ];
    }

    /**
     * Rend le PDF d'une fiche (même gabarit que l'aperçu écran, voir
     * eleves/bulletins/_papier.blade.php) sur un chemin temporaire — appelé
     * aussi bien par pdfIndividuel() que par genererPourClasse() pour chaque
     * entrée du ZIP produit.
     *
     * @param  array<string, mixed>  $fiche
     */
    private function rendrePdfFiche(array $fiche): string
    {
        Storage::makeDirectory('bulletins/tmp');

        $chemin = 'bulletins/tmp/'.Str::random(16).'.pdf';

        Pdf::loadView('eleves.bulletins.pdf', $fiche)->save(Storage::path($chemin));

        return $chemin;
    }

    /**
     * @return Collection<int, array{nom: string, note: ?float}>
     */
    private function matieresAvecNotes(Classe $classe, Examen $examen, Inscription $inscription): Collection
    {
        $classeMatieres = ClasseMatiere::query()
            ->where('classe_id', $classe->id)
            ->with('matiere')
            ->get();

        $notes = Note::query()
            ->whereIn('classe_matiere_id', $classeMatieres->pluck('id'))
            ->where('examen_id', $examen->id)
            ->where('eleve_id', $inscription->eleve_id)
            ->get()
            ->keyBy('classe_matiere_id');

        return $classeMatieres
            ->sortBy(fn (ClasseMatiere $cm) => $cm->matiere->nom)
            ->map(fn (ClasseMatiere $cm) => [
                'nom' => $cm->matiere->nom,
                'note' => $notes->get($cm->id)?->valeur,
            ])
            ->values();
    }

    /**
     * Génère (ou régénère) les bulletins de toute la classe pour cet
     * examen : fige moyenne_generale/rang/date_generation sur chaque
     * apprenant dont la moyenne est prête (voir Classe::notesCompletesPour()
     * — la signature du titulaire n'est plus une condition), en créant le
     * Bulletin s'il n'existait pas encore, produit le PDF individuel de
     * chacun, puis les regroupe dans une unique archive ZIP (un fichier par
     * apprenant, facile à redistribuer un par un) stockée sur le disque
     * `local`. Traite les apprenants un par un et incrémente
     * `$demande->traites` à chaque étape (si fourni) — c'est ce qui permet
     * à l'écran Bulletins d'afficher une progression en temps réel pendant
     * que App\Jobs\GenererBulletinsClasseJob tourne sur la file d'attente,
     * sans jamais bloquer une requête HTTP.
     *
     * N'est jamais appelée si au moins une moyenne de la classe est encore
     * en attente — voir BulletinGenerationController::demanderGeneration(),
     * qui revérifie ce critère avant de dispatcher le job.
     *
     * @return array{count: int, path: ?string}
     */
    public function genererPourClasse(Classe $classe, Examen $examen, ?DemandeGenerationBulletin $demande = null): array
    {
        $payload = $this->payloadPourClasse($classe, $examen);
        $lignesPretes = $payload['lignes']->filter(fn (array $l) => ! $l['moyenneEnAttente'])->values();

        $demande?->update(['total' => $lignesPretes->count(), 'traites' => 0]);

        $entrees = [];

        foreach ($lignesPretes as $ligne) {
            $inscription = $ligne['inscription'];

            $bulletin = Bulletin::query()->updateOrCreate(
                ['inscription_id' => $inscription->id, 'examen_id' => $examen->id],
                [
                    'moyenne_generale' => $ligne['moyenne'],
                    'rang' => $ligne['rang'],
                    'date_generation' => now()->toDateString(),
                ]
            );

            $fiche = [
                'classe' => $classe,
                'examen' => $examen,
                'inscription' => $inscription,
                'eleve' => $inscription->eleve,
                'bulletin' => $bulletin,
                'moyenne' => $ligne['moyenne'],
                'rang' => $ligne['rang'],
                'totalClasse' => $ligne['totalClasse'],
                'plusForte' => $payload['plusForte'],
                'plusFaible' => $payload['plusFaible'],
                'matieres' => $this->matieresAvecNotes($classe, $examen, $inscription),
            ];

            $entrees[] = [
                'nom' => Str::slug($inscription->eleve->nomComplet().'-'.$inscription->eleve->matricule).'.pdf',
                'chemin' => $this->rendrePdfFiche($fiche),
            ];

            $demande?->increment('traites');
        }

        if ($entrees === []) {
            return ['count' => 0, 'path' => null];
        }

        $cheminZip = $this->zipperEntrees($classe, $examen, $entrees);

        return ['count' => count($entrees), 'path' => $cheminZip];
    }

    /**
     * Regroupe les PDF individuels déjà rendus (voir rendrePdfFiche()) dans
     * une archive ZIP, puis supprime les fichiers temporaires — l'archive
     * seule est conservée (DemandeGenerationBulletin::chemin_pdf). Utilise
     * App\Support\ZipWriter (implémentation maison, sans dépendre de la
     * classe ZipArchive/extension `zip`) pour que la génération fonctionne
     * quelle que soit la configuration PHP du serveur.
     *
     * @param  array<int, array{nom: string, chemin: string}>  $entrees
     */
    private function zipperEntrees(Classe $classe, Examen $examen, array $entrees): string
    {
        Storage::makeDirectory('bulletins');

        $cheminZip = 'bulletins/'.$classe->id.'-'.$examen->id.'-'.Str::random(8).'.zip';

        $zip = new ZipWriter;

        foreach ($entrees as $entree) {
            $zip->ajouterFichier($entree['nom'], Storage::get($entree['chemin']));
        }

        $zip->enregistrer(Storage::path($cheminZip));

        foreach ($entrees as $entree) {
            Storage::delete($entree['chemin']);
        }

        return $cheminZip;
    }
}
