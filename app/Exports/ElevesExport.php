<?php

namespace App\Exports;

use App\Enums\StatutEleve;
use App\Models\Eleve;
use App\Support\EleveFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ElevesExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Request $request) {}

    /**
     * Same search/classe/statut/date de création filters as the on-screen
     * list (see EleveController::index() / App\Support\EleveFilters) —
     * "export to Excel" means "export what's currently filtered".
     */
    public function collection(): Collection
    {
        $query = Eleve::query()
            ->with(['inscriptions' => fn ($q) => $q->latest('date_inscription')->limit(1)->with('classe')])
            ->orderBy('nom')
            ->orderBy('prenom');

        return EleveFilters::apply($query, $this->request)->get();
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
            $eleve->matricule ?? '—',
            $eleve->nom ?? '—',
            $eleve->prenom ?? '—',
            $eleve->sexe ?? '—',
            // Nullable: a fiche started via the wizard but not yet
            // "Terminer"-ed (StatutEleve::Brouillon) may not have one yet.
            $eleve->date_naissance?->format('d/m/Y') ?? '—',
            $eleve->inscriptions->first()?->classe?->nom ?? 'Sans classe',
            match ($eleve->statut) {
                StatutEleve::Archive => 'Archivé',
                StatutEleve::Brouillon => 'Brouillon',
                default => 'Actif',
            },
        ];
    }
}
