<?php

namespace App\Http\Controllers\Eleves;

use App\Enums\StatutGenerationBulletin;
use App\Http\Controllers\Controller;
use App\Http\Requests\DemanderGenerationBulletinRequest;
use App\Jobs\GenererBulletinsClasseJob;
use App\Models\AnneeAcademique;
use App\Models\Classe;
use App\Models\DemandeGenerationBulletin;
use App\Models\Examen;
use App\Models\Inscription;
use App\Services\BulletinGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Écran "Bulletins" (voir files/bulletin.html) : suivi des signatures des
 * bulletins mensuels d'une classe pour un examen donné, et bouton pour en
 * lancer la génération. Cliquer sur « Générer les bulletins de la classe »
 * démarre immédiatement App\Jobs\GenererBulletinsClasseJob sur la file
 * d'attente (voir `composer run dev`, qui lance un worker) plutôt que de
 * générer les PDF dans la requête HTTP — l'écran affiche la progression en
 * temps réel via statut(), interrogé par polling.
 */
class BulletinGenerationController extends Controller
{
    public function __construct(private readonly BulletinGenerationService $service) {}

    public function index(Request $request): View
    {
        $anneeActive = AnneeAcademique::query()->where('est_active', true)->first();

        $classes = $anneeActive
            ? Classe::query()->where('annee_academique_id', $anneeActive->id)->with('niveau')->orderBy('nom')->get()
            : collect();

        $classeId = (int) $request->query('classe_id', (string) $classes->first()?->id);
        $classe = $classes->firstWhere('id', $classeId) ?? $classes->first();

        $examens = collect();
        $examenActif = null;
        $payload = null;
        $demande = null;

        if ($classe) {
            $examens = Examen::pourClasse($classe);
            $examenId = (int) $request->query('examen_id', (string) $examens->first()?->id);
            $examenActif = $examens->firstWhere('id', $examenId) ?? $examens->first();

            if ($examenActif) {
                $payload = $this->service->payloadPourClasse($classe, $examenActif);
                $demande = DemandeGenerationBulletin::query()
                    ->where('classe_id', $classe->id)
                    ->where('examen_id', $examenActif->id)
                    ->first();
            }
        }

        return view('eleves.bulletins.index', [
            'classes' => $classes,
            'classe' => $classe,
            'examens' => $examens,
            'examenActif' => $examenActif,
            'payload' => $payload,
            'demande' => $demande,
            'breadcrumbs' => [
                'Tableau de bord' => route('dashboard'),
                'Dossier élève' => route('eleves.index'),
                'Bulletins' => null,
            ],
        ]);
    }

    /**
     * Lance immédiatement la génération (job en file d'attente — voir
     * App\Jobs\GenererBulletinsClasseJob) : jamais dans cette requête, pour
     * ne jamais bloquer le serveur le temps de calculer les moyennes et de
     * produire le PDF d'une classe entière. Le serveur revérifie
     * systématiquement qu'aucune moyenne n'est encore en attente (voir
     * Classe::notesCompletesPour()) — la signature du titulaire n'est plus
     * une condition, ne jamais faire confiance uniquement au bouton
     * désactivé côté navigateur.
     *
     * Rejouable à volonté une fois qu'une génération précédente est Termine
     * ou en Echec (seule une génération réellement EnCours, ou EnAttente
     * depuis moins de 2 minutes, bloque une nouvelle demande — voir
     * DemandeGenerationBulletin::bloqueUneNouvelleGeneration() : au-delà,
     * on considère qu'aucun worker de file d'attente ne tourne et on laisse
     * l'écran se débloquer tout seul plutôt que de rester bloqué
     * indéfiniment) — utile après une correction de notes. L'ancienne
     * archive ZIP est supprimée avant de relancer, pour ne jamais laisser
     * de fichier orphelin sur le disque.
     */
    public function demanderGeneration(DemanderGenerationBulletinRequest $request): RedirectResponse
    {
        $classe = Classe::query()->findOrFail($request->validated('classe_id'));
        $examen = Examen::query()->findOrFail($request->validated('examen_id'));

        $redirectBack = redirect()->route('eleves.bulletins.index', ['classe_id' => $classe->id, 'examen_id' => $examen->id]);

        if ($examen->annee_academique_id !== $classe->annee_academique_id) {
            abort(404);
        }

        $demandeExistante = DemandeGenerationBulletin::query()
            ->where('classe_id', $classe->id)
            ->where('examen_id', $examen->id)
            ->first();

        if ($demandeExistante?->bloqueUneNouvelleGeneration()) {
            return $redirectBack->with('toast', "La génération des bulletins de {$classe->nom} est déjà en cours.");
        }

        $payload = $this->service->payloadPourClasse($classe, $examen);

        if ($payload['total'] === 0 || $payload['moyennesEnAttenteCount'] > 0) {
            return $redirectBack->with('toast', "Impossible : la moyenne d'au moins un apprenant de la classe {$classe->nom} est encore en attente (notes incomplètes).");
        }

        $regeneration = $demandeExistante?->estGeneree() ?? false;

        if ($demandeExistante?->chemin_pdf && Storage::exists($demandeExistante->chemin_pdf)) {
            Storage::delete($demandeExistante->chemin_pdf);
        }

        $demande = DemandeGenerationBulletin::query()->updateOrCreate(
            ['classe_id' => $classe->id, 'examen_id' => $examen->id],
            [
                'demande_par_id' => $request->user()->id,
                'demande_at' => now(),
                'statut' => StatutGenerationBulletin::EnAttente,
                'genere_at' => null,
                'nb_bulletins_generes' => null,
                'total' => 0,
                'traites' => 0,
                'chemin_pdf' => null,
                'erreur' => null,
            ]
        );

        GenererBulletinsClasseJob::dispatch($demande);

        $toast = $regeneration
            ? "Régénération des bulletins de {$classe->nom} démarrée — suivez la progression ci-dessous."
            : "Génération des bulletins de {$classe->nom} démarrée — suivez la progression ci-dessous.";

        return $redirectBack->with('toast', $toast);
    }

    /**
     * Interrogé par polling depuis l'écran Bulletins (voir
     * resources/js/bulletins.js) pendant qu'une génération est en cours,
     * pour afficher une barre de progression en temps réel sans
     * rafraîchir la page.
     */
    public function statut(DemandeGenerationBulletin $demande): JsonResponse
    {
        return response()->json([
            'statut' => $demande->statut?->value,
            'label' => $demande->statut?->label(),
            'traites' => $demande->traites,
            'total' => $demande->total,
            'pourcentage' => $demande->pourcentage(),
            'genere' => $demande->estGeneree(),
            'echec' => $demande->aEchoue(),
            'coinceeSansWorker' => $demande->estCoinceeSansWorker(),
            'erreur' => $demande->erreur,
            'genereAt' => $demande->genere_at?->format('d/m/Y à H:i'),
            'nbBulletinsGeneres' => $demande->nb_bulletins_generes,
            'telechargerUrl' => $demande->estGeneree() ? route('eleves.bulletins.telecharger', $demande) : null,
        ]);
    }

    public function apercu(Classe $classe, Examen $examen, Inscription $inscription): View
    {
        abort_unless($inscription->classe_id === $classe->id, 404);

        return view('eleves.bulletins.apercu', $this->service->papierPourInscription($classe, $examen, $inscription));
    }

    /**
     * Télécharge l'archive ZIP déjà produite par GenererBulletinsClasseJob
     * (voir DemandeGenerationBulletin::chemin_pdf) — un PDF par apprenant,
     * rien n'est généré à la volée ici.
     */
    public function telecharger(DemandeGenerationBulletin $demande): Response
    {
        abort_unless($demande->estGeneree() && $demande->chemin_pdf && Storage::exists($demande->chemin_pdf), 404);

        $nomFichier = 'bulletins-'.Str::slug($demande->classe->nom).'.zip';

        return Storage::download($demande->chemin_pdf, $nomFichier);
    }

    /**
     * Télécharge le PDF d'un seul bulletin, généré à la volée (voir
     * BulletinGenerationService::pdfIndividuel()) — accessible depuis
     * l'écran d'aperçu (bouton « Télécharger en PDF » / « Partager »),
     * indépendamment de la génération groupée de la classe. Le fichier
     * temporaire est supprimé aussitôt lu, rien ne s'accumule sur le disque.
     */
    public function telechargerIndividuel(Classe $classe, Examen $examen, Inscription $inscription): Response
    {
        abort_unless($inscription->classe_id === $classe->id, 404);

        ['chemin' => $chemin, 'nomFichier' => $nomFichier] = $this->service->pdfIndividuel($classe, $examen, $inscription);

        $contenu = Storage::get($chemin);
        Storage::delete($chemin);

        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nomFichier.'"',
        ]);
    }
}
