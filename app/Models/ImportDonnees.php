<?php

namespace App\Models;

use App\Enums\StatutImport;
use App\Enums\TypeImportDonnees;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportDonnees extends Model
{
    use HasFactory;

    protected $table = 'imports_donnees';

    protected $fillable = [
        'importe_par',
        'type_import',
        'fichier_source',
        'date_import',
        'nombre_lignes_importees',
        'nombre_erreurs',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'type_import' => TypeImportDonnees::class,
            'statut' => StatutImport::class,
            'date_import' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'importe_par');
    }
}
