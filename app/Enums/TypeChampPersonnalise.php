<?php

namespace App\Enums;

enum TypeChampPersonnalise: string
{
    case Texte = 'texte';
    case Date = 'date';
    case ListeDeroulante = 'liste_deroulante';
    case Nombre = 'nombre';

    public function label(): string
    {
        return match ($this) {
            self::Texte => 'Texte',
            self::Date => 'Date',
            self::ListeDeroulante => 'Liste déroulante',
            self::Nombre => 'Nombre',
        };
    }
}
