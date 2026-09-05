<?php

namespace App\Models;

use App\Enums\DecisionAnnuelle;
use App\Enums\StatutBulletin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'eleve_id',
        'classe_id',
        'date_inscription',
        'moyenne_annuelle',
        'decision',
        'motif_decision',
    ];

    protected function casts(): array
    {
        return [
            'date_inscription' => 'date',
            'decision' => DecisionAnnuelle::class,
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
     * @return BelongsTo<Classe, $this>
     */
    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    /**
     * @return HasMany<Bulletin, $this>
     */
    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    /**
     * Moyenne simple des bulletins mensuels déjà Validés (voir
     * StatutBulletin) de l'année — un bulletin encore en Brouillon n'est pas
     * définitif et ne doit pas peser dans la moyenne annuelle.
     */
    public function calculerMoyenneAnnuelle(): float
    {
        return (float) $this->bulletins()->where('statut', StatutBulletin::Valide)->avg('moyenne_generale');
    }

    /**
     * Proposition automatique "Admis" / "Redouble" selon le seuil configuré
     * (voir ParametreSysteme::$seuil_passage, Academique\
     * ParametreAcademiqueController) — "Exclu" reste un choix manuel de la
     * direction, jamais proposé automatiquement (voir Academique\
     * DecisionPassageController).
     */
    public function determinerPassage(): void
    {
        $seuil = ParametreSysteme::query()->value('seuil_passage') ?? 10;

        $this->update([
            'decision' => $this->moyenne_annuelle >= $seuil ? DecisionAnnuelle::Admis : DecisionAnnuelle::Redouble,
        ]);
    }
}
