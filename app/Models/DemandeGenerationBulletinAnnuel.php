<?php

namespace App\Models;

use App\Enums\StatutGenerationBulletin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Équivalent annuel de DemandeGenerationBulletin (voir ce modèle pour le
 * fonctionnement détaillé — même statut/progression, même job en file
 * d'attente) : « la classe X a demandé la génération de son bulletin annuel »
 * (bouton « Générer les bulletins annuels de la classe », voir
 * Eleves\BulletinAnnuelGenerationController::demanderGeneration()), traitée
 * par App\Jobs\GenererBulletinsAnnuelsClasseJob. Une seule ligne par classe
 * (`classe_id` unique) puisqu'il n'y a qu'un seul bulletin annuel par classe
 * et par année, contrairement au mensuel qui varie par examen.
 */
class DemandeGenerationBulletinAnnuel extends Model
{
    use HasFactory;

    protected $table = 'demandes_generation_bulletins_annuels';

    protected $fillable = [
        'classe_id',
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
     * Voir DemandeGenerationBulletin::estCoinceeSansWorker() — même garde-fou.
     */
    public function estCoinceeSansWorker(): bool
    {
        return $this->statut === StatutGenerationBulletin::EnAttente
            && $this->demande_at !== null
            && $this->demande_at->lt(now()->subMinutes(2));
    }

    public function bloqueUneNouvelleGeneration(): bool
    {
        return $this->estEnCours() && ! $this->estCoinceeSansWorker();
    }

    public function pourcentage(): int
    {
        return $this->total > 0 ? (int) round($this->traites / $this->total * 100) : 0;
    }
}
