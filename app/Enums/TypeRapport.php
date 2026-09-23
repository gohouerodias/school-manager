<?php

namespace App\Enums;

enum TypeRapport: string
{
    case Effectifs = 'effectifs';
    case Resultats = 'resultats';
    case Archives = 'archives';

    public function label(): string
    {
        return match ($this) {
            self::Effectifs => 'Effectifs',
            self::Resultats => 'Résultats',
            self::Archives => 'Archives',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Effectifs => "Nombre d'apprenants par niveau/classe, répartition par sexe et apprenants sans classe, pour une année académique.",
            self::Resultats => 'Moyenne de classe et taux Admis/Redouble/Exclu par classe, pour une année académique.',
            self::Archives => "Liste des apprenants archivés, avec leur date d'archivage et leur dernière classe.",
        };
    }

    /**
     * Effectifs et Résultats se lisent pour une année académique donnée ;
     * Archives ne dépend d'aucune année (un apprenant archivé le reste,
     * indépendamment de l'année académique consultée).
     */
    public function necessiteAnneeAcademique(): bool
    {
        return $this !== self::Archives;
    }
}
