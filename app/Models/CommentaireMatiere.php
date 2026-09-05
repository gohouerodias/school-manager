<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A teacher's remark for one élève, in one matière (via classe_matiere),
 * for one examen — the "Commentaire — {matière}" field of the espace
 * enseignant's comment panel. Any teacher assigned to that classe+matière
 * may write it (unlike Bulletin::resultat_global/appreciation, reserved to
 * the classe's titulaire — see Classe::titulairePour()).
 */
class CommentaireMatiere extends Model
{
    use HasFactory;

    protected $table = 'commentaires_matiere';

    protected $fillable = [
        'eleve_id',
        'classe_matiere_id',
        'examen_id',
        'enseignant_id',
        'commentaire',
    ];

    /**
     * @return BelongsTo<Eleve, $this>
     */
    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    /**
     * @return BelongsTo<ClasseMatiere, $this>
     */
    public function classeMatiere(): BelongsTo
    {
        return $this->belongsTo(ClasseMatiere::class, 'classe_matiere_id');
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
    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}
