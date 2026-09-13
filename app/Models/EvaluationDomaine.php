<?php

namespace App\Models;

use App\Enums\NiveauQualitatif;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L'appréciation mensuelle d'un apprenant de maternelle pour un domaine
 * d'évaluation — équivalent maternelle de Note+CommentaireMatiere combinés
 * (voir la migration create_evaluations_domaine_table).
 */
class EvaluationDomaine extends Model
{
    use HasFactory;

    protected $table = 'evaluations_domaine';

    protected $fillable = [
        'eleve_id',
        'classe_domaine_id',
        'examen_id',
        'enseignant_id',
        'valeur',
        'observation',
        'date_saisie',
    ];

    protected function casts(): array
    {
        return [
            'valeur' => NiveauQualitatif::class,
            'date_saisie' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Eleve, $this>
     */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    /**
     * @return BelongsTo<ClasseDomaine, $this>
     */
    public function classeDomaine(): BelongsTo
    {
        return $this->belongsTo(ClasseDomaine::class, 'classe_domaine_id');
    }

    /**
     * @return BelongsTo<Examen, $this>
     */
    public function examen(): BelongsTo
    {
        return $this->belongsTo(Examen::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
