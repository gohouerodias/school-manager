<?php

namespace App\Models;

use App\Enums\FormatRapport;
use App\Enums\TypeRapport;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rapport extends Model
{
    use HasFactory;

    protected $fillable = [
        'genere_par',
        'type',
        'format',
        'date_generation',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeRapport::class,
            'format' => FormatRapport::class,
            'date_generation' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par');
    }
}
