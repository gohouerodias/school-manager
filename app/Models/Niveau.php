<?php

namespace App\Models;

use App\Enums\CycleNiveau;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Niveau extends Model
{
    use HasFactory;

    protected $table = 'niveaux';

    /**
     * `premiere_scolarisation` (true only for Maternelle 1 / Maternelle 2):
     * a child entering this niveau is assumed to be starting school for the
     * first time, so the fiche élève wizard's "Documents" step doesn't ask
     * for a bulletin/certificat from a previous school — see
     * TypeDocument::$requis_si_transfert.
     */
    protected $fillable = [
        'libelle',
        'ordre',
        'cycle',
        'premiere_scolarisation',
    ];

    protected function casts(): array
    {
        return [
            'cycle' => CycleNiveau::class,
            'premiere_scolarisation' => 'bool',
        ];
    }

    /**
     * @return HasMany<Classe, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    /**
     * Standard curriculum for this niveau — redefined every année académique
     * (see NiveauMatiere), so this alone isn't scoped to one year; use
     * matieresPour() to get the matières configured for a specific année.
     *
     * @return BelongsToMany<Matiere, $this>
     */
    public function matieres(): BelongsToMany
    {
        return $this->belongsToMany(Matiere::class, 'niveau_matiere')
            ->using(NiveauMatiere::class)
            ->withPivot(['annee_academique_id', 'coefficient'])
            ->withTimestamps();
    }

    /**
     * The matières (with their coefficient) configured for this niveau for a
     * given année académique — the curriculum a new Classe for this niveau/
     * année inherits into its own `classe_matiere` (see
     * Academique\ClasseController).
     *
     * @return Collection<int, Matiere>
     */
    public function matieresPour(AnneeAcademique $anneeAcademique): Collection
    {
        return $this->matieres()->wherePivot('annee_academique_id', $anneeAcademique->id)->get();
    }
}
