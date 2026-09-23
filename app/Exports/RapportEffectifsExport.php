<?php

namespace App\Exports;

use App\Models\AnneeAcademique;
use App\Services\RapportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @see RapportService::effectifs()
 */
class RapportEffectifsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array{annee_academique: AnneeAcademique, par_classe: Collection, total: int, par_sexe: Collection, sans_classe: int}  $donnees
     */
    public function __construct(private readonly array $donnees) {}

    public function collection(): Collection
    {
        $lignes = collect($this->donnees['par_classe']);

        $lignes->push([
            'niveau' => '',
            'classe' => 'Total général',
            'effectif' => $this->donnees['total'],
        ]);
        $lignes->push([
            'niveau' => '',
            'classe' => 'dont Féminin',
            'effectif' => $this->donnees['par_sexe']['F'] ?? 0,
        ]);
        $lignes->push([
            'niveau' => '',
            'classe' => 'dont Masculin',
            'effectif' => $this->donnees['par_sexe']['M'] ?? 0,
        ]);
        $lignes->push([
            'niveau' => '',
            'classe' => 'Sans classe',
            'effectif' => $this->donnees['sans_classe'],
        ]);

        return $lignes;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Niveau', 'Classe', 'Effectif'];
    }

    /**
     * @return array<int, string|int>
     */
    public function map($ligne): array
    {
        return [$ligne['niveau'], $ligne['classe'], $ligne['effectif']];
    }
}
