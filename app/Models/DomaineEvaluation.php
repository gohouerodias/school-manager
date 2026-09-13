<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un domaine d'évaluation de maternelle (Langage, Pré-lecture...) —
 * équivalent maternelle de Matiere. Voir la migration create_domaines_
 * evaluation_table pour le contexte complet.
 */
class DomaineEvaluation extends Model
{
    use HasFactory;

    protected $table = 'domaines_evaluation';

    protected $fillable = [
        'nom',
    ];

    /**
     * Standard programme for this domaine — redefined every année
     * académique (see NiveauDomaine); use niveaux()->wherePivot(...) or
     * Niveau::domainesPour() for a specific année.
     *
     * @return BelongsToMany<Niveau, $this>
     */
    public function niveaux(): BelongsToMany
    {
        return $this->belongsToMany(Niveau::class, 'niveau_domaine')
            ->using(NiveauDomaine::class)
            ->withPivot(['annee_academique_id'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Classe, $this>
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'classe_domaine')
            ->using(ClasseDomaine::class)
            ->withTimestamps();
    }
}
