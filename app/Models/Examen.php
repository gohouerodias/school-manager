<?php

namespace App\Models;

use App\Enums\SystemeScolaire;
use App\Enums\TypeEvaluation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un examen "campagne" créé depuis la page de gestion des examens (voir
 * Academique\ExamenController). Pour l'instant, seul le système Primaire
 * est implémenté : le choisir crée un unique examen mensuel
 * (TypeEvaluation::EvaluationMensuelle) couvrant, conceptuellement, toutes
 * les classes/élèves du système primaire de l'année académique active,
 * chacun évalué dans les matières indiquées à son programme (bulletin). La
 * saisie des notes par les enseignants elle-même n'est pas encore
 * implémentée — cet enregistrement ne fait que définir la date de l'examen
 * et la date limite de saisie.
 */
class Examen extends Model
{
    use HasFactory;

    protected $fillable = [
        'annee_academique_id',
        'systeme',
        'type',
        'date_examen',
        'date_limite_saisie',
    ];

    protected function casts(): array
    {
        return [
            'systeme' => SystemeScolaire::class,
            'type' => TypeEvaluation::class,
            'date_examen' => 'date',
            'date_limite_saisie' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AnneeAcademique, $this>
     */
    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }
}
