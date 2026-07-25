<?php

namespace App\Models;

use App\Enums\DecisionAnnuelle;
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

    public function calculerMoyenneAnnuelle(): float
    {
        return (float) $this->bulletins()->avg('moyenne_generale');
    }

    public function determinerPassage(): void
    {
        $this->update([
            'decision' => $this->moyenne_annuelle >= 10 ? DecisionAnnuelle::Admis : DecisionAnnuelle::Redouble,
        ]);
    }
}
