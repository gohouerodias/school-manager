<?php

namespace App\Models;

use App\Enums\ActionJournal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'date_heure',
        'details',
        'adresse_ip',
    ];

    protected function casts(): array
    {
        return [
            'date_heure' => 'datetime',
        ];
    }

    /**
     * Enregistre une action dans le journal, pour l'utilisateur connecté
     * (ou $user, ex. tentative de connexion échouée sur un compte existant),
     * avec la date et l'adresse IP de la requête. Sans utilisateur connu,
     * rien n'est enregistré (la colonne user_id est obligatoire).
     */
    public static function enregistrer(ActionJournal $action, ?string $details = null, ?User $user = null): void
    {
        $user ??= auth()->user();

        if (! $user) {
            return;
        }

        self::query()->create([
            'user_id' => $user->id,
            'action' => $action->value,
            'date_heure' => now(),
            'details' => $details,
            'adresse_ip' => request()?->ip(),
        ]);
    }

    /**
     * Libellé lisible de l'action (« Connexion », « Archivage d'un
     * apprenant »…) ; le code brut pour une action inconnue.
     */
    public function libelleAction(): string
    {
        return ActionJournal::tryFrom($this->action)?->label() ?? $this->action;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
