<?php

namespace App\Models;

use App\Enums\CycleNiveau;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Niveau extends Model
{
    use HasFactory;

    protected $table = 'niveaux';

    /**
     * `premiere_scolarisation` (true only for Maternelle 1 / Maternelle 2):
     * a child entering this niveau is assumed to be starting school for the
     * first time, so the fiche élève wizard's "Documents" step doesn't ask
     * for a bulletin/certificat from a previous school — see
     * TypeDocument::$requis_si_transfert.
     */
    protected $fillable = [
        'libelle',
        'ordre',
        'cycle',
        'premiere_scolarisation',
    ];

    protected function casts(): array
    {
        return [
            'cycle' => CycleNiveau::class,
            'premiere_scolarisation' => 'bool',
        ];
    }

    /**
     * @return HasMany<Classe, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }
}
