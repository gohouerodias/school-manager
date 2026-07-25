<?php

namespace App\Enums;

enum ProfilUtilisateur: string
{
    case Administrateur = 'administrateur';
    case AgentScolarite = 'agent_scolarite';
    case Enseignant = 'enseignant';
    case Direction = 'direction';

    public function label(): string
    {
        return match ($this) {
            self::Administrateur => 'Administrateur',
            self::AgentScolarite => 'Agent de scolarité',
            self::Enseignant => 'Enseignant',
            self::Direction => 'Direction',
        };
    }
}
