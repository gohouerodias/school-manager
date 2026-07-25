<?php

namespace App\Models;

use App\Enums\StatutTrimestre;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trimestre extends Model
{
    use HasFactory;

    protected $fillable = [
        'annee_academique_id',
        'nom',
        'ordre',
        'date_debut',
        'date_fin',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'statut' => StatutTrimestre::class,
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
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * @return HasMany<Bulletin, $this>
     */
    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    public function ouvrir(): void
    {
        $this->update(['statut' => StatutTrimestre::Ouvert]);
    }

    public function fermer(): void
    {
        $this->update(['statut' => StatutTrimestre::Ferme]);
    }
}
