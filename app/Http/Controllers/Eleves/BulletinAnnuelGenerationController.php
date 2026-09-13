<?php

namespace App\Http\Controllers\Eleves;

use App\Enums\StatutGenerationBulletin;
use App\Http\Controllers\Controller;
use App\Http\Requests\DemanderGenerationBulletinAnnuelRequest;
use App\Jobs\GenererBulletinsAnnuelsClasseJob;
use App\Models\Classe;
use App\Models\DemandeGenerationBulletinAnnuel;
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
 * Bulletin annuel — équivalent annuel de BulletinGenerationController (voir
 * ce contrôleur pour les conventions générales : génération en arrière-plan
 * sur la file d'attente, jamais dans la requête HTTP). Partage l'écran
 * "Bulletins" (voir Eleves\BulletinGenerationController::index(), qui
 * calcule aussi $payloadAnnuel/$demandeAnnuel pour la section « Bulletin
 * annuel » de eleves/bulletins/index.blade.php) plutôt que d'avoir son propre
 * index() — seules les actions (générer/statut/aperçu/télécharger) sont
 * séparées, puisqu'elles portent sur une classe entière et non sur un
 * examen précis.
 */
class BulletinAnnuelGenerationController extends Controller
{
    public function __construct(private readonly BulletinGenerationService $service) {}

    /**
     * Lance immédiatement la génération annuelle (job en file d'attente —
     * voir App\Jobs\GenererBulletinsAnnuelsClasseJob). Revérifie
     * systématiquement, côté serveur, que le seuil `nombre_evaluations_prevues`
     * est atteint (voir BulletinGenerationService::payloadAnnuelPourClasse())
     * — jamais faire confiance uniquement au bouton désactivé côté navigateur.
     */
    public function demanderGeneration(DemanderGenerationBulletinAnnuelRequest $request): RedirectResponse
    {
        $classe = Classe::query()->findOrFail($request->validated('classe_id'));

        $redirectBack = redirect()->route('eleves.bulletins.index', ['classe_id' => $classe->id]);

        $demandeExistante = DemandeGenerationBulletinAnnuel::query()->where('classe_id', $classe->id)->first();

        if ($demandeExistante?->bloqueUneNouvelleGeneration()) {
            return $redirectBack->with('toast', "La génération du bulletin annuel de {$classe->nom} est déjà en cours.");
        }

        $payload = $this->service->payloadAnnuelPourClasse($classe);

        if (! $payload['seuilAtteint']) {
            return $redirectBack->with('toast', "Impossible : {$payload['nombreEvaluationsCreees']} évaluation(s) créée(s) sur {$payload['nombreEvaluationsRequis']} requise(s) pour proposer le bulletin annuel de {$classe->nom}.");
        }

        if ($payload['total'] === 0 || $payload['moyennesEnAttenteCount'] > 0) {
            $motif = $classe->estMaternelle()
                ? "aucun apprenant n'est inscrit dans la classe {$classe->nom}"
                : "aucun bulletin mensuel Validé n'existe encore pour au moins un apprenant de la classe {$classe->nom}";

            return $redirectBack->with('toast', "Impossible : {$motif}.");
        }

        $regeneration = $demandeExistante?->estGeneree() ?? false;

        if ($demandeExistante?->chemin_pdf && Storage::exists($demandeExistante->chemin_pdf)) {
            Storage::delete($demandeExistante->chemin_pdf);
        }

        $demande = DemandeGenerationBulletinAnnuel::query()->updateOrCreate(
            ['classe_id' => $classe->id],
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

        GenererBulletinsAnnuelsClasseJob::dispatch($demande);

        $toast = $regeneration
            ? "Régénération du bulletin annuel de {$classe->nom} démarrée — suivez la progression ci-dessous."
            : "Génération du bulletin annuel de {$classe->nom} démarrée — suivez la progression ci-dessous.";

        return $redirectBack->with('toast', $toast);
    }

    /**
     * Recalcule et persiste la moyenne annuelle générale et par matière de
     * chaque apprenant de la classe (voir BulletinGenerationService::
     * recalculerMoyennesAnnuellesPourClasse()) — bouton "Calculer les
     * moyennes annuelles" de la section Bulletin annuel. Sans rapport avec le
     * seuil `nombre_evaluations_prevues` : utile même avant que le bulletin
     * annuel lui-même soit proposable, pour que la moyenne annuelle par
     * matière soit déjà prête une fois que ça sera le cas.
     */
    public function recalculer(Request $request): RedirectResponse
    {
        $classe = Classe::query()->findOrFail($request->input('classe_id'));

        abort_if($classe->estMaternelle(), 404);

        $count = $this->service->recalculerMoyennesAnnuellesPourClasse($classe);

        return redirect()->route('eleves.bulletins.index', ['classe_id' => $classe->id])
            ->with('toast', "Moyennes annuelles recalculées pour {$count} apprenant(s) de {$classe->nom}.");
    }

    /**
     * Interrogé par polling depuis l'écran Bulletins (voir
     * resources/js/bulletins.js) pendant qu'une génération annuelle est en
     * cours.
     */
    public function statut(DemandeGenerationBulletinAnnuel $demande): JsonResponse
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
            'telechargerUrl' => $demande->estGeneree() ? route('eleves.bulletins.annuel.telecharger', $demande) : null,
        ]);
    }

    public function apercu(Classe $classe, Inscription $inscription): View
    {
        abort_unless($inscription->classe_id === $classe->id, 404);

        return view('eleves.bulletins.apercu-annuel', $this->service->papierAnnuelPourInscription($classe, $inscription));
    }

    /**
     * Télécharge l'archive ZIP déjà produite par
     * GenererBulletinsAnnuelsClasseJob (voir
     * DemandeGenerationBulletinAnnuel::chemin_pdf).
     */
    public function telecharger(DemandeGenerationBulletinAnnuel $demande): Response
    {
        abort_unless($demande->estGeneree() && $demande->chemin_pdf && Storage::exists($demande->chemin_pdf), 404);

        $nomFichier = 'bulletins-annuels-'.Str::slug($demande->classe->nom).'.zip';

        return Storage::download($demande->chemin_pdf, $nomFichier);
    }

    /**
     * Télécharge le PDF annuel d'un seul bulletin, généré à la volée (voir
     * BulletinGenerationService::pdfAnnuelIndividuel()) — accessible depuis
     * l'écran d'aperçu, indépendamment de la génération groupée de la classe.
     */
    public function telechargerIndividuel(Classe $classe, Inscription $inscription): Response
    {
        abort_unless($inscription->classe_id === $classe->id, 404);

        ['chemin' => $chemin, 'nomFichier' => $nomFichier] = $this->service->pdfAnnuelIndividuel($classe, $inscription);

        $contenu = Storage::get($chemin);
        Storage::delete($chemin);

        return response($contenu, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nomFichier.'"',
        ]);
    }
}
