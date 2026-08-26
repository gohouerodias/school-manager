<?php

namespace App\Enums;

enum StatutTrimestre: string
{
    case Ouvert = 'ouvert';
    case Ferme = 'ferme';

    public function label(): string
    {
        return match ($this) {
            self::Ouvert => 'Ouvert',
            self::Ferme => 'Fermé',
        };
    }
}
