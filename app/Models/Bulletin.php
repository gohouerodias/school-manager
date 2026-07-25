<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bulletin extends Model
{
    use HasFactory;

    protected $fillable = [
        'inscription_id',
        'trimestre_id',
        'moyenne_generale',
        'appreciation',
        'rang',
        'date_generation',
    ];

    protected function casts(): array
    {
        return [
            'date_generation' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Inscription, $this>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /**
     * @return BelongsTo<Trimestre, $this>
     */
    public function trimestre(): BelongsTo
    {
        return $this->belongsTo(Trimestre::class);
    }

    public function calculerMoyenne(): float
    {
        $eleveId = $this->inscription->eleve_id;
        $classeId = $this->inscription->classe_id;

        $moyennesParMatiere = Note::query()
            ->join('classe_matiere', 'notes.classe_matiere_id', '=', 'classe_matiere.id')
            ->where('notes.eleve_id', $eleveId)
            ->where('notes.trimestre_id', $this->trimestre_id)
            ->where('classe_matiere.classe_id', $classeId)
            ->selectRaw('classe_matiere.coefficient as coefficient, avg(notes.valeur) as moyenne')
            ->groupBy('classe_matiere.id', 'classe_matiere.coefficient')
            ->get();

        $totalCoef = $moyennesParMatiere->sum('coefficient');

        if ($totalCoef === 0.0) {
            return 0.0;
        }

        return round($moyennesParMatiere->sum(fn ($m) => $m->moyenne * $m->coefficient) / $totalCoef, 2);
    }

    public function genererBulletin(): void
    {
        $this->update([
            'moyenne_generale' => $this->calculerMoyenne(),
            'date_generation' => now()->toDateString(),
        ]);
    }
}
