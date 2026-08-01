<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValeurChampPersonnalise extends Model
{
    use HasFactory;

    protected $table = 'valeurs_champs_personnalises';

    protected $fillable = [
        'eleve_id',
        'champ_personnalise_id',
        'valeur',
    ];

    /**
     * @return BelongsTo<Eleve, $this>
     */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    /**
     * @return BelongsTo<ChampPersonnalise, $this>
     */
    public function champPersonnalise(): BelongsTo
    {
        return $this->belongsTo(ChampPersonnalise::class);
    }
}
