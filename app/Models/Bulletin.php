<?php

namespace App\Models;

use App\Enums\ResultatMensuel;
use App\Enums\StatutBulletin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bulletin extends Model
{
    use HasFactory;

    protected $fillable = [
        'inscription_id',
        'examen_id',
        'moyenne_generale',
        'appreciation',
        'resultat_global',
        'assiduite',
        'conduite',
        'defauts_majeurs',
        'qualites',
        'decision_pedagogique',
        'statut',
        'valide_par_id',
        'valide_at',
        'rang',
        'date_generation',
    ];

    protected function casts(): array
    {
        return [
            'date_generation' => 'date',
            'resultat_global' => ResultatMensuel::class,
            'statut' => StatutBulletin::class,
            'valide_at' => 'datetime',
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
     * @return BelongsTo<Examen, $this>
     */
    public function examen(): BelongsTo
    {
        return $this->belongsTo(Examen::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function valideParUtilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par_id');
    }

    public function estValide(): bool
    {
        return $this->statut === StatutBulletin::Valide;
    }

    public function calculerMoyenne(): float
    {
        $eleveId = $this->inscription->eleve_id;
        $classeId = $this->inscription->classe_id;

        $moyennesParMatiere = Note::query()
            ->join('classe_matiere', 'notes.classe_matiere_id', '=', 'classe_matiere.id')
            ->where('notes.eleve_id', $eleveId)
            ->where('notes.examen_id', $this->examen_id)
            ->where('classe_matiere.classe_id', $classeId)
            ->selectRaw('classe_matiere.coefficient as coefficient, avg(notes.valeur) as moyenne')
            ->groupBy('classe_matiere.id', 'classe_matiere.coefficient')
            ->get();

        $totalCoef = (float) $moyennesParMatiere->sum('coefficient');

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
