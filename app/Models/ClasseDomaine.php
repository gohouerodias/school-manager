<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ClasseDomaine extends Pivot
{
    use HasFactory;

    protected $table = 'classe_domaine';

    public $incrementing = true;

    protected $fillable = [
        'classe_id',
        'domaine_evaluation_id',
    ];

    /**
     * @return BelongsTo<Classe, $this>
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * @return BelongsTo<DomaineEvaluation, $this>
     */
    public function domaineEvaluation(): BelongsTo
    {
        return $this->belongsTo(DomaineEvaluation::class);
    }

    /**
     * @return HasMany<EvaluationDomaine, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(EvaluationDomaine::class);
    }
}
