<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catégorie de matières du bulletin (« Champ de formation » du carnet
 * papier). `ordre` fixe la position du champ sur le bulletin.
 */
class ChampFormation extends Model
{
    use HasFactory;

    protected $table = 'champs_formation';

    protected $fillable = [
        'nom',
        'ordre',
    ];

    /**
     * @return HasMany<Matiere, $this>
     */
    public function matieres(): HasMany
    {
        return $this->hasMany(Matiere::class, 'champ_formation_id');
    }
}
