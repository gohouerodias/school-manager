<?php

namespace App\Exports;

use App\Models\Matiere;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * US B.4 — export Excel de la feuille de saisie d'une classe, telle
 * qu'affichée à l'enseignant (voir Enseignant\
 * EspaceEnseignantController::exportNotes()) : une colonne par matière que
 * cet enseignant peut voir, une ligne par apprenant, plus une moyenne
 * pondérée par coefficient — comme App\Models\Bulletin::calculerMoyenne()
 * et resources/views/enseignant/saisie-notes.blade.php — pour ne jamais
 * afficher une valeur différente de l'écran ou des bulletins admin. Reste
 * partielle si l'enseignant n'a que quelques matières affectées (voir
 * Classe::matieresPourEnseignant()) : ce n'est alors pas la moyenne
 * officielle de l'apprenant, seulement celle des matières visibles ici.
 */
class NotesClasseExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  EloquentCollection<int, Matiere>  $matieres
     * @param  Collection<int, array<string, mixed>>  $students
     */
    public function __construct(
        private readonly EloquentCollection $matieres,
        private readonly Collection $students
    ) {}

    public function collection(): Collection
    {
        return $this->students;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_merge(
            ['Matricule', 'Nom', 'Prénom'],
            $this->matieres->pluck('nom')->all(),
            ['Moyenne']
        );
    }

    /**
     * @param  array<string, mixed>  $student
     * @return array<int, mixed>
     */
    public function map($student): array
    {
        $valeurs = $this->matieres->map(fn (Matiere $matiere) => $student['notes'][$matiere->id] ?? null);
        $renseignees = $valeurs->filter(fn ($v) => $v !== null && $v !== '');

        // Une moyenne partielle (matières manquantes comptées comme 0) serait
        // trompeuse : comme sur l'écran de saisie, on ne l'affiche qu'une
        // fois TOUTES les matières notées pour cet apprenant.
        $notesCompletes = $this->matieres->isNotEmpty() && $renseignees->count() === $this->matieres->count();
        $totalCoefficients = (float) $this->matieres->sum(fn (Matiere $matiere) => $matiere->pivot->coefficient);
        $moyenne = ($notesCompletes && $totalCoefficients > 0)
            ? round(
                $this->matieres->sum(fn (Matiere $matiere) => ($student['notes'][$matiere->id] ?? 0) * $matiere->pivot->coefficient) / $totalCoefficients,
                2
            )
            : '';

        return array_merge(
            [$student['matricule'] ?? '—', $student['nom'] ?? '—', $student['prenom'] ?? '—'],
            $valeurs->map(fn ($v) => $v ?? '')->all(),
            [$moyenne]
        );
    }
}
