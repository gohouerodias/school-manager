<?php

namespace App\Exports;

use App\Enums\StatutUtilisateur;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): Collection
    {
        return User::query()->orderBy('name')->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Nom', 'E-mail', 'Profil', 'Statut', 'Dernière connexion'];
    }

    /**
     * @return array<int, string>
     */
    public function map($user): array
    {
        return [
            $user->name,
            $user->email,
            $user->profil->label(),
            $this->statutLabel($user),
            $user->derniere_connexion_at?->format('d/m/Y H:i') ?? '—',
        ];
    }

    private function statutLabel(User $user): string
    {
        if ($user->statut === StatutUtilisateur::Archive) {
            return 'Archivé';
        }

        return $user->estEnAttenteActivation() ? 'Invitation en attente' : 'Actif';
    }
}
