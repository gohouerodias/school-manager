<?php

namespace App\Models;

use App\Enums\StatutEleve;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Eleve extends Model
{
    use HasFactory;

    protected $fillable = [
        'matricule',
        'nom',
        'prenom',
        'date_naissance',
        'sexe',
        'niveau_souhaite_id',
        'statut',
        'date_archivage',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance' => 'date',
            'date_archivage' => 'date',
            'statut' => StatutEleve::class,
        ];
    }

    /**
     * Grade level a not-yet-assigned élève is intended for — purely
     * informational until the censeur assigns a real classe (Inscription).
     *
     * @return BelongsTo<Niveau, $this>
     */
    public function niveauSouhaite(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'niveau_souhaite_id');
    }

    /**
     * @return BelongsToMany<ParentTuteur, $this>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentTuteur::class, 'eleve_parent')
            ->using(EleveParent::class)
            ->withPivot('lien_parente')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Inscription, $this>
     */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /**
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * @return HasMany<ObservationAdministrative, $this>
     */
    public function observations(): HasMany
    {
        return $this->hasMany(ObservationAdministrative::class);
    }

    /**
     * @return HasMany<DocumentNumerique, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(DocumentNumerique::class);
    }

    /**
     * @return HasMany<ValeurChampPersonnalise, $this>
     */
    public function valeursPersonnalisees(): HasMany
    {
        return $this->hasMany(ValeurChampPersonnalise::class);
    }

    public function nomComplet(): string
    {
        return "{$this->nom} {$this->prenom}";
    }

    /**
     * The élève's "Photo d'identité" document, if one has been uploaded —
     * matched by type libellé (types de documents are admin-configurable,
     * so there's no fixed id/enum to key on) rather than an exact string,
     * so a rename like "Photo d'identité (2x2)" still matches. Used to show
     * a real photo instead of initials on the fiche and in the list.
     * Requires `documents.typeDocument` to already be eager-loaded.
     */
    public function photoIdentite(): ?DocumentNumerique
    {
        return $this->documents
            ->filter(fn (DocumentNumerique $document) => Str::contains($document->typeDocument?->libelle ?? '', 'photo', ignoreCase: true))
            ->sortByDesc('date_ajout')
            ->first();
    }

    /**
     * The élève's Inscription for the currently active année académique, if
     * any — distinct from "the latest inscription overall", which could be
     * a past année's historical record. Used by the éditable "Classe"
     * dropdown (list + EleveClasseController) so a classe change today
     * never overwrites a prior year's Inscription. Requires
     * `inscriptions.classe.anneeAcademique` to already be eager-loaded.
     */
    public function inscriptionActive(): ?Inscription
    {
        return $this->inscriptions
            ->first(fn (Inscription $inscription) => $inscription->classe?->anneeAcademique?->est_active === true);
    }

    public function archiver(): void
    {
        $this->update(['statut' => StatutEleve::Archive, 'date_archivage' => now()->toDateString()]);
    }

    public function desarchiver(): void
    {
        $this->update(['statut' => StatutEleve::Actif, 'date_archivage' => null]);
    }
}
