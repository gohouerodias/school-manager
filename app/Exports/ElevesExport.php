<?php

namespace App\Exports;

use App\Enums\StatutEleve;
use App\Models\Eleve;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ElevesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return Eleve::query()
            ->with(['inscriptions' => fn ($q) => $q->latest('date_inscription')->limit(1)->with('classe')])
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Matricule', 'Nom', 'Prénom', 'Sexe', 'Date de naissance', 'Classe', 'Statut'];
    }

    /**
     * @return array<int, string>
     */
    public function map($eleve): array
    {
        return [
            $eleve->matricule,
            $eleve->nom,
            $eleve->prenom,
            $eleve->sexe,
            $eleve->date_naissance->format('d/m/Y'),
            $eleve->inscriptions->first()?->classe?->nom ?? 'Sans classe',
            $eleve->statut === StatutEleve::Archive ? 'Archivé' : 'Actif',
        ];
    }
}
