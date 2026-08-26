<?php

namespace App\Enums;

/**
 * "Système scolaire" choisi lors de la création d'un examen (voir
 * Academique\ExamenController) — un regroupement de un ou plusieurs
 * CycleNiveau. Maternelle et Primaire sont disponibles ; Secondaire affiche
 * un message "en cours de développement" et ne crée aucun Examen (voir
 * estDisponible()).
 */
enum SystemeScolaire: string
{
    case Maternelle = 'maternelle';
    case Primaire = 'primaire';
    case Secondaire = 'secondaire';

    public function label(): string
    {
        return match ($this) {
            self::Maternelle => 'Maternelle',
            self::Primaire => 'Primaire',
            self::Secondaire => 'Secondaire',
        };
    }

    /**
     * Cycles (Niveau::$cycle) regroupés sous ce système — un seul endroit à
     * ajuster si le découpage évolue plus tard (ex : ajout d'un cycle Lycée
     * distinct de Collège sous Secondaire).
     *
     * @return array<int, CycleNiveau>
     */
    public function cycles(): array
    {
        return match ($this) {
            self::Maternelle => [CycleNiveau::Maternelle],
            self::Primaire => [CycleNiveau::Primaire],
            self::Secondaire => [CycleNiveau::College],
        };
    }

    public function estDisponible(): bool
    {
        return $this === self::Maternelle || $this === self::Primaire;
    }
}
