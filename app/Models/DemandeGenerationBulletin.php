<?php

namespace App\Models;

use App\Enums\StatutGenerationBulletin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * « La classe X a demandé la génération des bulletins de l'examen Y »
 * (bouton « Générer les bulletins de la classe », voir
 * Eleves\BulletinGenerationController::demanderGeneration()) — la
 * génération se lance immédiatement en arrière-plan (voir
 * App\Jobs\GenererBulletinsClasseJob, dispatché sur la file d'attente pour
 * ne jamais bloquer le serveur web), cette ligne suit sa progression
 * (`statut`, `traites`/`total`) jusqu'à `genere_at`/`nb_bulletins_generes`/
 * `chemin_pdf` (le chemin d'une archive ZIP contenant un PDF par apprenant,
 * malgré son nom), ou `erreur` en cas d'échec.
 */
class DemandeGenerationBulletin extends Model
{
    use HasFactory;

    protected $table = 'demandes_generation_bulletins';

    protected $fillable = [
        'classe_id',
        'examen_id',
        'demande_par_id',
        'demande_at',
        'statut',
        'genere_at',
        'nb_bulletins_generes',
        'total',
        'traites',
        'chemin_pdf',
        'erreur',
    ];

    protected function casts(): array
    {
        return [
            'demande_at' => 'datetime',
            'genere_at' => 'datetime',
            'statut' => StatutGenerationBulletin::class,
        ];
    }

    /**
     * @return BelongsTo<Classe, $this>
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * @return BelongsTo<Examen, $this>
     */
    public function examen(): BelongsTo
    {
        return $this->belongsTo(Examen::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function demandePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par_id');
    }

    public function estEnCours(): bool
    {
        return in_array($this->statut, [StatutGenerationBulletin::EnAttente, StatutGenerationBulletin::EnCours], true);
    }

    public function estGeneree(): bool
    {
        return $this->statut === StatutGenerationBulletin::Termine;
    }

    public function aEchoue(): bool
    {
        return $this->statut === StatutGenerationBulletin::Echec;
    }

    /**
     * Vrai si cette demande est restée `EnAttente` plus de 2 minutes sans
     * qu'aucun worker ne l'ait prise en charge (un job passe en `EnCours`
     * quasi instantanément une fois un worker actif — voir `composer run
     * dev`/`php artisan queue:work`). Sans ce garde-fou, une demande créée
     * pendant qu'aucun worker ne tourne resterait "en cours" indéfiniment :
     * ni le bouton (voir eleves/bulletins/index.blade.php) ni
     * demanderGeneration() ne la laisseraient jamais se relancer.
     */
    public function estCoinceeSansWorker(): bool
    {
        return $this->statut === StatutGenerationBulletin::EnAttente
            && $this->demande_at !== null
            && $this->demande_at->lt(now()->subMinutes(2));
    }

    /**
     * Vrai si cette demande bloque réellement une nouvelle génération —
     * c'est estEnCours() en excluant le cas "coincée sans worker" ci-dessus,
     * qui doit au contraire pouvoir être relancé.
     */
    public function bloqueUneNouvelleGeneration(): bool
    {
        return $this->estEnCours() && ! $this->estCoinceeSansWorker();
    }

    /**
     * Pourcentage de bulletins traités jusqu'ici (0-100) — piloté par
     * App\Jobs\GenererBulletinsClasseJob au fil du traitement.
     */
    public function pourcentage(): int
    {
        return $this->total > 0 ? (int) round($this->traites / $this->total * 100) : 0;
    }
}
