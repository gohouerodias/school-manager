<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ParentTuteur extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'prenom',
        'telephone',
        'email',
        'profession',
        'adresse',
    ];

    /**
     * @return BelongsToMany<Eleve, $this>
     */
    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'eleve_parent')
            ->using(EleveParent::class)
            ->withPivot('lien_parente')
            ->withTimestamps();
    }
}
