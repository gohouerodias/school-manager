<?php

namespace App\Enums;

enum StatutImport: string
{
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case Echoue = 'echoue';
}
