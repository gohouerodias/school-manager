<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Standard curriculum (matières + coefficients) for a niveau, redefined each
 * année académique since the programme can change from one year to the
 * next (see the `niveau_matiere` migration). When a new Classe is created
 * for a given niveau/année, its `classe_matiere` rows are copied from
 * whichever NiveauMatiere rows exist for that niveau + année (see
 * App\Services\PromotionAnnuelleService and Academique\ClasseController) —
 * still adjustable per classe afterwards, since ClasseMatiere is a separate,
 * independent pivot.
 */
class NiveauMatiere extends Pivot
{
    use HasFactory;

    protected $table = 'niveau_matiere';

    public $incrementing = true;

    protected $fillable = [
        'niveau_id',
        'matiere_id',
        'annee_academique_id',
        'coefficient',
    ];

    /**
     * @return BelongsTo<Niveau, $this>
     */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /**
     * @return BelongsTo<Matiere, $this>
     */
    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    /**
     * @return BelongsTo<AnneeAcademique, $this>
     */
    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }
}
