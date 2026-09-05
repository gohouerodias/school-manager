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
 * cet enseignant peut voir, une ligne par apprenant, plus une moyenne simple.
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
        $moyenne = $renseignees->isNotEmpty() ? round($renseignees->avg(), 2) : '';

        return array_merge(
            [$student['matricule'] ?? '—', $student['nom'] ?? '—', $student['prenom'] ?? '—'],
            $valeurs->map(fn ($v) => $v ?? '')->all(),
            [$moyenne]
        );
    }
}
