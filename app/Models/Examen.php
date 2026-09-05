<?php

namespace App\Models;

use App\Enums\SystemeScolaire;
use App\Enums\TypeEvaluation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * @return HasMany<DemandeGenerationBulletin, $this>
     */
    public function demandesGenerationBulletins(): HasMany
    {
        return $this->hasMany(DemandeGenerationBulletin::class);
    }

    /**
     * Examens dont le système correspond au cycle du niveau de $classe (voir
     * SystemeScolaire::cycles()), pour l'année académique de cette classe,
     * les plus récents en premier — les seules "périodes" sélectionnables
     * pour une classe donnée (feuille de saisie enseignant, écran Bulletins
     * admin...). Centralisé ici pour éviter de dupliquer cette règle de
     * correspondance cycle ↔ système à chaque écran qui en a besoin.
     *
     * @return Collection<int, Examen>
     */
    public static function pourClasse(Classe $classe): Collection
    {
        $systemesCompatibles = collect(SystemeScolaire::cases())
            ->filter(fn (SystemeScolaire $s) => in_array($classe->niveau->cycle, $s->cycles(), true))
            ->map(fn (SystemeScolaire $s) => $s->value);

        return static::query()
            ->where('annee_academique_id', $classe->annee_academique_id)
            ->whereIn('systeme', $systemesCompatibles)
            ->orderByDesc('date_examen')
            ->get();
    }
}
