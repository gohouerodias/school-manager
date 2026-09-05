<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classe extends Model
{
    use HasFactory;

    protected $fillable = [
        'niveau_id',
        'annee_academique_id',
        'nom',
    ];

    /**
     * @return BelongsTo<Niveau, $this>
     */
    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    /**
     * @return BelongsTo<AnneeAcademique, $this>
     */
    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    /**
     * @return BelongsToMany<Matiere, $this>
     */
    public function matieres(): BelongsToMany
    {
        return $this->belongsToMany(Matiere::class, 'classe_matiere')
            ->using(ClasseMatiere::class)
            ->withPivot('coefficient')
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
     * @return HasMany<AffectationEnseignant, $this>
     */
    public function affectations(): HasMany
    {
        return $this->hasMany(AffectationEnseignant::class);
    }

    /**
     * The classe's titulaire (professeur principal) for a given année — the
     * only teacher allowed to write the monthly bulletin's overall rating/
     * comment (see Bulletin::resultat_global/appreciation and
     * Enseignant\EspaceEnseignantController). Null if no affectation for
     * this classe/année has `est_professeur_principal` set — that's a valid
     * (if incomplete) configuration, not an error.
     */
    public function titulairePour(AnneeAcademique $anneeAcademique): ?User
    {
        return $this->affectations()
            ->where('annee_academique_id', $anneeAcademique->id)
            ->where('est_professeur_principal', true)
            ->first()
            ?->enseignant;
    }

    /**
     * The matières this classe's programme has that a given enseignant is
     * actually affected to teach here, for a given année — what the espace
     * enseignant's "Saisie des notes" grid should show as columns (a teacher
     * only sees/edits the matière(s) they were assigned, even if the classe
     * has more matières at its programme overall).
     *
     * @return Collection<int, Matiere>
     */
    public function matieresPourEnseignant(User $enseignant, AnneeAcademique $anneeAcademique): Collection
    {
        $matiereIds = $this->affectations()
            ->where('annee_academique_id', $anneeAcademique->id)
            ->where('enseignant_id', $enseignant->id)
            ->pluck('matiere_id');

        return $this->matieres()->whereIn('matieres.id', $matiereIds)->get();
    }

    /**
     * Vrai si $eleveId a une Note pour chacune des matières du programme de
     * cette classe pour cet examen — c'est cette complétude (et non le
     * statut Validé du bulletin) qui détermine si sa moyenne est réellement
     * prête, ou encore « en attente » : voir Bulletin::calculerMoyenne(),
     * qui moyenne ce qui existe déjà même quand il manque des notes, et
     * BulletinGenerationService::payloadPourClasse(), qui expose ce
     * booléen par ligne à l'écran Bulletins (Eleves\
     * BulletinGenerationController) pour piloter le bouton « Générer ».
     * Centralisé ici pour éviter de dupliquer cette règle (voir aussi
     * Enseignant\EspaceEnseignantController::notesCompletesPour()).
     */
    public function notesCompletesPour(int $eleveId, Examen $examen): bool
    {
        $classeMatiereIds = ClasseMatiere::query()->where('classe_id', $this->id)->pluck('id');

        if ($classeMatiereIds->isEmpty()) {
            return false;
        }

        $notesRenseignees = Note::query()
            ->where('eleve_id', $eleveId)
            ->where('examen_id', $examen->id)
            ->whereIn('classe_matiere_id', $classeMatiereIds)
            ->count();

        return $notesRenseignees === $classeMatiereIds->count();
    }
}
