<?php

namespace App\Models;

use App\Enums\TypeChampPersonnalise;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChampPersonnalise extends Model
{
    use HasFactory;

    protected $table = 'champs_personnalises';

    protected $fillable = [
        'libelle',
        'type',
        'options',
        'obligatoire',
        'ordre',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeChampPersonnalise::class,
            'options' => 'array',
            'obligatoire' => 'bool',
            'ordre' => 'integer',
        ];
    }

    /**
     * @return HasMany<ValeurChampPersonnalise, $this>
     */
    public function valeurs(): HasMany
    {
        return $this->hasMany(ValeurChampPersonnalise::class);
    }
}
