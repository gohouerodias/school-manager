<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Programme de domaines d'évaluation d'un niveau de maternelle pour une
 * année académique — équivalent maternelle de NiveauMatiere, sans
 * coefficient (voir sa docblock pour le fonctionnement général).
 */
class NiveauDomaine extends Pivot
{
    use HasFactory;

    protected $table = 'niveau_domaine';

    public $incrementing = true;

    protected $fillable = [
        'niveau_id',
        'domaine_evaluation_id',
        'annee_academique_id',
    ];

    /**
     * @return BelongsTo<Niveau, $this>
     */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /**
     * @return BelongsTo<DomaineEvaluation, $this>
     */
    public function domaineEvaluation(): BelongsTo
    {
        return $this->belongsTo(DomaineEvaluation::class);
    }

    /**
     * @return BelongsTo<AnneeAcademique, $this>
     */
    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }
}
