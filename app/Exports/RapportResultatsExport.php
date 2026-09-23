<?php

namespace App\Exports;

use App\Models\AnneeAcademique;
use App\Services\RapportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @see RapportService::resultats()
 */
class RapportResultatsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array{annee_academique: AnneeAcademique, par_classe: Collection}  $donnees
     */
    public function __construct(private readonly array $donnees) {}

    public function collection(): Collection
    {
        return collect($this->donnees['par_classe']);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Niveau', 'Classe', 'Effectif', 'Moyenne de classe', 'Taux Admis (%)', 'Taux Redouble (%)', 'Taux Exclu (%)'];
    }

    /**
     * @return array<int, string|int|float>
     */
    public function map($ligne): array
    {
        return [
            $ligne['niveau'],
            $ligne['classe'],
            $ligne['effectif'],
            $ligne['moyenne_classe'] ?? '—',
            $ligne['taux_admis'] ?? '—',
            $ligne['taux_redouble'] ?? '—',
            $ligne['taux_exclu'] ?? '—',
        ];
    }
}
