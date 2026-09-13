<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Moyenne annuelle persistée d'une inscription pour une matière (voir la
 * migration `create_moyennes_annuelles_matieres_table` pour le pourquoi de
 * la persistance) — écrite uniquement par
 * BulletinGenerationService::recalculerMoyennesAnnuellesPourClasse(), jamais
 * en direct par l'utilisateur.
 */
class MoyenneAnnuelleMatiere extends Model
{
    use HasFactory;

    protected $table = 'moyennes_annuelles_matieres';

    protected $fillable = [
        'inscription_id',
        'classe_matiere_id',
        'moyenne',
        'calculee_at',
    ];

    protected function casts(): array
    {
        return [
            'moyenne' => 'float',
            'calculee_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Inscription, $this>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /**
     * @return BelongsTo<ClasseMatiere, $this>
     */
    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class);
    }
}
