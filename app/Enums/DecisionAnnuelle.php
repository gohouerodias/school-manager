<?php

namespace App\Enums;

enum DecisionAnnuelle: string
{
    case Admis = 'admis';
    case Redouble = 'redouble';
    case Exclu = 'exclu';

    public function label(): string
    {
        return match ($this) {
            self::Admis => 'Admis(e)',
            self::Redouble => 'Redouble',
            self::Exclu => 'Exclu(e)',
        };
    }
}
