<?php

namespace App\Enums;

enum TypeImportDonnees: string
{
    case Eleves = 'eleves';
    case Notes = 'notes';
    case Enseignants = 'enseignants';
}
