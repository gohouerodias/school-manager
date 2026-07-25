<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ProfilUtilisateur;
use App\Enums\StatutUtilisateur;
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
}
