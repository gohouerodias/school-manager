<?php

namespace App\Models;

use App\Enums\TypeEvaluation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'eleve_id',
        'classe_matiere_id',
        'trimestre_id',
        'enseignant_id',
        'valeur',
        'type',
        'numero',
        'date_saisie',
    ];

    protected function casts(): array
    {
        return [
            'valeur' => 'float',
            'type' => TypeEvaluation::class,
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
     * @return BelongsTo<ClasseMatiere, $this>
     */
    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class, 'classe_matiere_id');
    }

    /**
     * @return BelongsTo<Trimestre, $this>
     */
    public function trimestre(): BelongsTo
    {
        return $this->belongsTo(Trimestre::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function verifierModifiable(): bool
    {
        return $this->trimestre?->statut === \App\Enums\StatutTrimestre::Ouvert;
    }
}
