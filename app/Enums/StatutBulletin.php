<?php

namespace App\Enums;

/**
 * Statut d'un bulletin mensuel (voir App\Models\Bulletin) : un Brouillon
 * reste modifiable par les enseignants ; une fois Validé par le titulaire de
 * la classe (voir Enseignant\EspaceEnseignantController::validerBulletin()),
 * les notes et commentaires de la période se verrouillent pour tout le monde
 * jusqu'à une éventuelle dévalidation.
 */
enum StatutBulletin: string
{
    case Brouillon = 'brouillon';
    case Valide = 'valide';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Valide => 'Validé',
        };
    }
}
