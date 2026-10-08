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
        'examen_id',
        'enseignant_id',
        'valeur',
        'critere_minimal',
        'critere_perfectionnement',
        'commentaire',
        'type',
        'numero',
        'date_saisie',
    ];

    /** Barème du primaire (séance du 07/10/2026) : critère minimal /18 + perfectionnement /2 = /20. */
    public const CRITERE_MINIMAL_MAX = 18;

    public const CRITERE_PERFECTIONNEMENT_MAX = 2;

    /**
     * Note totale (/20) à partir des deux critères ; null quand aucun des
     * deux n'est renseigné (la case est vide).
     */
    public static function totalDesCriteres(?float $critereMinimal, ?float $criterePerfectionnement): ?float
    {
        if ($critereMinimal === null && $criterePerfectionnement === null) {
            return null;
        }

        return round(($critereMinimal ?? 0) + ($criterePerfectionnement ?? 0), 2);
    }

    protected function casts(): array
    {
        return [
            'valeur' => 'float',
            'critere_minimal' => 'float',
            'critere_perfectionnement' => 'float',
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

    /**
     * A teacher can no longer create/edit this note once the examen's
     * "délai de remplissage des notes" (Examen::date_limite_saisie) has
     * passed — see Enseignant\EspaceEnseignantController.
     */
    public function verifierModifiable(): bool
    {
        return $this->examen !== null && ! $this->examen->delaiSaisieDepasse();
    }
}
