<?php

namespace App\Models;

use App\Enums\SystemeScolaire;
use App\Enums\TypeEvaluation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
            'date_limite_saisie' => 'datetime',
        ];
    }

    /**
     * « Examen mensuel — octobre 2026 » : le nom de la période d'évaluation,
     * tel qu'affiché dans le sélecteur de la feuille de saisie et repris
     * dans l'export Excel des notes.
     */
    public function libellePeriode(): string
    {
        return 'Examen mensuel — '.$this->date_examen->translatedFormat('F Y');
    }

    /**
     * Une seule évaluation mensuelle par mois et par système, sur une année
     * académique : deux examens du même mois donneraient deux bulletins
     * « octobre » et compteraient deux fois dans la moyenne annuelle.
     * Retourne l'examen déjà présent sur ce mois (hors $saufExamenId), s'il
     * y en a un.
     */
    public static function dejaPresentCeMois(int $anneeAcademiqueId, SystemeScolaire $systeme, string $dateExamen, ?int $saufExamenId = null): ?self
    {
        $date = Carbon::parse($dateExamen);

        return self::query()
            ->where('annee_academique_id', $anneeAcademiqueId)
            ->where('systeme', $systeme)
            ->whereYear('date_examen', $date->year)
            ->whereMonth('date_examen', $date->month)
            ->when($saufExamenId, fn ($query) => $query->whereKeyNot($saufExamenId))
            ->first();
    }

    /**
     * Message affiché quand on tente de créer/déplacer un examen sur un mois
     * qui en a déjà un (voir dejaPresentCeMois()).
     */
    public function messageDoublonMois(): string
    {
        return sprintf(
            "Un examen mensuel existe déjà pour le système %s en %s (examen du %s). Modifiez-le plutôt que d'en créer un second.",
            mb_strtolower($this->systeme->label()),
            $this->date_examen->translatedFormat('F Y'),
            $this->date_examen->format('d/m/Y'),
        );
    }

    /**
     * True once the « délai de saisie des notes » (day AND time) has passed.
     */
    public function delaiSaisieDepasse(): bool
    {
        return now()->greaterThan($this->date_limite_saisie);
    }

    /**
     * « 22/11/2026 à 18h00 » — the deadline as shown to staff.
     */
    public function dateLimiteSaisieLibelle(): string
    {
        return $this->date_limite_saisie->format('d/m/Y à H\hi');
    }

    /**
     * Value for an <input type="datetime-local">.
     */
    public function dateLimiteSaisiePourChamp(): string
    {
        return $this->date_limite_saisie->format('Y-m-d\TH:i');
    }

    /**
     * A deadline typed without a time (older forms, imports, seeders) keeps
     * its historical meaning: open until the end of that day.
     */
    public static function normaliserDateLimiteSaisie(?string $valeur): ?string
    {
        if ($valeur !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            return "{$valeur} 23:59";
        }

        return $valeur;
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
