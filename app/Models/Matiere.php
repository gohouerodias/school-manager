<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Matiere extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'champ_formation_id',
    ];

    /**
     * @return BelongsTo<ChampFormation, $this>
     */
    public function champFormation(): BelongsTo
    {
        return $this->belongsTo(ChampFormation::class, 'champ_formation_id');
    }

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

    /**
     * Niveaux whose curriculum includes this matière, for whichever année(s)
     * académique(s) — see Niveau::matieresPour() for the année-scoped view.
     *
     * @return BelongsToMany<Niveau, $this>
     */
    public function niveaux(): BelongsToMany
    {
        return $this->belongsToMany(Niveau::class, 'niveau_matiere')
            ->using(NiveauMatiere::class)
            ->withPivot(['annee_academique_id', 'coefficient'])
            ->withTimestamps();
    }
}
