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

    protected $fillable = [
        'libelle',
        'ordre',
        'cycle',
    ];

    protected function casts(): array
    {
        return [
            'cycle' => CycleNiveau::class,
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
