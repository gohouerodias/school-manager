<?php

namespace App\Exports;

use App\Services\RapportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @see RapportService::archives()
 */
class RapportArchivesExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array{lignes: Collection}  $donnees
     */
    public function __construct(private readonly array $donnees) {}

    public function collection(): Collection
    {
        return collect($this->donnees['lignes']);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Nom et prénom', 'Matricule', "Date d'archivage", 'Dernière classe'];
    }

    /**
     * @return array<int, string>
     */
    public function map($ligne): array
    {
        return [
            $ligne['nom_complet'],
            $ligne['matricule'] ?: '—',
            $ligne['date_archivage'] ?? '—',
            $ligne['derniere_classe'],
        ];
    }
}
