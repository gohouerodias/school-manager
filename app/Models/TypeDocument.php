<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeDocument extends Model
{
    use HasFactory;

    protected $table = 'types_documents';

    protected $fillable = [
        'libelle',
        'description',
        'obligatoire',
    ];

    protected function casts(): array
    {
        return [
            'obligatoire' => 'bool',
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
