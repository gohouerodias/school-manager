<?php

namespace App\Models;

use App\Enums\StatutEleve;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'lieu_naissance',
        'sexe',
        'adresse',
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

    public function archiver(): void
    {
        $this->update(['statut' => StatutEleve::Archive, 'date_archivage' => now()->toDateString()]);
    }

    public function desarchiver(): void
    {
        $this->update(['statut' => StatutEleve::Actif, 'date_archivage' => null]);
    }
}
