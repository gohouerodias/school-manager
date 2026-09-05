<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnneeAcademique extends Model
{
    use HasFactory;

    protected $table = 'annees_academiques';

    protected $fillable = [
        'libelle',
        'est_active',
        'date_debut',
        'date_fin',
    ];

    protected function casts(): array
    {
        return [
            'est_active' => 'bool',
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    /**
     * @return HasMany<Trimestre, $this>
     */
    public function trimestres(): HasMany
    {
        return $this->hasMany(Trimestre::class);
    }

    /**
     * @return HasMany<Classe, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    /**
     * @return HasMany<AffectationEnseignant, $this>
     */
    public function affectations(): HasMany
    {
        return $this->hasMany(AffectationEnseignant::class);
    }

    /**
     * Curriculum rows (niveau + matière + coefficient) configured for this
     * année — see NiveauMatiere.
     *
     * @return HasMany<NiveauMatiere, $this>
     */
    public function niveauMatieres(): HasMany
    {
        return $this->hasMany(NiveauMatiere::class);
    }

    /**
     * @return HasMany<Examen, $this>
     */
    public function examens(): HasMany
    {
        return $this->hasMany(Examen::class);
    }

    /**
     * Makes this the one active année académique, deactivating any other —
     * only one année can be "active" at a time (enforced here, not at the DB
     * level; see the `annees_academiques` migration). Called by
     * Academique\AnneeAcademiqueController::demarrer(), after the previous
     * active année's élèves have been promoted into this one (see
     * App\Services\PromotionAnnuelleService).
     */
    public function activer(): void
    {
        static::query()->where('id', '!=', $this->id)->where('est_active', true)->update(['est_active' => false]);
        $this->update(['est_active' => true]);
    }
}
