<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentNumerique extends Model
{
    use HasFactory;

    protected $table = 'documents_numeriques';

    protected $fillable = [
        'eleve_id',
        'inscription_id',
        'type_document_id',
        'televerse_par',
        'chemin_fichier',
        'date_ajout',
    ];

    protected function casts(): array
    {
        return [
            'date_ajout' => 'date',
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
     * Rempli seulement pour un document justifiant un événement précis du
     * parcours scolaire (ex : preuve d'un "Transféré entrant") — voir la
     * migration ajoutant cette colonne. Null pour les documents rattachés
     * seulement à l'élève en général (pièce d'identité, photo…).
     *
     * @return BelongsTo<Inscription, $this>
     */
    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    /**
     * @return BelongsTo<TypeDocument, $this>
     */
    public function typeDocument(): BelongsTo
    {
        return $this->belongsTo(TypeDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function televerseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'televerse_par');
    }
}
