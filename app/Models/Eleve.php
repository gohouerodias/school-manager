<?php

namespace App\Models;

use App\Enums\StatutEleve;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Eleve extends Model
{
    use HasFactory;

    protected $fillable = [
        'matricule',
        'nom',
        'prenom',
        'date_naissance',
        'sexe',
        'niveau_souhaite_id',
        'statut',
        'date_archivage',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_archivage' => 'date',
            'statut' => StatutEleve::class,
        ];
    }

    /**
     * Grade level a not-yet-assigned élève is intended for — purely
     * informational until the censeur assigns a real classe (Inscription).
     *
     * @return BelongsTo<Niveau, $this>
     */
    public function niveauSouhaite(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'niveau_souhaite_id');
    }

    /**
     * @return BelongsToMany<ParentTuteur, $this>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentTuteur::class, 'eleve_parent')
            ->using(EleveParent::class)
            ->withPivot('lien_parente')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Inscription, $this>
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /**
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * @return HasMany<ObservationAdministrative, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(ObservationAdministrative::class);
    }

    /**
     * @return HasMany<DocumentNumerique, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DocumentNumerique::class);
    }

    /**
     * @return HasMany<ValeurChampPersonnalise, $this>
     */
    public function valeursPersonnalisees(): HasMany
    {
        return $this->hasMany(ValeurChampPersonnalise::class);
    }

    public function nomComplet(): string
    {
        return "{$this->nom} {$this->prenom}";
    }

    /**
     * Sequential matricule per year, e.g. "2026-1000", "2026-1001"...
     */
    public static function genererMatricule(): string
    {
        $annee = now()->year;

        $dernier = static::query()
            ->where('matricule', 'like', "{$annee}-%")
            ->orderByDesc('matricule')
            ->value('matricule');

        $sequence = $dernier ? ((int) substr($dernier, strlen((string) $annee) + 1)) + 1 : 1000;

        return "{$annee}-{$sequence}";
    }

    public function archiver(): void
    {
        $this->update(['statut' => StatutEleve::Archive, 'date_archivage' => now()->toDateString()]);
    }

    public function desarchiver(): void
    {
        $this->update(['statut' => StatutEleve::Actif, 'date_archivage' => null]);
    }
}
