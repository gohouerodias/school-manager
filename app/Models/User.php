<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ProfilUtilisateur;
use App\Enums\StatutUtilisateur;
use App\Notifications\ResetPasswordNotification;
use App\Services\TwoFactorAuthenticator;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profil',
        'statut',
        'deux_fa_actif',
        'secret_2fa',
        'doit_changer_mot_de_passe',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'secret_2fa',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profil' => ProfilUtilisateur::class,
            'statut' => StatutUtilisateur::class,
            'deux_fa_actif' => 'bool',
            'doit_changer_mot_de_passe' => 'bool',
        ];
    }

    /**
     * @return HasMany<Note, $this>
     */
    public function notesSaisies(): HasMany
    {
        return $this->hasMany(Note::class, 'enseignant_id');
    }

    /**
     * @return HasMany<AffectationEnseignant, $this>
     */
    public function affectations(): HasMany
    {
        return $this->hasMany(AffectationEnseignant::class, 'enseignant_id');
    }

    /**
     * @return HasMany<ObservationAdministrative, $this>
     */
    public function observationsRedigees(): HasMany
    {
        return $this->hasMany(ObservationAdministrative::class, 'auteur_id');
    }

    /**
     * @return HasMany<DocumentNumerique, $this>
     */
    public function documentsTeleverses(): HasMany
    {
        return $this->hasMany(DocumentNumerique::class, 'televerse_par');
    }

    /**
     * @return HasMany<JournalAction, $this>
     */
    public function journalActions(): HasMany
    {
        return $this->hasMany(JournalAction::class);
    }

    /**
     * @return HasMany<Rapport, $this>
     */
    public function rapportsGeneres(): HasMany
    {
        return $this->hasMany(Rapport::class, 'genere_par');
    }

    /**
     * @return HasMany<ImportDonnees, $this>
     */
    public function importsDonnees(): HasMany
    {
        return $this->hasMany(ImportDonnees::class, 'importe_par');
    }

    /**
     * Send the branded, French password reset notification instead of
     * Laravel's default one.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function verifierCode2FA(string $code): bool
    {
        if (! $this->secret_2fa) {
            return false;
        }

        return app(TwoFactorAuthenticator::class)->verify($this->secret_2fa, $code);
    }

    /**
     * Activate 2FA for this account and return the newly generated secret,
     * so the caller can render it (e.g. as a QR code) to the user once.
     */
    public function activerDoubleAuth(): string
    {
        $secret = app(TwoFactorAuthenticator::class)->generateSecretKey();

        $this->update([
            'secret_2fa' => $secret,
            'deux_fa_actif' => true,
        ]);

        return $secret;
    }

    public function desactiverDoubleAuth(): void
    {
        $this->update([
            'secret_2fa' => null,
            'deux_fa_actif' => false,
        ]);
    }

    public function definirMotDePasse(string $motDePasse): void
    {
        $this->update([
            'password' => $motDePasse,
            'doit_changer_mot_de_passe' => false,
        ]);
    }

    public function archiverCompte(): void
    {
        $this->update(['statut' => StatutUtilisateur::Archive]);
    }

    public function changerProfil(ProfilUtilisateur $profil): void
    {
        $this->update(['profil' => $profil]);
    }

    /**
     * Up to two initials derived from the user's full name, for avatar chips.
     */
    public function initials(): string
    {
        $parts = array_filter(preg_split('/\s+/', trim($this->name)) ?: []);
        $letters = array_map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)), $parts);

        return implode('', array_slice($letters, 0, 2));
    }
}
