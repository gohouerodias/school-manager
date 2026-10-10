<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EleveParent extends Pivot
{
    protected $table = 'eleve_parent';

    protected $fillable = [
        'eleve_id',
        'parent_tuteur_id',
        'lien_parente',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function parentTuteur(): BelongsTo
    {
        return $this->belongsTo(ParentTuteur::class);
    }
}
