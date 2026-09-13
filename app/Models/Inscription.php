<?php

namespace App\Models;

use App\Enums\DecisionAnnuelle;
use App\Enums\StatutBulletin;
use App\Enums\StatutInscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Inscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'eleve_id',
        'classe_id',
        'date_inscription',
        'statut',
        'moyenne_annuelle',
        'decision',
        'motif_decision',
        'observation_annuelle',
    ];

    protected function casts(): array
    {
        return [
            'date_inscription' => 'date',
            'statut' => StatutInscription::class,
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
     * @return HasMany<MoyenneAnnuelleMatiere, $this>
     */
    public function moyennesMatieres(): HasMany
    {
        return $this->hasMany(MoyenneAnnuelleMatiere::class);
    }

    /**
     * Moyenne simple des bulletins mensuels déjà Validés (voir
     * StatutBulletin) de l'année — un bulletin encore en Brouillon n'est pas
     * définitif et ne doit pas peser dans la moyenne annuelle. `null` (et non
     * 0.0) tant qu'aucun bulletin n'est encore Validé : `avg()` renvoie déjà
     * `null` dans ce cas, un `(float)` naïf le transformerait à tort en 0 —
     * un vrai 0/20 ne doit jamais être confondu avec « pas encore calculable »
     * (voir Academique\DecisionPassageController, qui affichait « 0 » pour
     * tout apprenant sans bulletin validé avant ce correctif).
     */
    public function calculerMoyenneAnnuelle(): ?float
    {
        $moyenne = $this->bulletins()->where('statut', StatutBulletin::Valide)->avg('moyenne_generale');

        return $moyenne !== null ? round((float) $moyenne, 2) : null;
    }

    /**
     * Moyenne annuelle de chacune des matières du programme de la classe,
     * pour ce même apprenant : moyenne simple des moyennes mensuelles de
     * cette matière (chacune déjà une moyenne de toutes les notes de ce mois
     * pour cette matière — voir Bulletin::calculerMoyenne()), sur les seuls
     * mois dont le bulletin est Validé — même règle que
     * calculerMoyenneAnnuelle(), pour que les deux restent cohérentes.
     * Calcul en direct (non mis en cache) : voir MoyenneAnnuelleMatiere pour
     * la version persistée, écrite par
     * BulletinGenerationService::recalculerMoyennesAnnuellesPourClasse().
     *
     * @return Collection<int, array{classe_matiere_id: int, matiere: Matiere, moyenne: ?float}>
     */
    public function moyennesAnnuellesParMatiere(): Collection
    {
        $examenIdsValides = $this->bulletins()->where('statut', StatutBulletin::Valide)->pluck('examen_id');

        return ClasseMatiere::query()
            ->where('classe_id', $this->classe_id)
            ->with('matiere')
            ->get()
            ->sortBy(fn (ClasseMatiere $classeMatiere) => $classeMatiere->matiere->nom)
            ->map(function (ClasseMatiere $classeMatiere) use ($examenIdsValides) {
                $moyennesMensuelles = $examenIdsValides->isEmpty()
                    ? collect()
                    : Note::query()
                        ->where('eleve_id', $this->eleve_id)
                        ->where('classe_matiere_id', $classeMatiere->id)
                        ->whereIn('examen_id', $examenIdsValides)
                        ->selectRaw('examen_id, avg(valeur) as moyenne_mensuelle')
                        ->groupBy('examen_id')
                        ->pluck('moyenne_mensuelle');

                return [
                    'classe_matiere_id' => $classeMatiere->id,
                    'matiere' => $classeMatiere->matiere,
                    'moyenne' => $moyennesMensuelles->isNotEmpty() ? round((float) $moyennesMensuelles->avg(), 2) : null,
                ];
            })
            ->values();
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
