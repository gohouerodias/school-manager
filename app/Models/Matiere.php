<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Matiere extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
    ];

    /**
     * @return BelongsToMany<Classe, $this>
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'classe_matiere')
            ->using(ClasseMatiere::class)
            ->withPivot('coefficient')
            ->withTimestamps();
    }
}
