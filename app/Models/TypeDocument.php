<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeDocument extends Model
{
    use HasFactory;

    protected $table = 'types_documents';

    /**
     * `requis_si_transfert` (true for "Bulletin de l'école précédente" /
     * "Certificat de scolarité antérieure"): shown in the fiche élève
     * wizard's "Documents" step only when the classe désirée picked in
     * step 1 isn't a Niveau with `premiere_scolarisation` — an élève
     * transferring in from another school, unlike one starting at the
     * maternelle. Independent of `obligatoire`, which is unconditional.
     */
    protected $fillable = [
        'libelle',
        'description',
        'formats_acceptes',
        'obligatoire',
        'protege',
        'requis_si_transfert',
    ];

    protected function casts(): array
    {
        return [
            'formats_acceptes' => 'array',
            'obligatoire' => 'bool',
            'protege' => 'bool',
            'requis_si_transfert' => 'bool',
        ];
    }

    /**
     * @return HasMany<DocumentNumerique, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DocumentNumerique::class, 'type_document_id');
    }
}
